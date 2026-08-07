<?php
namespace verbb\cpnav\nav\builder;

use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\CustomIcon;
use verbb\cpnav\models\LayoutNavItem;
use verbb\cpnav\nav\sources\NodeKey;

use Craft;
use craft\helpers\Json;
use craft\base\Component;

/**
 * JSON payloads for the React nav builder API.
 */
class NavBuilderApi extends Component
{
    // Public Methods
    // =========================================================================

    public function getLayoutTree(int $layoutId): array
    {
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return [];
        }

        $navigations = CpNav::$plugin->getNavBuilder()->getLayoutNavItemsForLayout($layoutId);
        $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        $registryKeys = $this->_registryKeys();
        $acknowledgedKeys = CpNav::$plugin->getNavCustomization()->getAcknowledgedRegistryKeys($layout->uid);

        // Only surface items that appeared in the registry since the admin last acknowledged — not
        // every stock Craft item on a fresh/reset layout.
        $newItemKeys = $acknowledgedKeys === null
            ? []
            : array_values(array_filter(
                array_diff($registryKeys, $acknowledgedKeys),
                fn(string $key) => !NodeKey::isCustomizationOnly($key),
            ));

        // Build flat list first so canIndent / canOutdent see the full sibling context.
        $flatNodes = [];
        foreach ($navigations as $navigation) {
            if ($navigation->parentId) {
                continue;
            }

            $flatNodes[] = [
                'builderId' => (int)$navigation->id,
                'key' => (string)$navigation->nodeKey,
                'parentId' => null,
                'level' => 1,
            ];

            foreach ($navigation->getChildren() as $child) {
                $flatNodes[] = [
                    'builderId' => (int)$child->id,
                    'key' => (string)$child->nodeKey,
                    'parentId' => (int)$navigation->id,
                    'level' => 2,
                ];
            }
        }

        $nodes = [];

        foreach ($navigations as $navigation) {
            // Resolved list is flat; children are nested on parents — skip duplicates.
            if ($navigation->parentId) {
                continue;
            }

            $nodes[] = $this->_serializeNode($navigation, $overlay, $newItemKeys, $flatNodes);

            foreach ($navigation->getChildren() as $child) {
                $nodes[] = $this->_serializeNode($child, $overlay, $newItemKeys, $flatNodes);
            }
        }

        return [
            'layout' => [
                'id' => $layout->id,
                'uid' => $layout->uid,
                'name' => $layout->name,
                'maxLevels' => NavTreeReparent::MAX_DEPTH,
            ],
            'nodes' => $nodes,
            'meta' => [
                'newItemCount' => count($newItemKeys),
                'maxDepth' => NavTreeReparent::MAX_DEPTH,
                'assetSources' => $this->_assetSources(),
            ],
        ];
    }

    public function updateNode(int $layoutId, string $nodeKey, array $data): bool
    {
        $navigation = $this->_navigationByKey($layoutId, $nodeKey);

        // Disabled items were previously omitted from the resolved builder tree; fall back to
        // overlay + registry so toggles still work for keys already in project config.
        if (!$navigation) {
            $navigation = CpNav::$plugin->getNavBuilder()->getLayoutNavItemByKey($layoutId, $nodeKey);
        }

        if (!$navigation) {
            return false;
        }

        if (array_key_exists('enabled', $data)) {
            $navigation->enabled = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('currLabel', $data)) {
            $navigation->currLabel = (string)$data['currLabel'];
        }

        if (array_key_exists('url', $data)) {
            $navigation->url = (string)$data['url'];
        }

        if (array_key_exists('newWindow', $data)) {
            $navigation->newWindow = (bool)$data['newWindow'];
        }

        if (array_key_exists('icon', $data)) {
            $navigation->icon = $this->_normalizeIcon($data['icon']);
        }

        if (array_key_exists('customIcon', $data)) {
            $navigation->customIcon = $this->_normalizeCustomIcon($data['customIcon']);
        }

        return CpNav::$plugin->getNavBuilder()->saveLayoutNavItem($navigation);
    }

    public function createNode(int $layoutId, array $data): ?array
    {
        $navigation = new LayoutNavItem();
        $navigation->layoutId = $layoutId;
        $navigation->type = (string)($data['type'] ?? LayoutNavItem::TYPE_MANUAL);
        $navigation->currLabel = (string)($data['currLabel'] ?? '');
        $navigation->prevLabel = $navigation->currLabel;
        $navigation->url = (string)($data['url'] ?? '');
        $navigation->prevUrl = $navigation->url;
        $navigation->enabled = true;
        $navigation->level = 1;
        $navigation->newWindow = (bool)($data['newWindow'] ?? false);
        $navigation->icon = $data['icon'] ?? null;

        if (!CpNav::$plugin->getNavBuilder()->createLayoutNavItem($navigation)) {
            return null;
        }

        return $this->_serializeNode($navigation, [], []);
    }

    public function deleteNode(int $layoutId, string $nodeKey): bool
    {
        $navigation = $this->_navigationByKey($layoutId, $nodeKey);

        if (!$navigation) {
            return false;
        }

        return CpNav::$plugin->getNavBuilder()->deleteLayoutNavItem($layoutId, (int)$navigation->id);
    }

    public function reorderNodes(int $layoutId, array $items): bool
    {
        return CpNav::$plugin->getNavBuilder()->reorder($layoutId, $items);
    }

    public function indentNode(int $layoutId, string $nodeKey): bool
    {
        return CpNav::$plugin->getNavBuilder()->indentNode($layoutId, $nodeKey);
    }

    public function outdentNode(int $layoutId, string $nodeKey): bool
    {
        return CpNav::$plugin->getNavBuilder()->outdentNode($layoutId, $nodeKey);
    }

    /**
     * @param string|null $parentKey Canonical parent key, or null for top-level.
     */
    public function reparentNode(int $layoutId, string $nodeKey, ?string $parentKey): bool
    {
        return CpNav::$plugin->getNavBuilder()->reparentNode($layoutId, $nodeKey, $parentKey);
    }

    public function resetLayout(int $layoutId): void
    {
        CpNav::$plugin->getNavBuilder()->resetLayout($layoutId);
    }

    public function refreshSources(): void
    {
        CpNav::$plugin->getNavSources()->invalidate();
    }

    public function acknowledgeNewItems(int $layoutId): void
    {
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return;
        }

        CpNav::$plugin->getNavCustomization()->acknowledgeCurrentRegistry($layout->uid);
    }


    // Private Methods
    // =========================================================================

    private function _serializeNode(LayoutNavItem $navigation, array $overlay, array $newItemKeys, array $flatNodes = []): array
    {
        $key = $navigation->nodeKey;
        [$typeLabel, $typeClass, $typeColorRgb, $typeTextColorRgb] = $this->_typeMeta((string)$navigation->type);

        return [
            'builderId' => $navigation->id,
            'key' => $key,
            'title' => $navigation->currLabel,
            'label' => $navigation->currLabel,
            'defaultLabel' => $navigation->prevLabel,
            'url' => $navigation->url,
            'type' => $navigation->type,
            'typeLabel' => $typeLabel,
            'typeClass' => $typeClass,
            'typeColorRgb' => $typeColorRgb,
            'typeTextColorRgb' => $typeTextColorRgb,
            'enabled' => (bool)$navigation->enabled,
            'level' => (int)$navigation->level,
            'parentId' => $navigation->parentId,
            'parentKey' => $navigation->getParent()?->nodeKey,
            'sort' => (int)$navigation->sortOrder,
            'newWindow' => (bool)$navigation->newWindow,
            'icon' => $navigation->icon,
            'customIcon' => $this->_customIconId($navigation->customIcon),
            'customIconAsset' => $this->_customIconAsset($navigation->customIcon),
            'isCustomized' => $key && isset($overlay[$key]),
            'isNew' => $key && in_array($key, $newItemKeys, true),
            'isOrphan' => (bool)$navigation->isOrphan,
            'hasDescendants' => count($navigation->getChildren()) > 0,
            'canIndent' => $key ? NavTreeReparent::canIndent($flatNodes, $key) : false,
            'canOutdent' => $key ? NavTreeReparent::canOutdent($flatNodes, $key) : false,
            'deletable' => $navigation->isManual() || $navigation->isDivider(),
        ];
    }

    /**
     * Badge metadata per node type: [label, css class, base RGB, text RGB].
     * Colours are hard-coded (Navigation-style) so builder badges stay stable.
     */
    private function _typeMeta(string $type): array
    {
        return match ($type) {
            LayoutNavItem::TYPE_PLUGIN => [Craft::t('cp-nav', 'Plugin'), 'cpnav-type-plugin', '124, 58, 237', '124, 58, 237'],
            LayoutNavItem::TYPE_MANUAL => [Craft::t('cp-nav', 'Manual'), 'cpnav-type-manual', '13, 148, 136', '13, 148, 136'],
            LayoutNavItem::TYPE_DIVIDER => [Craft::t('cp-nav', 'Divider'), 'cpnav-type-divider', '107, 114, 128', '107, 114, 128'],
            default => [Craft::t('cp-nav', 'Craft'), 'cpnav-type-craft', '37, 99, 235', '37, 99, 235'],
        };
    }

    /**
     * Store Craft system SVG names / paths as-is. Empty clears the override (registry default).
     * Builder UI does not edit `icon` (Custom Icon only); this remains for migrate/API/registry merge.
     */
    private function _normalizeIcon(mixed $icon): ?string
    {
        if ($icon === null || $icon === '') {
            return null;
        }

        $icon = trim((string)$icon);

        if ($icon === '') {
            return null;
        }

        if ($icon === 'title') {
            return 'title';
        }

        return $icon;
    }

    /**
     * Accept asset id, id list, or JSON string; store as `[id]` JSON or null when cleared.
     */
    private function _normalizeCustomIcon(mixed $customIcon): ?string
    {
        if ($customIcon === null || $customIcon === '' || $customIcon === []) {
            return null;
        }

        if (is_string($customIcon) && str_starts_with(trim($customIcon), '[')) {
            $decoded = Json::decode($customIcon);
            $ids = is_array($decoded) ? $decoded : [];
        } elseif (is_array($customIcon)) {
            $ids = $customIcon;
        } else {
            $ids = [$customIcon];
        }

        $ids = array_values(array_filter(array_map('intval', $ids)));

        return $ids === [] ? null : Json::encode($ids);
    }

    private function _customIconId(?string $customIcon): ?int
    {
        return CustomIcon::assetIdFromStored($customIcon);
    }

    private function _customIconAsset(?string $customIcon): ?array
    {
        return CustomIcon::serializeForBuilder($customIcon);
    }

    /**
     * @return string[]
     */
    private function _assetSources(): array
    {
        $sources = [];

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $sources[] = 'volume:' . $volume->uid;
        }

        return $sources;
    }

    private function _navigationByKey(int $layoutId, string $nodeKey): ?LayoutNavItem
    {
        foreach (CpNav::$plugin->getNavBuilder()->getLayoutNavItemsForLayout($layoutId) as $navigation) {
            if ($navigation->nodeKey === $nodeKey) {
                return $navigation;
            }

            foreach ($navigation->getChildren() as $child) {
                if ($child->nodeKey === $nodeKey) {
                    return $child;
                }
            }
        }

        return null;
    }

    private function _registryKeys(): array
    {
        $keys = [];

        $walk = function(array $nodes) use (&$keys, &$walk): void {
            foreach ($nodes as $node) {
                $keys[] = $node->key;

                if ($node->children) {
                    $walk($node->children);
                }
            }
        };

        $walk(CpNav::$plugin->getNavSources()->getTree());

        return $keys;
    }
}
