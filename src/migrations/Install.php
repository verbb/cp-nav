<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout;

use Craft;
use craft\db\Migration;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->createTables();
        $this->addForeignKeys();
        $this->insertDefaultData();

        return true;
    }

    public function safeDown(): bool
    {
        $this->removeTables();
        $this->dropProjectConfig();

        return true;
    }

    public function createTables(): void
    {
        $this->createTable('{{%cpnav_layout}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255),
            'isDefault' => $this->boolean()->defaultValue(false),
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
            'enabled' => $this->boolean()->defaultValue(true),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'prevLevel' => $this->smallInteger(),
            'level' => $this->smallInteger()->defaultValue(1),
            'prevParentId' => $this->integer(),
            'parentId' => $this->integer(),
            'prevUrl' => $this->string(255),
            'url' => $this->string(255),
            'icon' => $this->string(255),
            'customIcon' => $this->string(255),
            'type' => $this->string(),
            'newWindow' => $this->boolean()->defaultValue(false),
            'subnavBehaviour' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, '{{%cpnav_navigation}}', ['layoutId'], '{{%cpnav_layout}}', ['id'], 'CASCADE', null);
    }

    /**
     * Insert the default data.
     */
    public function insertDefaultData(): void
    {
        // Don't make the same config changes twice
        $installed = (Craft::$app->projectConfig->get('plugins.cp-nav', true) !== null);
        $configExists = (Craft::$app->projectConfig->get('cp-nav', true) !== null);

        if (!$installed && !$configExists) {
            $layout = new Layout([
                'name' => 'Default',
                'isDefault' => true,
            ]);

            CpNav::$plugin->getLayouts()->saveLayout($layout);
        }
    }

    public function removeTables(): void
    {
        $this->dropTableIfExists('{{%cpnav_navigation}}');
        $this->dropTableIfExists('{{%cpnav_layout}}');
    }

    public function dropProjectConfig(): void
    {
        Craft::$app->projectConfig->remove('cp-nav');
    }
}
