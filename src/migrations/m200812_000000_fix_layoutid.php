<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\CpNav;
use verbb\cpnav\services\LayoutsService;
use verbb\cpnav\services\NavigationsService;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\helpers\Component as ComponentHelper;
use craft\helpers\StringHelper;

class m200812_000000_fix_layoutid extends Migration
{
    public function safeUp()
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.6', '>=')) {
            return;
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

    public function safeDown()
    {
        echo "m200812_000000_fix_layoutid cannot be reverted.\n";
        return false;
    }
}

