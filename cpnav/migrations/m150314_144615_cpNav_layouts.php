<?php
namespace Craft;

class m150314_144615_cpNav_layouts extends BaseMigration
{
    public function safeUp()
    {
        // Create the Layouts table
        craft()->db->createCommand()->createTable('cpnav_layouts', array(
            'name'      => array('column' => ColumnType::Varchar),
            'isDefault' => array('column' => ColumnType::TinyInt),
        ), null, true);

        // Create default layout
        $layoutsRecord = new CpNav_LayoutRecord();

        $layoutsRecord->id = '1';
        $layoutsRecord->name = 'Default';
        $layoutsRecord->isDefault = '1';

        $layoutsRecord->save();



        // Rename the old table
        craft()->db->createCommand()->renameTable('cpnav', 'cpnav_navs');

        // Add LayoutId column to main table
        craft()->db->createCommand()->addColumnAfter('cpnav_navs', 'layoutId', ColumnType::Int, 'id');

        craft()->db->createCommand()->addForeignKey('cpnav_navs', 'layoutId', 'cpnav_layouts', 'id', 'SET NULL', null);

        // Populate each nav with the default layoutId for now
        craft()->db->createCommand()->update('cpnav_navs', array('layoutId' => '1'));






        return true;
    }
}

