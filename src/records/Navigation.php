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
 * @property int $id
 * @property int $layoutId
 * @property string $handle
 * @property string $prevLabel
 * @property string $currLabel
 * @property bool $enabled
 * @property string $order
 * @property string $prevUrl
 * @property string $url
 * @property string $icon
 * @property string $customIcon
 * @property bool $manualNav
 * @property bool $newWindow
 * @property string $craftIcon
 * @property string $pluginIcon
 */
class Navigation extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
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
