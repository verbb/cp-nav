<?php

namespace verbb\cpnav\migrations;

use Craft;
use craft\db\Migration;

class Install extends Migration
{

    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;


    // Public Methods
    // =========================================================================

    /**
     * @return bool
     */
    public function safeUp(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->addForeignKeys();

            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    /**
     * @return bool
     */
    public function safeDown(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }


    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    protected function createTables(): bool
    {
        $tablesCreated = false;

        // cpnav_layout table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%cpnav_layout}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%cpnav_layout}}',
                [
                    'id'          => $this->primaryKey(),

                    // Custom columns in the table
                    'name'        => $this->string(255),
                    'isDefault'   => $this->boolean()->notNull()->defaultValue(0),
                    'permissions' => $this->text(),

                    // Yii stuff
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid'         => $this->uid(),
                ]
            );
        }

        // cpnav_navigation table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%cpnav_navigation}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%cpnav_navigation}}',
                [
                    'id'          => $this->primaryKey(),

                    // Custom columns in the table
                    'layoutId'    => $this->integer()->notNull(),
                    'handle'      => $this->string(255),
                    'prevLabel'   => $this->string(255),
                    'currLabel'   => $this->string(255),
                    'enabled'     => $this->boolean()->notNull()->defaultValue(1),
                    'order'       => $this->integer()->defaultValue(0),
                    'prevUrl'     => $this->string(255),
                    'url'         => $this->string(255),
                    'icon'        => $this->string(255),
                    'customIcon'  => $this->string(255),
                    'manualNav'   => $this->boolean()->notNull()->defaultValue(0),
                    'newWindow'   => $this->boolean()->notNull()->defaultValue(0),

                    // Yii stuff
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid'         => $this->uid(),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        // cpnav_navigation table
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%cpnav_navigation}}', 'layoutId'),
            '{{%cpnav_navigation}}',
            'layoutId',
            '{{%cpnav_layout}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables()
    {
        // delete cpnav_navigation table first because of foreign key constraint
        $this->dropTableIfExists('{{%cpnav_navigation}}');

        // cpnavs_layout table
        $this->dropTableIfExists('{{%cpnav_layout}}');
    }
}
