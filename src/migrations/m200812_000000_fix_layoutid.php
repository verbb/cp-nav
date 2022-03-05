<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\CpNav;
use verbb\cpnav\services\NavigationsService;

use Craft;
use craft\db\Migration;
use craft\helpers\ArrayHelper;

class m200812_000000_fix_layoutid extends Migration
{
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.6', '>=')) {
            return true;
        }

        // Remove `layoutId` and add `layout` for its UID
        $navs = Craft::$app->getProjectConfig()->get(NavigationsService::CONFIG_NAVIGATION_KEY);

        if (is_array($navs)) {
            foreach ($navs as $navUid => $nav) {
                $layoutId = ArrayHelper::remove($nav, 'layoutId');

                if (!$layoutId) {
                    continue;
                }

                $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

                if (!$layout) {
                    continue;
                }

                $nav['layout'] = $layout->uid;

                Craft::$app->getProjectConfig()->set(NavigationsService::CONFIG_NAVIGATION_KEY . '.' . $navUid, $nav);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200812_000000_fix_layoutid cannot be reverted.\n";
        return false;
    }
}

