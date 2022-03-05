<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\services\LayoutsService;

use Craft;
use craft\db\Migration;

class m200812_100000_layout_sortorder extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%cpnav_layout}}', 'sortOrder')) {
            $this->addColumn('{{%cpnav_layout}}', 'sortOrder', $this->smallInteger()->unsigned()->after('permissions'));
        }

        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.7', '>=')) {
            return true;
        }

        // Populate `sortOrder` 
        $layouts = Craft::$app->getProjectConfig()->get(LayoutsService::CONFIG_LAYOUT_KEY);

        $sortOrder = 0;

        if (is_array($layouts)) {
            foreach ($layouts as $layoutUid => $layout) {
                $layout['sortOrder'] = ++$sortOrder;

                Craft::$app->getProjectConfig()->set(LayoutsService::CONFIG_LAYOUT_KEY . '.' . $layoutUid, $layout);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200812_100000_layout_sortorder cannot be reverted.\n";
        return false;
    }
}

