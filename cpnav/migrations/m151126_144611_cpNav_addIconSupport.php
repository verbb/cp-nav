<?php
namespace Craft;

class m151126_144611_cpNav_addIconSupport extends BaseMigration
{
    public function safeUp()
    {
        // Add Layout Permissions Support
        $layoutsTable = $this->dbConnection->schema->getTable('{{cpnav_layouts}}');

        if ($layoutsTable->getColumn('permissions') === null) {
            $this->addColumnAfter('cpnav_layouts', 'permissions', array('column' => ColumnType::Text), 'isDefault');
        }

        $navsTable = $this->dbConnection->schema->getTable('{{cpnav_navs}}');

        if ($navsTable->getColumn('icon') === null) {
            $this->addColumnAfter('cpnav_navs', 'icon', array('column' => ColumnType::Varchar), 'url');
        }

        return true;
    }
}
