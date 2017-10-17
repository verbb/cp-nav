<?php
/**
 * CP Nav plugin for Craft CMS 3.x
 *
 * Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation.
 *
 * @link      http://verbb.io
 * @copyright Copyright (c) 2017 Verbb
 */

namespace verbb\cpnav\migrations;

use verbb\cpnav\CpNav;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * cpnav Install Migration
 *
 * If your plugin needs to create any custom database tables when it gets installed,
 * create a migrations/ folder within your plugin folder, and save an Install.php file
 * within it using the following template:
 *
 * If you need to perform any additional actions on install/uninstall, override the
 * safeUp() and safeDown() methods.
 *
 * @author    Verbb
 * @package   CpNav
 * @since     2
 */
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
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
//            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
//            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
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
    protected function createTables()
    {
        $tablesCreated = false;

        // cpnav_layout table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%cpnav_layout}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%cpnav_layout}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                // Custom columns in the table
                    'name' => $this->string(255),
                    'isDefault' => $this->boolean()->notNull()->defaultValue(0),
                    'permissions' => $this->text(),
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
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                // Custom columns in the table
                    'layoutId' => $this->integer()->notNull(),
                    'handle' => $this->string(255),
                    'prevLabel' => $this->string(255),
                    'currLabel' => $this->string(255),
                    'enabled' => $this->boolean()->notNull()->defaultValue(1),
                    'order' => $this->integer()->defaultValue(0),
                    'prevUrl' => $this->string(255),
                    'url' => $this->string(255),
                    'icon' => $this->string(255),
                    'customIcon' => $this->string(255),
                    'manualNav' => $this->boolean()->notNull()->defaultValue(0),
                    'newWindow' => $this->boolean()->notNull()->defaultValue(0),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
//    protected function createIndexes()
//    {
//        // cpnav_layout table
//        $this->createIndex(
//            $this->db->getIndexName(
//                '{{%cpnav_layout}}',
//                'some_field',
//                true
//            ),
//            '{{%cpnav_layout}}',
//            'some_field',
//            true
//        );
//        // Additional commands depending on the db driver
//        switch ($this->driver) {
//            case DbConfig::DRIVER_MYSQL:
//                break;
//            case DbConfig::DRIVER_PGSQL:
//                break;
//        }
//
//        // cpnav_navigation table
//        $this->createIndex(
//            $this->db->getIndexName(
//                '{{%cpnav_navigation}}',
//                'some_field',
//                true
//            ),
//            '{{%cpnav_navigation}}',
//            'some_field',
//            true
//        );
//        // Additional commands depending on the db driver
//        switch ($this->driver) {
//            case DbConfig::DRIVER_MYSQL:
//                break;
//            case DbConfig::DRIVER_PGSQL:
//                break;
//        }
//    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        // cpnav_layout table
//        $this->addForeignKey(
//            $this->db->getForeignKeyName('{{%cpnav_layout}}', 'siteId'),
//            '{{%cpnav_layout}}',
//            'siteId',
//            '{{%sites}}',
//            'id',
//            'CASCADE',
//            'CASCADE'
//        );

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

//    /**
//     * Populates the DB with the default data.
//     *
//     * @return void
//     */
//    protected function insertDefaultData()
//    {
//    }

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
