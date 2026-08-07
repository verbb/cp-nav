<?php
namespace verbb\cpnav\helpers;

use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationSchema;
use verbb\cpnav\nav\sources\NodeKey;

class ProjectConfigData
{
    // Static Methods
    // =========================================================================

    public static function rebuildProjectConfig(): array
    {
        return [
            'schemaVersion' => CustomizationSchema::SCHEMA_VERSION,
            'layouts' => self::_getLayoutsData(),
        ];
    }


    // Private Methods
    // =========================================================================

    private static function _getLayoutsData(): array
    {
        $data = [];
        $navCustomization = CpNav::$plugin->getNavCustomization();

        foreach (CpNav::$plugin->getLayouts()->getAllLayouts() as $layout) {
            $layoutData = $layout->getConfig();
            $overlayNodes = $navCustomization->getCustomizationForLayout($layout->uid);
            $acknowledged = $navCustomization->getAcknowledgedRegistryKeys($layout->uid);
            $customizations = [];

            if ($overlayNodes !== []) {
                $nodes = [];

                foreach ($overlayNodes as $node) {
                    $nodes[NodeKey::encodePathKey($node->key)] = $node->toConfig();
                }

                $customizations[CustomizationSchema::NODES_KEY] = $nodes;
            }

            if ($acknowledged !== null) {
                $customizations[CustomizationSchema::ACKNOWLEDGED_REGISTRY_KEYS_KEY] = $acknowledged;
            }

            if ($customizations !== []) {
                $layoutData[CustomizationSchema::CUSTOMIZATIONS_KEY] = $customizations;
            }

            $data[$layout->uid] = $layoutData;
        }

        return $data;
    }
}
