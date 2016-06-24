<?php
namespace Craft;

class m160625_000000_cpNav_addCustomIconSupport extends BaseMigration
{
    public function safeUp()
    {
        $navsTable = $this->dbConnection->schema->getTable('{{cpnav_navs}}');

        if ($navsTable->getColumn('customIcon') === null) {
            $this->addColumnAfter('cpnav_navs', 'customIcon', array('column' => ColumnType::Text), 'icon');
        }

        return true;
    }
}
