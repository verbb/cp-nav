<?php
namespace verbb\cpnav\upgrade;

use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\models\LayoutNavItem;

/**
 * Transforms v5 Navigation rows into customization nodes.
 */
final class CustomizationUpgrader
{
    // Public Methods
    // =========================================================================

    public function upgradeNavigations(array $navigations): array
    {
        if ($navigations === []) {
            return [];
        }

        $byId = [];
        foreach ($navigations as $navigation) {
            if ($navigation->id) {
                $byId[$navigation->id] = $navigation;
            }
        }

        $resolved = [];

        foreach ($navigations as $navigation) {
            // Current parentId is authoritative. Only fall back to prevParentId when the
            // referenced parent row is missing (orphan recovery) — never when parentId is
            // explicitly null/0 (intentional v5 outdent to root).
            $parent = null;
            if ($navigation->parentId) {
                $parent = $byId[$navigation->parentId] ?? null;

                if (!$parent && $navigation->prevParentId) {
                    $parent = $byId[$navigation->prevParentId] ?? null;
                }
            }

            $parentKey = $parent ? V5KeyMap::resolveParentKey($parent) : null;
            $key = V5KeyMap::resolveKey($navigation, $parent);

            // Sync bugs can duplicate rows — keep the lowest sortOrder per key.
            if (isset($resolved[$key]) && ($resolved[$key]['sortOrder'] ?? PHP_INT_MAX) <= ($navigation->sortOrder ?? PHP_INT_MAX)) {
                continue;
            }

            $resolved[$key] = [
                'navigation' => $navigation,
                'parent' => $parent,
                'parentKey' => $parentKey,
                'sortOrder' => $navigation->sortOrder ?? 0,
            ];
        }

        $groups = [];
        foreach ($resolved as $key => $row) {
            $parentKey = $row['parentKey'] ?? '';
            $groups[$parentKey][$key] = $row;
        }

        $customizations = [];

        foreach ($groups as $parentKey => $siblings) {
            uasort($siblings, fn(array $a, array $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));

            $sort = 0;
            foreach ($siblings as $key => $row) {
                $sort += 10;
                $customizations[$key] = $this->_buildCustomizationNode(
                    $row['navigation'],
                    $row['parent'],
                    $parentKey === '' ? null : $parentKey,
                    $sort,
                );
            }
        }

        return $customizations;
    }


    // Private Methods
    // =========================================================================

    private function _buildCustomizationNode(
        LayoutNavItem $navigation,
        ?LayoutNavItem $parent,
        ?string $parentKey,
        int $sort,
    ): CustomizationNode {
        $key = V5KeyMap::resolveKey($navigation, $parent);
        $isCustomizationOnly = $navigation->isManual() || $navigation->isDivider();

        // Manuals/dividers always keep their label (v5 often sets currLabel === prevLabel on create).
        // Canonical nodes only store a delta when the label was renamed.
        $label = null;
        if ($isCustomizationOnly) {
            $label = $navigation->currLabel;
        } elseif ($navigation->currLabel !== $navigation->prevLabel) {
            $label = $navigation->currLabel;
        }

        $url = null;
        if ($isCustomizationOnly) {
            $url = $navigation->url;
        } elseif (($navigation->url ?? null) !== ($navigation->prevUrl ?? null)) {
            $url = $navigation->url;
        }

        $type = $isCustomizationOnly ? $navigation->type : null;

        // Explicit root vs nested — never leave null (inherit) for migrated placement.
        $storedParent = $parentKey === null
            ? CustomizationNode::PARENT_ROOT
            : $parentKey;

        return new CustomizationNode(
            key: $key,
            enabled: (bool)$navigation->enabled,
            sort: $sort,
            parent: $storedParent,
            label: $label,
            type: $type,
            url: $url,
            icon: $navigation->icon ?: null,
            customIcon: $navigation->customIcon ?: null,
            newWindow: (bool)$navigation->newWindow,
        );
    }
}
