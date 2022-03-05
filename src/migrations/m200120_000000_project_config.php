<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\services\LayoutsService;
use verbb\cpnav\services\NavigationsService;

use Craft;
use craft\db\Migration;
use craft\db\Query;

class m200120_000000_project_config extends Migration
{
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.4', '>=')) {
            return true;
        }

        $this->_migrateLayouts();
        $this->_migrateNavigations();

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200120_000000_project_config cannot be reverted.\n";
        return false;
    }

    // Private methods
    // =========================================================================

    private function _migrateLayouts(): void
    {
        $layoutRows = (new Query())
            ->select([
                'name',
                'isDefault',
                'permissions',
                'uid',
            ])
            ->from(['{{%cpnav_layout}}'])
            ->indexBy('uid')
            ->all();

        foreach ($layoutRows as $key => $row) {
            unset($layoutRows[$key]['uid']);
        }

        Craft::$app->getProjectConfig()->set(LayoutsService::CONFIG_LAYOUT_KEY, $layoutRows);
    }

    private function _migrateNavigations(): void
    {
        $navigationRows = (new Query())
            ->select([
                'layoutId',
                'handle',
                'prevLabel',
                'currLabel',
                'enabled',
                'order',
                'prevUrl',
                'url',
                'icon',
                'customIcon',
                'type',
                'newWindow',
                'uid',
            ])
            ->from(['{{%cpnav_navigation}}'])
            ->indexBy('uid')
            ->all();

        foreach ($navigationRows as $key => $row) {
            unset($navigationRows[$key]['uid']);
        }

        Craft::$app->getProjectConfig()->set(NavigationsService::CONFIG_NAVIGATION_KEY, $navigationRows);
    }
}

