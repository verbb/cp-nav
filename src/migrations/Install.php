<?php
namespace verbb\cpnav\migrations;

use Craft;
use craft\db\Migration;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp()
    {
        $this->createTables();
        $this->addForeignKeys();

        return true;
    }

    public function safeDown()
    {
        $this->removeTables();
        $this->dropProjectConfig();

        return true;
    }

    public function createTables()
    {
        $this->createTable('{{%cpnav_layout}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255),
            'isDefault' => $this->boolean()->notNull()->defaultValue(false),
            'permissions' => $this->text(),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%cpnav_navigation}}', [
            'id' => $this->primaryKey(),
            'layoutId' => $this->integer()->notNull(),
            'handle' => $this->string(255),
            'prevLabel' => $this->string(255),
            'currLabel' => $this->string(255),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'order' => $this->integer()->defaultValue(0),
            'prevUrl' => $this->string(255),
            'url' => $this->string(255),
            'icon' => $this->string(255),
            'customIcon' => $this->string(255),
            'type' => $this->string(),
            'newWindow' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        
        $this->createTable('{{%cpnav_pending_navigations}}', [
            'id' => $this->primaryKey(),
            'pluginNavItem' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function addForeignKeys()
    {
        $this->addForeignKey(null, '{{%cpnav_navigation}}', ['layoutId'], '{{%cpnav_layout}}', ['id'], 'CASCADE', null);
    }

    public function removeTables()
    {
        $this->dropTableIfExists('{{%cpnav_navigation}}');
        $this->dropTableIfExists('{{%cpnav_layout}}');
        $this->dropTableIfExists('{{%cpnav_pending_navigations}}');
    }

    public function dropProjectConfig()
    {
        Craft::$app->projectConfig->remove('cp-nav');
    }
}
