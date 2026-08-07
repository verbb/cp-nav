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
        $this->insertDefaultData();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropProjectConfig();
        $this->removeTables();

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists('{{%cpnav_layout}}');
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
    }

    public function insertDefaultData(): void
    {
        // Don't make the same config changes twice
        $installed = (Craft::$app->getProjectConfig()->get('plugins.cp-nav', true) !== null);
        $configExists = (Craft::$app->getProjectConfig()->get('cp-nav', true) !== null);

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
        $this->dropTableIfExists('{{%cpnav_layout}}');
    }

    public function dropProjectConfig(): void
    {
        Craft::$app->getProjectConfig()->remove('cp-nav');
    }
}
