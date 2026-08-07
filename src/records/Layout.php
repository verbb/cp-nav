<?php
namespace verbb\cpnav\records;

use craft\db\ActiveRecord;

class Layout extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%cpnav_layout}}';
    }
}
