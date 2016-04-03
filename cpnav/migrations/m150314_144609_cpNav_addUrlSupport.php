<?php
namespace Craft;

class m150314_144609_cpNav_addUrlSupport extends BaseMigration
{
    public function safeUp()
    {
        // We should be including the Url!
        craft()->db->createCommand()->addColumnAfter('cpnav', 'url', ColumnType::Varchar, 'order');


        return true;
    }
}
