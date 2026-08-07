<?php
namespace verbb\cpnav\nav\resolve;

use verbb\cpnav\events\ModifyResolvedNavEvent;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\sources\NodeKey;

use craft\base\Component;

/**
 * Merges nav sources with per-layout customizations.
 */
class NavResolver extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_MODIFY_RESOLVED_NAV = 'modifyResolvedNav';


    // Public Methods
    // =========================================================================

    /**
     * @param array $registryTree
     * @param array<string, CustomizationNode> $overlayByKey
     * @return ResolvedNavNode[]
     */
    public function resolve(array $registryTree, array $overlayByKey = [], ?string $layoutUid = null): array
    {
        $registryIndex = $this->_indexRegistry($registryTree);
        $workingOverlay = $overlayByKey;

        // Default-position insert: ensure every nav source node has customization sort metadata.
        foreach ($registryIndex as $key => $indexed) {
            if (isset($workingOverlay[$key]) || NodeKey::isCustomizationOnly($key)) {
                continue;
            }

            $workingOverlay[$key] = new CustomizationNode(
                key: $key,
                enabled: true,
                sort: $indexed['defaultOrder'] * 10,
                parent: $indexed['defaultParent'],
            );
        }

        $resolved = [];

        foreach ($workingOverlay as $key => $overlay) {
            $isCustomizationOnly = NodeKey::isCustomizationOnly($key);
            $registry = $registryIndex[$key] ?? null;

            // Stale canonical customization keys are ignored at resolve time (D19).
            if (!$isCustomizationOnly && $registry === null) {
                continue;
            }

            $parentKey = $overlay->parent ?? $registry['defaultParent'] ?? null;

            // Manual/divider: overlay only. Registry nodes inherit Craft `external` (e.g. GraphiQL)
            // unless the overlay explicitly sets newWindow true.
            $newWindow = $isCustomizationOnly
                ? $overlay->newWindow
                : ($overlay->newWindow || (bool)($registry['node']->defaultExternal ?? false));

            $resolved[] = new ResolvedNavNode(
                key: $key,
                label: $overlay->label ?? $registry['node']->defaultLabel ?? '',
                url: $overlay->url ?? $registry['node']->defaultUrl ?? '',
                sort: $overlay->sort,
                parentKey: $parentKey,
                source: $registry['node']->source ?? (string)($overlay->type ?? 'manual'),
                enabled: $overlay->enabled,
                icon: $overlay->icon ?? $registry['node']->icon ?? null,
                customIcon: $overlay->customIcon ?? null,
                newWindow: $newWindow,
            );
        }

        // D22 — manual/divider under a removed parent promote to top level.
        $resolved = $this->_reparentOrphans($resolved);
        // Dividers are section breaks — always top-level (never nested under another item).
        $resolved = $this->_forceDividersTopLevel($resolved);
        $resolved = $this->_sortTree($resolved);

        if ($this->hasEventHandlers(self::EVENT_MODIFY_RESOLVED_NAV)) {
            $event = new ModifyResolvedNavEvent([
                'resolvedNodes' => $resolved,
                'layoutUid' => $layoutUid,
            ]);
            $this->trigger(self::EVENT_MODIFY_RESOLVED_NAV, $event);
            $resolved = $this->_sortTree($event->resolvedNodes);
        }

        return $resolved;
    }


    // Private Methods
    // =========================================================================

    /**
     * @param ResolvedNavNode[] $resolved
     * @return ResolvedNavNode[]
     */
    private function _reparentOrphans(array $resolved): array
    {
        $keys = [];
        foreach ($resolved as $node) {
            $keys[$node->key] = true;
        }

        $out = [];

        foreach ($resolved as $node) {
            if ($node->parentKey === null || isset($keys[$node->parentKey])) {
                $out[] = $node;
                continue;
            }

            // Parent missing from resolved tree — only customization-only nodes should float up.
            if (NodeKey::isCustomizationOnly($node->key)) {
                $out[] = $node->withParentKey(null, true);
                continue;
            }

            $out[] = $node->withParentKey(null, false);
        }

        return $out;
    }

    /**
     * @param ResolvedNavNode[] $resolved
     * @return ResolvedNavNode[]
     */
    private function _forceDividersTopLevel(array $resolved): array
    {
        $out = [];

        foreach ($resolved as $node) {
            if (NodeKey::isDivider($node->key) && $node->parentKey !== null) {
                $out[] = $node->withParentKey(null, false);
                continue;
            }

            $out[] = $node;
        }

        return $out;
    }

    private function _indexRegistry(array $registryTree): array
    {
        $index = [];

        $walk = function(array $nodes, ?string $parentKey) use (&$index, &$walk): void {
            foreach ($nodes as $node) {
                $index[$node->key] = [
                    'node' => $node,
                    'defaultOrder' => $node->defaultOrder,
                    'defaultParent' => $parentKey,
                ];

                if ($node->children) {
                    $walk($node->children, $node->key);
                }
            }
        };

        $walk($registryTree, null);

        return $index;
    }

    /**
     * @param ResolvedNavNode[] $nodes
     * @return ResolvedNavNode[]
     */
    private function _sortTree(array $nodes): array
    {
        usort($nodes, function(ResolvedNavNode $a, ResolvedNavNode $b): int {
            if (($a->parentKey ?? '') !== ($b->parentKey ?? '')) {
                return strcmp($a->parentKey ?? '', $b->parentKey ?? '');
            }

            if ($a->sort !== $b->sort) {
                return $a->sort <=> $b->sort;
            }

            return strcmp($a->key, $b->key);
        });

        return $nodes;
    }
}
