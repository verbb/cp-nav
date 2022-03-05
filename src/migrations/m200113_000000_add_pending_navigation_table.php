<?php
namespace verbb\cpnav\migrations;

use craft\db\Migration;

class m200113_000000_add_pending_navigation_table extends Migration
{
    public function safeUp(): bool
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

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200113_000000_add_pending_navigation_table cannot be reverted.\n";
        return false;
    }
}

