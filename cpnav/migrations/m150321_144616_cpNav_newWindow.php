<?php
namespace Craft;

class m150321_144616_cpNav_newWindow extends BaseMigration
{
    public function safeUp()
    {
        craft()->db->createCommand()->addColumnAfter('cpnav_navs', 'newWindow', ColumnType::TinyInt, 'manualNav');

        return true;
    }
}

