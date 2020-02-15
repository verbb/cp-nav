<?php
namespace verbb\cpnav\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;
use craft\helpers\Component as ComponentHelper;
use craft\helpers\StringHelper;

class m200101_000000_craft3 extends Migration
{
    public function safeUp()
    {
        if ($this->db->tableExists('{{%cpnav_layouts}}') && !$this->db->tableExists('{{%cpnav_layout}}')) {
            MigrationHelper::renameTable('{{%cpnav_layouts}}', '{{%cpnav_layout}}', $this);
        }

        if ($this->db->tableExists('{{%cpnav_navs}}') && !$this->db->tableExists('{{%cpnav_navigation}}')) {
            MigrationHelper::renameTable('{{%cpnav_navs}}', '{{%cpnav_navigation}}', $this);
        }
    }

    public function safeDown()
    {
        echo "m200101_000000_craft3 cannot be reverted.\n";
        return false;
    }
}

