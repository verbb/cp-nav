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

        $layouts = (new Query())
            ->from('{{%cpnav_layout}}')
            ->all();

        $sortOrder = 0;

        foreach ($layouts as $layout) {
            $this->update('{{%cpnav_layout}}', ['sortOrder' => $sortOrder++], ['id' => $layout['id']]);
        }

        return true;
    }

    public function safeDown()
    {
        echo "m200812_100000_layout_sortorder cannot be reverted.\n";
        return false;
    }
}

