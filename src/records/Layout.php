<?php
namespace verbb\cpnav\records;

use craft\db\ActiveRecord;

use yii\db\ActiveQueryInterface;

class Layout extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%cpnav_layout}}';
    }

    public function getNavigations(): ActiveQueryInterface
    {
        return $this->hasMany(Navigation::className(), ['navId' => 'id'])->inverseOf('layout');
    }
}
