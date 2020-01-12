<?php
namespace verbb\cpnav\records;

use craft\db\ActiveRecord;

use yii\db\ActiveQuery;

class Navigation extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%cpnav_navigation}}';
    }

    public function getLayout(): ActiveQuery
    {
        return $this->hasOne(Layout::className(), ['id' => 'navId']);
    }
}
