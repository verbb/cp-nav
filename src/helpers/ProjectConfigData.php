<?php
namespace verbb\cpnav\helpers;

use verbb\cpnav\CpNav;

use Craft;
use craft\models\Structure;

class ProjectConfigData
{
    // Static Methods
    // =========================================================================

    public static function rebuildProjectConfig(): array
    {
        $configData = [];

        $configData['layouts'] = self::_getLayoutsData();
        $configData['navigations'] = self::_getNavigationsData();

        return array_filter($configData);
    }

    
    // Private Methods
    // =========================================================================

    private static function _getLayoutsData(): array
    {
        $data = [];

        foreach (CpNav::$plugin->getLayouts()->getAllLayouts() as $layout) {
            $data[$layout->uid] = $layout->getConfig();
        }

        return $data;
    }

    private static function _getNavigationsData(): array
    {
        $data = [];

        foreach (CpNav::$plugin->getNavigations()->getAllNavigations() as $navigation) {
            $data[$navigation->uid] = $navigation->getConfig();
        }

        return $data;
    }
}