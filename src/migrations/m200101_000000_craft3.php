<?php
namespace verbb\cpnav\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

class m200101_000000_craft3 extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%cpnav_layouts}}') && !$this->db->tableExists('{{%cpnav_layout}}')) {
            MigrationHelper::renameTable('{{%cpnav_layouts}}', '{{%cpnav_layout}}', $this);
        }

        if ($this->db->tableExists('{{%cpnav_navs}}') && !$this->db->tableExists('{{%cpnav_navigation}}')) {
            MigrationHelper::renameTable('{{%cpnav_navs}}', '{{%cpnav_navigation}}', $this);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200101_000000_craft3 cannot be reverted.\n";
        return false;
    }
}

