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
use verbb\cpnav\records\Navigation as NavigationRecord;
use verbb\cpnav\models\Navigation as NavigationModel;

use Craft;
use craft\base\Component;
use craft\db\Query;

/**
 * @author    Verbb
 * @package   CpNav
 * @since     2
 */
class Navigation extends Component
{
    // Public Methods
    // =========================================================================

    public function getAllManual($layoutId, $indexBy = null)
    {
//        $records = CpNav_NavRecord::model()->ordered()->findAll(array('condition' => 'layoutId = '.$layoutId.' AND (manualNav IS NULL OR manualNav <> 1)'));
//        return CpNav_NavModel::populateModels($records, $indexBy);

        return (new Query())
            ->select(['*'])
            ->from(['{{%cpnav_navigation}}'])
            ->where(['layoutId' => $layoutId])
            ->andFilterWhere([
                'or',
                ['is', 'manualNav', null],
                ['<>', 'manualNav', '1'],
            ])
            ->indexBy($indexBy)
            ->all();
    }

    public function getByLayoutId($layoutId, $indexBy = null)
    {
//        $records = CpNav_NavRecord::model()->ordered()->findAllByAttributes(array('layoutId' => $layoutId));
//        return CpNav_NavModel::populateModels($records, $indexBy);

        return (new Query())
            ->select(['*'])
            ->from(['{{%cpnav_navigation}}'])
            ->where(['layoutId' => $layoutId])
            ->indexBy($indexBy)
            ->all();
    }

    public function getById($id)
    {
//        $record = CpNav_NavRecord::model()->findById($id);
//
//        if ($record) {
//            return CpNav_NavModel::populateModel($record);
//        }

        return (new Query())
            ->select(['*'])
            ->from(['{{%cpnav_navigation}}'])
            ->where(['id' => $id])
            ->one();
    }

    public function getByHandle($layoutId, $handle)
    {
//        $record = CpNav_NavRecord::model()->findByAttributes(array('handle' => $handle, 'layoutId' => $layoutId));
//
//        if ($record) {
//            return CpNav_NavModel::populateModel($record);
//        }

        return (new Query())
            ->select(['*'])
            ->from(['{{%cpnav_navigation}}'])
            ->where(['layoutId' => $layoutId])
            ->where(['handle' => $handle])
            ->one();
    }


    public function save(NavigationModel $model)
    {
//        if ($model->id) {
//            $record = CpNav_NavRecord::model()->findById($model->id);
//        } else {
//            $record = new CpNav_NavRecord();
//        }

        $record = NavigationRecord::findOne(['id' => $model->id]);

        if (!$record) {
            $record = new NavigationRecord();
        }

        $record->layoutId = $model->layoutId;
        $record->handle = $model->handle;
        $record->currLabel = $model->currLabel;
        $record->prevLabel = $model->prevLabel;
        $record->enabled = $model->enabled;
        $record->order = $model->order;
        $record->url = $model->url;
        $record->prevUrl = $model->prevUrl;
        $record->icon = $model->icon;
        $record->customIcon = $model->customIcon;
        $record->manualNav = $model->manualNav;
        $record->newWindow = $model->newWindow;

        $record->validate();
        $model->addErrors($record->getErrors());

//        if (!$model->hasErrors()) {
//            $record->save(false);
//
//            if (!$model->id) {
//                $model->id = $record->id;
//            }
//        }

        if ($model->hasErrors()) {
            return false;
        }

        if ($record->isNewRecord) {
            $record->save();
        } else {
            $record->update();
        }

        return $model;
    }

    public function delete(NavigationModel $modal)
    {
//        $navRecord = CpNav_NavRecord::model()->findById($modal->id);
//
//        $navRecord->delete();

        $record = NavigationRecord::findOne(['id' => $modal->id]);

        if ($record) {
            return $record->delete();
        }
    }
}
