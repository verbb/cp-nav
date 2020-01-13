<?php
namespace verbb\cpnav\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;
use craft\helpers\Component as ComponentHelper;
use craft\helpers\StringHelper;

class m200113_000000_add_pending_navigation_table extends Migration
{
    public function safeUp()
    {
        if (!$this->db->tableExists('{{%cpnav_pending_navigations}}')) {
            $this->createTable('{{%cpnav_pending_navigations}}', [
                'id' => $this->primaryKey(),
                'pluginNavItem' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }
    }

    public function safeDown()
    {
        echo "m200113_000000_add_pending_navigation_table cannot be reverted.\n";
        return false;
    }
}

