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
        // Missing registry keys get a resolve-time sort between Craft’s default neighbours,
        // not absolute defaultOrder*10 (that collides with frozen pre-appearance overlay sorts).
        $workingOverlay = $this->_insertMissingAtDefaultPositions($registryIndex, $overlayByKey);

        $resolved = [];

        foreach ($workingOverlay as $key => $overlay) {
            $isCustomizationOnly = NodeKey::isCustomizationOnly($key);
            $registry = $registryIndex[$key] ?? null;

            // Stale canonical customization keys are ignored at resolve time.
            if (!$isCustomizationOnly && $registry === null) {
                continue;
            }

            // null parent = inherit registry; '' = explicit root; key = nest under that node.
            $parentKey = $overlay->resolvedParent($registry['defaultParent'] ?? null);

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

        // Manual/divider under a removed parent promote to top level.
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
     * Ensure every nav source key has a working customization entry, with new keys sorted
     * between the nearest lower/higher *default* siblings already present.
     */
    private function _insertMissingAtDefaultPositions(array $registryIndex, array $overlayByKey): array
    {
        $workingOverlay = $overlayByKey;
        $byParent = [];

        foreach ($registryIndex as $key => $indexed) {
            $parentGroup = $indexed['defaultParent'] ?? '';
            $byParent[$parentGroup][] = [
                'key' => $key,
                'defaultOrder' => $indexed['defaultOrder'],
                'defaultParent' => $indexed['defaultParent'],
            ];
        }

        foreach ($byParent as &$siblings) {
            usort($siblings, fn(array $a, array $b): int => $a['defaultOrder'] <=> $b['defaultOrder']);
        }
        unset($siblings);

        // Insert in Craft defaultOrder so each new key can anchor against ones we just placed.
        foreach ($byParent as $parentGroup => $siblings) {
            $parentKey = $parentGroup === '' ? null : $parentGroup;

            foreach ($siblings as $indexed) {
                $key = $indexed['key'];

                if (isset($workingOverlay[$key]) || NodeKey::isCustomizationOnly($key)) {
                    continue;
                }

                $sort = $this->_defaultPositionSort(
                    $key,
                    $indexed['defaultOrder'],
                    $parentKey,
                    $siblings,
                    $workingOverlay,
                    $registryIndex,
                );

                // Inherit registry parent — do not snapshot it into the working overlay.
                $workingOverlay[$key] = new CustomizationNode(
                    key: $key,
                    enabled: true,
                    sort: $sort,
                    parent: null,
                );
            }
        }

        return $workingOverlay;
    }

    /**
     * Pick a sort between the nearest preceding/following registry siblings that still share
     * this parent in the working overlay. Falls back to defaultOrder*10 only when no anchors exist.
     */
    private function _defaultPositionSort(
        string $key,
        int $defaultOrder,
        ?string $parentKey,
        array $registrySiblings,
        array &$workingOverlay,
        array $registryIndex = [],
    ): int {
        $prevSort = null;
        $nextSort = null;

        foreach ($registrySiblings as $sibling) {
            $siblingKey = $sibling['key'];

            if ($siblingKey === $key || !isset($workingOverlay[$siblingKey])) {
                continue;
            }

            // Skip anchors the admin reparented away from this default parent.
            $effectiveParent = $workingOverlay[$siblingKey]->resolvedParent($sibling['defaultParent']);
            if ($effectiveParent !== $parentKey) {
                continue;
            }

            if ($sibling['defaultOrder'] < $defaultOrder) {
                $prevSort = $workingOverlay[$siblingKey]->sort;
                continue;
            }

            if ($sibling['defaultOrder'] > $defaultOrder) {
                $nextSort = $workingOverlay[$siblingKey]->sort;
                break;
            }
        }

        if ($prevSort !== null && $nextSort !== null) {
            $mid = intdiv($prevSort + $nextSort, 2);

            if ($mid > $prevSort && $mid < $nextSort) {
                return $mid;
            }

            // No integer gap (e.g. 20 then 21) — open a slot after prev at resolve time only.
            $sort = $prevSort + 1;
            $this->_shiftSiblingSortsFrom($workingOverlay, $parentKey, $sort, $key, $registryIndex);

            return $sort;
        }

        if ($prevSort !== null) {
            return $prevSort + 10;
        }

        if ($nextSort !== null) {
            $sort = $nextSort - 10;

            if ($sort < 0) {
                $sort = 0;
            }

            // next was already at 0…9 — make room rather than collide / go negative.
            if ($sort >= $nextSort) {
                $sort = $nextSort;
                $this->_shiftSiblingSortsFrom($workingOverlay, $parentKey, $sort, $key, $registryIndex);
            }

            return $sort;
        }

        return $defaultOrder * 10;
    }

    /**
     * Bump same-parent working sorts at/after $fromSort so a newly inserted key can own that slot.
     * Resolve-time only — does not write project config.
     */
    private function _shiftSiblingSortsFrom(
        array &$workingOverlay,
        ?string $parentKey,
        int $fromSort,
        string $exceptKey,
        array $registryIndex = [],
    ): void {
        foreach ($workingOverlay as $key => $node) {
            if ($key === $exceptKey || $node->sort < $fromSort) {
                continue;
            }

            $defaultParent = $registryIndex[$key]['defaultParent'] ?? null;
            if ($node->resolvedParent($defaultParent) !== $parentKey) {
                continue;
            }

            $workingOverlay[$key] = new CustomizationNode(
                key: $node->key,
                enabled: $node->enabled,
                sort: $node->sort + 1,
                parent: $node->parent,
                label: $node->label,
                type: $node->type,
                url: $node->url,
                icon: $node->icon,
                customIcon: $node->customIcon,
                newWindow: $node->newWindow,
            );
        }
    }

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
