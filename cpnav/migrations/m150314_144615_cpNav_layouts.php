<?php
namespace Craft;

class m150314_144615_cpNav_layouts extends BaseMigration
{
    public function safeUp()
    {
        craft()->db->createCommand()->createTable('cpnav_layouts', array(
            'name'      => array('column' => ColumnType::Varchar),
            'isDefault' => array('column' => ColumnType::TinyInt),
        ), null, true);

        // Create default record
        craft()->cpNav_layouts->createDefaultLayout();


        // Add LayoutId column to main table
    	craft()->db->createCommand()->addColumnAfter('cpnav', 'layoutId', ColumnType::Int, 'id');

        craft()->db->createCommand()->addForeignKey('cpnav', 'layoutId', 'cpnav_layouts', 'id', 'SET NULL', null);

        // Assign all current nav items to the default layout
        craft()->cpNav->assignToDefaultLayout();



        craft()->db->createCommand()->createTable('cpnav_users', array(

        ), null, true);

        craft()->db->createCommand()->addForeignKey('cpnav_users', 'layoutId', 'cpnav_layouts', 'id', 'SET NULL', null);
        craft()->db->createCommand()->addForeignKey('cpnav_users', 'userId', 'users', 'id', 'SET NULL', null);



        return true;
    }
}

