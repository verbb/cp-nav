<?php
namespace verbb\cpnav\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

class m200119_000000_add_type extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%cpnav_navigation}}', 'manualNav')) {
            MigrationHelper::renameColumn('{{%cpnav_navigation}}', 'manualNav', 'type', $this);
            $this->alterColumn('{{%cpnav_navigation}}', 'type', $this->string());

            $this->update('{{%cpnav_navigation}}', ['type' => 'manual'], ['type' => '1'], [], false);
            $this->update('{{%cpnav_navigation}}', ['type' => ''], ['type' => null], [], false);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200119_000000_add_type cannot be reverted.\n";
        return false;
    }
}

