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

class m200812_100000_layout_sortorder extends Migration
{
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%cpnav_layout}}', 'sortOrder')) {
            $this->addColumn('{{%cpnav_layout}}', 'sortOrder', $this->smallInteger()->unsigned()->after('permissions'));
        }

        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.7', '>=')) {
            return;
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

    public function safeDown()
    {
        echo "m200812_100000_layout_sortorder cannot be reverted.\n";
        return false;
    }
}

