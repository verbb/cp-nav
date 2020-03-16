<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\services\LayoutsService;
use verbb\cpnav\services\NavigationsService;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\helpers\Component as ComponentHelper;
use craft\helpers\StringHelper;

class m200316_000000_multi_hash extends Migration
{
    public function safeUp()
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.5', '>=')) {
            return;
        }

        $currentUser = Craft::$app->getUser()->getIdentity();
        $originalNavHash = Craft::$app->getProjectConfig()->get('plugins.cp-nav.settings.originalNavHash') ?? '';

        if ($currentUser) {
            Craft::$app->getProjectConfig()->set('plugins.cp-nav.settings.originalNavHash', [$currentUser->uid => $originalNavHash]);
        }
    }

    public function safeDown()
    {
        echo "m200316_000000_multi_hash cannot be reverted.\n";
        return false;
    }
}

