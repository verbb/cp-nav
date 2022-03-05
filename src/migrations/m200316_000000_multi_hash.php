<?php
namespace verbb\cpnav\migrations;

use Craft;
use craft\db\Migration;

class m200316_000000_multi_hash extends Migration
{
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.5', '>=')) {
            return true;
        }

        $currentUser = Craft::$app->getUser()->getIdentity();
        $originalNavHash = Craft::$app->getProjectConfig()->get('plugins.cp-nav.settings.originalNavHash') ?? '';

        if ($currentUser) {
            Craft::$app->getProjectConfig()->set('plugins.cp-nav.settings.originalNavHash', [$currentUser->uid => $originalNavHash]);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200316_000000_multi_hash cannot be reverted.\n";
        return false;
    }
}

