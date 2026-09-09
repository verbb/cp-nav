<?php
namespace verbb\cpnav\nav\sources;

use Craft;
use craft\base\Component;

/**
 * Cached nav sources tree.
 *
 * Shared Craft cache is keyed by generation + fingerprint. `invalidate()` bumps the
 * generation so every worker drops stale entries without enumerating fingerprint keys.
 * Labels are language-sensitive — language is part of the fingerprint.
 */
final class NavSources extends Component
{
    // Constants
    // =========================================================================

    private const CACHE_GENERATION_KEY = 'cpnav:sources:generation';
    /** v3 — generation eviction + language in fingerprint. */
    private const CACHE_KEY_PREFIX = 'cpnav:sources:v3:';


    // Properties
    // =========================================================================

    private ?string $_fingerprint = null;
    private ?array $_tree = null;
    private ?int $_generation = null;


    // Public Methods
    // =========================================================================

    public function getFingerprint(): string
    {
        return $this->_fingerprint ??= (new NavFingerprint())->compute();
    }

    public function getTree(bool $forceRebuild = false): array
    {
        // Same-request memo — invalidate()/forceRebuild clear it. Avoid re-fingerprinting
        // on every builder lookup (reorder used to recompute hundreds of times).
        if (!$forceRebuild && $this->_tree !== null) {
            return $this->_tree;
        }

        $fingerprint = $this->getFingerprint();
        $generation = $this->_cacheGeneration();
        $cache = Craft::$app->getCache();
        $cacheKey = self::CACHE_KEY_PREFIX . $generation . ':' . $fingerprint;
        $cached = $cache->get($cacheKey);

        if (!$forceRebuild && is_array($cached)) {
            $this->_tree = $this->_hydrateTree($cached);

            return $this->_tree;
        }

        $tree = (new NavSourceBuilder())->build();
        $this->_tree = $tree;
        $cache->set($cacheKey, $this->_dehydrateTree($tree));

        return $tree;
    }

    public function invalidate(): void
    {
        $this->_tree = null;
        $this->_fingerprint = null;
        $this->_generation = null;

        $cache = Craft::$app->getCache();
        $next = ((int)$cache->get(self::CACHE_GENERATION_KEY)) + 1;
        $cache->set(self::CACHE_GENERATION_KEY, $next);
    }


    // Private Methods
    // =========================================================================

    private function _cacheGeneration(): int
    {
        if ($this->_generation !== null) {
            return $this->_generation;
        }

        $cache = Craft::$app->getCache();
        $generation = (int)$cache->get(self::CACHE_GENERATION_KEY);

        if ($generation < 1) {
            $generation = 1;
            $cache->set(self::CACHE_GENERATION_KEY, $generation);
        }

        return $this->_generation = $generation;
    }

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
