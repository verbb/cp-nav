<?php
namespace verbb\cpnav\migrations;

use craft\db\Migration;
use craft\helpers\Db;

class m200101_000000_craft3 extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%cpnav_layouts}}') && !$this->db->tableExists('{{%cpnav_layout}}')) {
            Db::renameTable('{{%cpnav_layouts}}', '{{%cpnav_layout}}', $this);
        }

        if ($this->db->tableExists('{{%cpnav_navs}}') && !$this->db->tableExists('{{%cpnav_navigation}}')) {
            Db::renameTable('{{%cpnav_navs}}', '{{%cpnav_navigation}}', $this);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200101_000000_craft3 cannot be reverted.\n";
        return false;
    }
}

