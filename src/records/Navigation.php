<?php
namespace verbb\cpnav\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

class Navigation extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%cpnav_navigation}}';
    }


    // Public Methods
    // =========================================================================

    public function getLayout(): ActiveQuery
    {
        return $this->hasOne(Layout::class, ['id' => 'navId']);
    }
}
