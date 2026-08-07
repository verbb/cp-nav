<?php
namespace verbb\cpnav\nav\builder;

use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\models\Layout;
use verbb\cpnav\models\LayoutNavItem;
use verbb\cpnav\nav\sources\NavNode;
use verbb\cpnav\nav\sources\NodeKey;
use verbb\cpnav\nav\resolve\ResolvedNavNode;

use craft\base\Component;
use craft\helpers\StringHelper;

/**
 * Customization-backed builder operations for the CP Nav settings UI.
 */
class NavBuilderService extends Component
{
    // Public Methods
    // =========================================================================

    public function getLayoutNavItemsForLayout(int $layoutId): array
    {
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return [];
        }

        return $this->_buildLayoutNavItems($layout);
    }

    public function getLayoutNavItemByBuilderId(int $layoutId, int $builderId): ?LayoutNavItem
    {
        foreach ($this->getLayoutNavItemsForLayout($layoutId) as $navigation) {
            if ($navigation->id === $builderId) {
                return $navigation;
            }

            foreach ($navigation->getChildren() as $child) {
                if ($child->id === $builderId) {
                    return $child;
                }
            }
        }

        return null;
    }

    public function getLayoutNavItemByKey(int $layoutId, string $nodeKey): ?LayoutNavItem
    {
        foreach ($this->getLayoutNavItemsForLayout($layoutId) as $navigation) {
            if ($navigation->nodeKey === $nodeKey) {
                return $navigation;
            }

            foreach ($navigation->getChildren() as $child) {
                if ($child->nodeKey === $nodeKey) {
                    return $child;
                }
            }
        }

        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return null;
        }

        $registryNode = $this->_registryNodeForKey($nodeKey);
        $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        $customization = $overlay[$nodeKey] ?? null;

        if (!$registryNode || !$customization) {
            return null;
        }

        $resolved = new ResolvedNavNode(
            key: $nodeKey,
            label: $customization->label ?? $registryNode->defaultLabel,
            url: $customization->url ?? $registryNode->defaultUrl,
            sort: $customization->sort,
            parentKey: $customization->parent,
            source: $registryNode->source,
            enabled: $customization->enabled,
            icon: $customization->icon ?? $registryNode->icon,
            customIcon: $customization->customIcon,
            newWindow: $customization->newWindow || $registryNode->defaultExternal,
        );

        return $this->_layoutNavItemFromResolved($layout, $resolved, $registryNode);
    }

    public function toggleEnabled(int $layoutId, int $builderId, bool $enabled): bool
    {
        $navigation = $this->getLayoutNavItemByBuilderId($layoutId, $builderId);

        if (!$navigation || !$navigation->nodeKey) {
            return false;
        }

        $layout = $navigation->getLayout();
        $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        $node = $overlay[$navigation->nodeKey] ?? $this->_defaultCustomizationNode($navigation->nodeKey, $navigation);

        CpNav::$plugin->getNavCustomization()->saveNode(
            $layout->uid,
            new CustomizationNode(
                key: $node->key,
                enabled: $enabled,
                sort: $node->sort,
                parent: $node->parent,
                label: $node->label,
                type: $node->type,
                url: $node->url,
                icon: $node->icon,
                customIcon: $node->customIcon,
                newWindow: $node->newWindow,
            ),
        );

        return true;
    }

    public function reorder(int $layoutId, array $items): bool
    {
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return false;
        }

        $nodesByBuilderId = [];
        foreach ($this->getLayoutNavItemsForLayout($layoutId) as $navigation) {
            $nodesByBuilderId[(int)$navigation->id] = ['builderId' => $navigation->id, 'key' => $navigation->nodeKey];
            foreach ($navigation->getChildren() as $child) {
                $nodesByBuilderId[(int)$child->id] = ['builderId' => $child->id, 'key' => $child->nodeKey];
            }
        }

        if (NavTreeReparent::validateReorderItems($items, $nodesByBuilderId) !== []) {
            return false;
        }

        $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        $updated = $overlay;

        foreach ($items as $sort => $item) {
            $builderId = (int)($item['id'] ?? 0);
            $navigation = $this->getLayoutNavItemByBuilderId($layoutId, $builderId);

            if (!$navigation || !$navigation->nodeKey) {
                continue;
            }

            $parentKey = null;
            $parentId = $item['parentId'] ?? null;

            if ($parentId) {
                $parent = $this->getLayoutNavItemByBuilderId($layoutId, (int)$parentId);

                // Parent must exist and be a root (D15).
                if (!$parent || !$parent->nodeKey || $parent->parentId) {
                    return false;
                }

                $parentKey = $parent->nodeKey;
            }

            $existing = $updated[$navigation->nodeKey] ?? $this->_defaultCustomizationNode($navigation->nodeKey, $navigation);

            $updated[$navigation->nodeKey] = new CustomizationNode(
                key: $existing->key,
                enabled: $existing->enabled,
                sort: ((int)$sort + 1) * 10,
                parent: $parentKey,
                label: $existing->label,
                type: $existing->type,
                url: $existing->url,
                icon: $existing->icon,
                customIcon: $existing->customIcon,
                newWindow: $existing->newWindow,
            );
        }

        CpNav::$plugin->getNavCustomization()->setCustomizationNodes($layout->uid, $updated);

        return true;
    }

    /**
     * Indent a node under the previous root sibling (D15).
     */
    public function indentNode(int $layoutId, string $nodeKey): bool
    {
        $nodes = $this->_flatBuilderNodes($layoutId);
        $next = NavTreeReparent::indent($nodes, $nodeKey);

        if ($next === null) {
            return false;
        }

        return $this->reorder($layoutId, NavTreeReparent::toReorderPayload($next));
    }

    /**
     * Outdent a nested node to top level, after its former parent block (D15).
     */
    public function outdentNode(int $layoutId, string $nodeKey): bool
    {
        $nodes = $this->_flatBuilderNodes($layoutId);
        $next = NavTreeReparent::outdent($nodes, $nodeKey);

        if ($next === null) {
            return false;
        }

        return $this->reorder($layoutId, NavTreeReparent::toReorderPayload($next));
    }

    /**
     * Set an explicit parent key (null = root). Enforces max depth 2.
     */
    public function reparentNode(int $layoutId, string $nodeKey, ?string $parentKey): bool
    {
        $nodes = $this->_flatBuilderNodes($layoutId);
        $index = null;
        $parentBuilderId = null;

        foreach ($nodes as $i => $node) {
            if (($node['key'] ?? null) === $nodeKey) {
                $index = $i;
            }

            if ($parentKey !== null && ($node['key'] ?? null) === $parentKey) {
                $parentBuilderId = (int)$node['builderId'];

                // Parent must be a current root (D15).
                if (!empty($node['parentId'])) {
                    return false;
                }
            }
        }

        if ($index === null) {
            return false;
        }

        // Dividers are top-level section breaks only.
        if ($parentKey !== null && NodeKey::isDivider($nodeKey)) {
            return false;
        }

        if ($parentKey !== null && NodeKey::isDivider($parentKey)) {
            return false;
        }

        if ($parentKey !== null && $parentBuilderId === null) {
            return false;
        }

        if ($parentKey === $nodeKey) {
            return false;
        }

        // Moving a node that has children under another parent would create depth 3.
        if ($parentKey !== null) {
            $builderId = (int)$nodes[$index]['builderId'];

            foreach ($nodes as $node) {
                if ((int)($node['parentId'] ?? 0) === $builderId) {
                    return false;
                }
            }
        }

        $nodes[$index]['parentId'] = $parentBuilderId;
        $nodes[$index]['level'] = $parentBuilderId ? 2 : 1;

        return $this->reorder($layoutId, NavTreeReparent::toReorderPayload(array_values($nodes)));
    }

    public function saveLayoutNavItem(LayoutNavItem $navigation): bool
    {
        if (!$navigation->validate()) {
            return false;
        }

        $layout = $navigation->getLayout();
        $key = $navigation->nodeKey;

        if (!$key) {
            return false;
        }

        $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        $existing = $overlay[$key] ?? $this->_defaultCustomizationNode($key, $navigation);
        $registryNode = $this->_registryNodeForKey($key);
        $label = $navigation->currLabel;

        if ($registryNode && $label === $registryNode->defaultLabel) {
            $label = null;
        }

        CpNav::$plugin->getNavCustomization()->saveNode(
            $layout->uid,
            new CustomizationNode(
                key: $key,
                enabled: (bool)$navigation->enabled,
                sort: $existing->sort,
                parent: $existing->parent,
                label: $label,
                type: $existing->type,
                url: $navigation->url !== ($registryNode->defaultUrl ?? $navigation->url) ? $navigation->url : $existing->url,
                icon: $navigation->icon ?: null,
                customIcon: $navigation->customIcon ?: null,
                newWindow: (bool)$navigation->newWindow,
            ),
        );

        return true;
    }

    public function createLayoutNavItem(LayoutNavItem $navigation): bool
    {
        if (!$navigation->validate()) {
            return false;
        }

        $layout = $navigation->getLayout();
        $type = $navigation->type ?? LayoutNavItem::TYPE_MANUAL;
        $uuid = StringHelper::UUID();
        $key = $type === LayoutNavItem::TYPE_DIVIDER
            ? NodeKey::divider($uuid)
            : NodeKey::manual($uuid);

        $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        // Append after the *resolved* top-level order — not overlay-only maxSort.
        // Overlay is often empty/sparse; registry defaults use defaultOrder*10 (Dashboard=10),
        // so overlay maxSort=0 → sort=10 put new manuals in the second slot (tie with Dashboard).
        $maxSort = $this->_maxTopLevelResolvedSort($layout, $overlay);

        CpNav::$plugin->getNavCustomization()->saveNode(
            $layout->uid,
            new CustomizationNode(
                key: $key,
                enabled: true,
                sort: $maxSort + 10,
                parent: null,
                label: $navigation->currLabel,
                type: $type,
                url: $navigation->url,
                icon: $navigation->icon,
                customIcon: $navigation->customIcon,
                newWindow: (bool)$navigation->newWindow,
            ),
        );

        $navigation->nodeKey = $key;
        $navigation->id = $this->_builderIdForKey($key);
        $navigation->uid = $uuid;

        return true;
    }

    public function deleteLayoutNavItem(int $layoutId, int $builderId): bool
    {
        $navigation = $this->getLayoutNavItemByBuilderId($layoutId, $builderId);

        if (!$navigation || !$navigation->nodeKey) {
            return false;
        }

        if (!$navigation->isManual() && !$navigation->isDivider()) {
            return false;
        }

        $layout = $navigation->getLayout();
        CpNav::$plugin->getNavCustomization()->removeNode($layout->uid, $navigation->nodeKey);

        return true;
    }

    public function resetLayout(int $layoutId): void
    {
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return;
        }

        CpNav::$plugin->getNavCustomization()->clearCustomization($layout->uid);
        CpNav::$plugin->getNavCustomization()->acknowledgeCurrentRegistry($layout->uid);
    }


    // Private Methods
    // =========================================================================

    /**
     * Flat builder nodes in display order — same shape as NavBuilderApi tree nodes
     * (subset of fields needed for reparent transforms).
     *
     * @return array<int, array{builderId: int, key: string, parentId: int|null, level: int}>
     */
    private function _flatBuilderNodes(int $layoutId): array
    {
        $nodes = [];

        foreach ($this->getLayoutNavItemsForLayout($layoutId) as $navigation) {
            if ($navigation->parentId) {
                continue;
            }

            $nodes[] = [
                'builderId' => (int)$navigation->id,
                'key' => (string)$navigation->nodeKey,
                'parentId' => null,
                'level' => 1,
            ];

            foreach ($navigation->getChildren() as $child) {
                $nodes[] = [
                    'builderId' => (int)$child->id,
                    'key' => (string)$child->nodeKey,
                    'parentId' => (int)$navigation->id,
                    'level' => 2,
                ];
            }
        }

        return $nodes;
    }

    private function _buildLayoutNavItems(Layout $layout): array
    {
        $registryTree = CpNav::$plugin->getNavSources()->getTree();
        $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        $resolved = CpNav::$plugin->getNavResolver()->resolve($registryTree, $overlay, $layout->uid);
        $registryIndex = $this->_indexRegistry($registryTree);
        $navigations = [];
        $byKey = [];

        foreach ($resolved as $node) {
            $navigation = $this->_layoutNavItemFromResolved($layout, $node, $registryIndex[$node->key] ?? null);
            $byKey[$node->key] = $navigation;
            $navigations[] = $navigation;
        }

        foreach ($navigations as $navigation) {
            if (!$navigation->parentId) {
                continue;
            }

            foreach ($byKey as $parent) {
                if ($parent->id === $navigation->parentId) {
                    $parent->addChild($navigation);
                    $navigation->assignParent($parent);
                    break;
                }
            }
        }

        return $navigations;
    }

    /**
     * Highest sibling sort among top-level resolved nodes (registry defaults + overlay).
     * Used so admin-created manuals/dividers append at the end of the builder tree.
     *
     * @param array<string, CustomizationNode> $overlay
     */
    private function _maxTopLevelResolvedSort(Layout $layout, array $overlay): int
    {
        $registryTree = CpNav::$plugin->getNavSources()->getTree();
        $resolved = CpNav::$plugin->getNavResolver()->resolve($registryTree, $overlay, $layout->uid);
        $maxSort = 0;

        foreach ($resolved as $node) {
            if ($node->parentKey !== null) {
                continue;
            }

            $maxSort = max($maxSort, $node->sort);
        }

        return $maxSort;
    }

    private function _layoutNavItemFromResolved(Layout $layout, ResolvedNavNode $node, ?NavNode $registryNode): LayoutNavItem
    {
        $parentBuilderId = null;

        if ($node->parentKey) {
            $parentBuilderId = $this->_builderIdForKey($node->parentKey);
        }

        $navigation = new LayoutNavItem();
        $navigation->nodeKey = $node->key;
        $navigation->id = $this->_builderIdForKey($node->key);
        $navigation->layoutId = $layout->id;
        $navigation->enabled = $node->enabled;
        $navigation->level = $node->parentKey ? 2 : 1;
        $navigation->parentId = $parentBuilderId;
        $navigation->sortOrder = $node->sort;
        $navigation->prevLabel = $registryNode->defaultLabel ?? $node->label;
        $navigation->currLabel = $node->label;
        $navigation->url = $node->url;
        $navigation->prevUrl = $registryNode->defaultUrl ?? $node->url;
        $navigation->type = $this->_typeForKey($node->key, $node);
        $navigation->handle = $this->_handleForKey($node->key);
        $navigation->icon = $node->icon;
        $navigation->customIcon = $node->customIcon;
        $navigation->newWindow = $node->newWindow;
        $navigation->isOrphan = $node->isOrphan;
        $navigation->uid = $this->_uidFromKey($node->key);

        return $navigation;
    }

    private function _defaultCustomizationNode(string $key, LayoutNavItem $navigation): CustomizationNode
    {
        return new CustomizationNode(
            key: $key,
            enabled: (bool)$navigation->enabled,
            sort: (int)($navigation->sortOrder ?? 0),
            parent: null,
            label: $navigation->currLabel !== $navigation->prevLabel ? $navigation->currLabel : null,
            type: $navigation->type,
            url: $navigation->url,
            icon: $navigation->icon,
            customIcon: $navigation->customIcon,
            newWindow: (bool)$navigation->newWindow,
        );
    }

    private function _registryNodeForKey(string $key): ?NavNode
    {
        return $this->_indexRegistry(CpNav::$plugin->getNavSources()->getTree())[$key] ?? null;
    }

    private function _indexRegistry(array $registryTree): array
    {
        $index = [];

        $walk = function(array $nodes) use (&$index, &$walk): void {
            foreach ($nodes as $node) {
                $index[$node->key] = $node;

                if ($node->children) {
                    $walk($node->children);
                }
            }
        };

        $walk($registryTree);

        return $index;
    }

    private function _builderIdForKey(string $key): int
    {
        return (int)sprintf('%u', crc32($key));
    }

    private function _typeForKey(string $key, ResolvedNavNode $node): string
    {
        if (str_starts_with($key, NodeKey::NS_MANUAL . ':')) {
            return LayoutNavItem::TYPE_MANUAL;
        }

        if (str_starts_with($key, NodeKey::NS_DIVIDER . ':')) {
            return LayoutNavItem::TYPE_DIVIDER;
        }

        if (str_starts_with($key, NodeKey::NS_PLUGIN . ':')) {
            return LayoutNavItem::TYPE_PLUGIN;
        }

        return LayoutNavItem::TYPE_CRAFT;
    }

    private function _handleForKey(string $key): string
    {
        if (NodeKey::isCustomizationOnly($key)) {
            return substr($key, strpos($key, ':') + 1);
        }

        [, $path] = NodeKey::split($key);
        $parts = explode('/', $path);

        return $parts[0] ?? $path;
    }

    private function _uidFromKey(string $key): ?string
    {
        if (!NodeKey::isCustomizationOnly($key)) {
            return null;
        }

        return substr($key, strpos($key, ':') + 1);
    }
}
