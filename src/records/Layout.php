<?php

namespace verbb\cpnav\records;

use craft\db\ActiveRecord;

use \yii\db\ActiveQuery;

/**
 * @property int       $id
 * @property string    $name
 * @property bool      $isDefault
 * @property string    $permissions
 * @property \DateTime $dateCreated
 * @property |DateTime $dateUpdated
 * @property string    $uid
 */
class Layout extends ActiveRecord
{

    // Public Static Methods
    // =========================================================================

    /**
     * Declares the name of the database table associated with this AR class.
     *
     * @return string the table name
     */
    public static function tableName(): string
    {
        return '{{%cpnav_layout}}';
    }

    /**
     * @return \yii\db\ActiveQuery;
     */
    public function getNavigations(): ActiveQuery
    {
        return $this->hasMany(Navigation::className(), ['navId' => 'id'])->inverseOf('layout');
    }
}
