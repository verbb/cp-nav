<?php
namespace verbb\cpnav\nav\sources;

use Craft;
use craft\base\Component;

/**
 * Cached nav sources tree.
 */
final class NavSources extends Component
{
    // Properties
    // =========================================================================

    private ?string $_fingerprint = null;
    private ?array $_tree = null;


    // Public Methods
    // =========================================================================

    public function getFingerprint(): string
    {
        return $this->_fingerprint ??= (new NavFingerprint())->compute();
    }

    public function getTree(bool $forceRebuild = false): array
    {
        $fingerprint = (new NavFingerprint())->compute();

        if (!$forceRebuild && $this->_tree !== null && $this->_fingerprint === $fingerprint) {
            return $this->_tree;
        }

        $cache = Craft::$app->getCache();
        // v2 — includes defaultExternal (Craft `external`, e.g. GraphiQL).
        $cacheKey = 'cpnav:sources:v2:' . $fingerprint;
        $cached = $cache->get($cacheKey);

        if (!$forceRebuild && is_array($cached)) {
            $this->_fingerprint = $fingerprint;
            $this->_tree = $this->_hydrateTree($cached);

            return $this->_tree;
        }

        $tree = (new NavSourceBuilder())->build();
        $this->_fingerprint = $fingerprint;
        $this->_tree = $tree;

        $cache->set($cacheKey, $this->_dehydrateTree($tree));

        return $tree;
    }

    public function invalidate(): void
    {
        $this->_tree = null;
        $this->_fingerprint = null;
    }


    // Private Methods
    // =========================================================================

    private function _dehydrateTree(array $tree): array
    {
        return array_map(fn(NavNode $node) => $this->_dehydrateNode($node), $tree);
    }

    private function _dehydrateNode(NavNode $node): array
    {
        return [
            'key' => $node->key,
            'source' => $node->source,
            'defaultLabel' => $node->defaultLabel,
            'defaultUrl' => $node->defaultUrl,
            'icon' => $node->icon,
            'defaultOrder' => $node->defaultOrder,
            'parentKey' => $node->parentKey,
            'subHandle' => $node->subHandle,
            'defaultExternal' => $node->defaultExternal,
            'children' => array_map(fn(NavNode $child) => $this->_dehydrateNode($child), $node->children),
        ];
    }

    private function _hydrateTree(array $cached): array
    {
        return array_map(fn(array $node) => $this->_hydrateNode($node), $cached);
    }

    private function _hydrateNode(array $data): NavNode
    {
        $children = array_map(fn(array $child) => $this->_hydrateNode($child), $data['children'] ?? []);

        return new NavNode(
            key: (string)$data['key'],
            source: (string)$data['source'],
            defaultLabel: (string)$data['defaultLabel'],
            defaultUrl: (string)$data['defaultUrl'],
            icon: $data['icon'] ?? null,
            defaultOrder: (int)$data['defaultOrder'],
            parentKey: $data['parentKey'] ?? null,
            children: $children,
            subHandle: $data['subHandle'] ?? null,
            defaultExternal: (bool)($data['defaultExternal'] ?? false),
        );
    }
}
