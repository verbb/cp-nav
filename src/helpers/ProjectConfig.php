<?php
namespace verbb\cpnav\helpers;

use verbb\cpnav\services\LayoutsService;

use Craft;

class ProjectConfig
{
    // Properties
    // =========================================================================

    private static $_processedLayouts = false;


    // Public Methods
    // =========================================================================

    public static function ensureAllLayoutsProcessed()
    {
        if (static::$_processedLayouts) {
            return;
        }
        static::$_processedLayouts = true;

        $projectConfig = Craft::$app->getProjectConfig();
        $allLayouts = $projectConfig->get(LayoutsService::CONFIG_LAYOUT_KEY, true) ?? [];

        foreach ($allLayouts as $layoutUid => $layoutData) {
            $projectConfig->processConfigChanges(LayoutsService::CONFIG_LAYOUT_KEY . '.' . $layoutUid);
        }
    }
}
