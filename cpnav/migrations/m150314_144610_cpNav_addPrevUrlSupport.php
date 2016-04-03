<?php
namespace Craft;

class m150314_144610_cpNav_addPrevUrlSupport extends BaseMigration
{
    public function safeUp()
    {
        // We should be including the Url!
        craft()->db->createCommand()->addColumnAfter('cpnav', 'prevUrl', ColumnType::Varchar, 'order');


        return true;
    }
}
