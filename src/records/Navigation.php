<?php

namespace verbb\cpnav\records;

use craft\db\ActiveRecord;

use \yii\db\ActiveQuery;

/**
 * @property int       $id
 * @property int       $layoutId
 * @property string    $handle
 * @property string    $prevLabel
 * @property string    $currLabel
 * @property bool      $enabled
 * @property string    $order
 * @property string    $prevUrl
 * @property string    $url
 * @property string    $icon
 * @property string    $customIcon
 * @property bool      $manualNav
 * @property bool      $newWindow
 * @property string    $craftIcon
 * @property string    $pluginIcon
 * @property string    $parsedUrl
 * @property \DateTime $dateCreated
 * @property |DateTime $dateUpdated
 * @property string    $uid
 */
class Navigation extends ActiveRecord
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
        return '{{%cpnav_navigation}}';
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLayout(): ActiveQuery
    {
        return $this->hasOne(Layout::className(), ['id' => 'navId']);
    }
}
