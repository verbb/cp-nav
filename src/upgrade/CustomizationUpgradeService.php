<?php
namespace verbb\cpnav\upgrade;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout;
use verbb\cpnav\nav\sources\NodeKey;

/**
 * v5 navigation rows → customization project config.
 */
final class CustomizationUpgradeService
{
    // Properties
    // =========================================================================

    private readonly CustomizationUpgrader $migrator;


    // Public Methods
    // =========================================================================

    public function __construct(
        CustomizationUpgrader $migrator = new CustomizationUpgrader(),
    ) {
        $this->migrator = $migrator;
    }

    public function upgradeLayouts(?string $layoutUid = null, bool $dryRun = false): array
    {
        $results = [];

        foreach ($this->_layouts($layoutUid) as $layout) {
            $navigations = (new LegacyNavigationReader())->getLayoutNavItemsForLayout((int)$layout->id);
            $customizations = $this->migrator->upgradeNavigations($navigations);

            if (!$dryRun) {
                CpNav::$plugin->getNavCustomization()->setCustomizationNodes($layout->uid, $customizations);
            }

            $results[] = [
                'layoutUid' => $layout->uid,
                'layoutName' => (string)$layout->name,
                'nodeCount' => count($customizations),
            ];
        }

        return $results;
    }

    public function auditLayouts(?string $layoutUid = null): array
    {
        $sourceKeys = $this->_sourceKeys();
        $results = [];

        foreach ($this->_layouts($layoutUid) as $layout) {
            $customizations = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
            $customizationKeys = array_keys($customizations);

            $stale = [];
            foreach ($customizationKeys as $key) {
                if (NodeKey::isCustomizationOnly($key)) {
                    continue;
                }

                if (!in_array($key, $sourceKeys, true)) {
                    $stale[] = $key;
                }
            }

            $missing = array_values(array_diff($sourceKeys, $customizationKeys));

            $results[] = [
                'layoutUid' => $layout->uid,
                'layoutName' => (string)$layout->name,
                'stale' => $stale,
                'missing' => $missing,
            ];
        }

        return $results;
    }

    /**
     * @param string[] $staleKeys
     * @return string[]
     */
    public function purgeStaleKeys(string $layoutUid, array $staleKeys): array
    {
        return CpNav::$plugin->getNavCustomization()->removeStaleNodes($layoutUid, $staleKeys);
    }


    // Private Methods
    // =========================================================================

    private function _layouts(?string $layoutUid): array
    {
        if ($layoutUid) {
            $layout = CpNav::$plugin->getLayouts()->getLayoutByUid($layoutUid);
            return $layout ? [$layout] : [];
        }

        return CpNav::$plugin->getLayouts()->getAllLayouts();
    }

    private function _sourceKeys(): array
    {
        $tree = CpNav::$plugin->getNavSources()->getTree();
        $keys = [];

        $walk = function(array $nodes) use (&$keys, &$walk): void {
            foreach ($nodes as $node) {
                $keys[] = $node->key;
                if ($node->children) {
                    $walk($node->children);
                }
            }
        };

        $walk($tree);

        return $keys;
    }
}
