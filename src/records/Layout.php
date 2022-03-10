<?php
namespace verbb\cpnav\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

class Layout extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%cpnav_layout}}';
    }


    // Public Methods
    // =========================================================================

    public function getNavigations(): ActiveQuery
    {
        return $this->hasMany(Navigation::class, ['navId' => 'id'])->inverseOf('layout');
    }
}
