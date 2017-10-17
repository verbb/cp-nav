<?php
/**
 * CP Nav plugin for Craft CMS 3.x
 *
 * Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation.
 *
 * @link      http://verbb.io
 * @copyright Copyright (c) 2017 Verbb
 */

namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\records\Layout as LayoutRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;

/**
 * @author    Verbb
 * @package   CpNav
 * @since     2
 */
class LayoutService extends Component
{
    // Public Methods
    // =========================================================================

    public function getAll($indexBy = null)
    {
//        $records = CpNav_LayoutRecord::model()->findAll();
//        return CpNav_LayoutModel::populateModels($records, $indexBy);

        return (new Query())
            ->select(['*'])
            ->from(['{{%cpnav_layout}}'])
            ->indexBy($indexBy)
            ->all();
    }

    public function getById($layoutId)
    {
//        $record = CpNav_LayoutRecord::model()->findById($layoutId);
//
//        if ($record) {
//            return CpNav_LayoutModel::populateModel($record);
//        }

        return (new Query())
            ->select(['*'])
            ->from(['{{%cpnav_layout}}'])
            ->where(['id' => $layoutId])
            ->one();
    }

    public function setDefaultLayout($layoutId)
    {
        $layout = new LayoutRecord();
        $layout->id = $layoutId;
        $layout->name = 'Default';
        $layout->isDefault = true;
        $layout->save();
    }
}
