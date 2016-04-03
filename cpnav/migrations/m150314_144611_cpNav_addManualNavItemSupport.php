<?php
namespace Craft;

class m150314_144611_cpNav_addManualNavItemSupport extends BaseMigration
{
    public function safeUp()
    {
        // We should be including the Url!
        craft()->db->createCommand()->addColumnAfter('cpnav', 'manualNav', ColumnType::TinyInt, 'url');


        return true;
    }
}
