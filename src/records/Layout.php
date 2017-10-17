<?php
/**
 * CP Nav plugin for Craft CMS 3.x
 *
 * Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation.
 *
 * @link      http://verbb.io
 * @copyright Copyright (c) 2017 Verbb
 */

namespace verbb\cpnav\records;

use verbb\cpnav\CpNav;

use Craft;
use craft\db\ActiveRecord;

use \yii\db\ActiveQuery;

/**
 * @author    Verbb
 * @package   CpNav
 * @since     2
 *
 * @property int    $id
 * @property string $name
 * @property bool   $isDefault
 * @property mixed  $permission
 */
class Layout extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * Declares the name of the database table associated with this AR class.
     * By default this method returns the class name as the table name by calling [[Inflector::camel2id()]]
     * with prefix [[Connection::tablePrefix]]. For example if [[Connection::tablePrefix]] is `tbl_`,
     * `Customer` becomes `tbl_customer`, and `OrderItem` becomes `tbl_order_item`. You may override this method
     * if the table is not named after this convention.
     *
     * By convention, tables created by plugins should be prefixed with the plugin
     * name and an underscore.
     *
     * @return string the table name
     */
    public static function tableName(): string
    {
        return '{{%cpnav_layout}}';
    }

//    old
//    public function defineRelations()
//    {
//        return array(
//            'nav' => array(static::HAS_MANY, 'CpNav_NavRecord', 'navId'),
//        );
//    }

    // new
    /**
     * @return \yii\db\ActiveQuery;
     */
    public function getNavigations(): ActiveQuery
    {
        return $this->hasMany(Navigation::className(), ['navId' => 'id'])->inverseOf('layout');
    }
}
