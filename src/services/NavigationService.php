<?php

namespace verbb\cpnav\services;

use verbb\cpnav\models\Navigation as NavigationModel;
use verbb\cpnav\records\Navigation as NavigationRecord;

use craft\base\Component;

class NavigationService extends Component
{

    // Public Methods
    // =========================================================================

    /**
     * @param integer $layoutId
     * @param null    $indexBy
     *
     * @return array
     */
    public function getAllManual($layoutId, $indexBy = null): array
    {
        $records = NavigationRecord::find()
            ->where(['layoutId' => $layoutId])
            ->andFilterWhere([
                'or',
                ['is', 'manualNav', null],
                ['<>', 'manualNav', '1'],
            ])
            ->indexBy($indexBy)
            ->orderBy(['order' => SORT_ASC])
            ->all();
        $models = [];

        foreach ($records as $record) {
            $model = new NavigationModel($record->getAttributes());

            $models[] = $model;
        }

        return $models;
    }

    /**
     * @param integer $layoutId
     * @param null    $indexBy
     *
     * @return array
     */
    public function getByLayoutId($layoutId, $indexBy = null): array
    {
        $records = NavigationRecord::find()
            ->where(['layoutId' => $layoutId])
            ->orderBy(['order' => SORT_ASC])
            ->indexBy($indexBy)
            ->all();
        $models = [];

        foreach ($records as $record) {
            $model = new NavigationModel($record->getAttributes());

            $models[] = $model;
        }

        return $models;
    }

    /**
     * @param integer $id
     *
     * @return null|\verbb\cpnav\models\Navigation
     */
    public function getById($id)
    {
        $record = NavigationRecord::findOne(['id' => $id]);

        if ($record) {
            return new NavigationModel($record->getAttributes());
        }

        return null;
    }

    /**
     * @param integer $layoutId
     * @param string  $handle
     *
     * @return null|\verbb\cpnav\models\Navigation
     */
    public function getByHandle($layoutId, $handle)
    {
        $record = NavigationRecord::findOne([
            'layoutId' => $layoutId,
            'handle'   => $handle,
        ]);

        if ($record) {
            return new NavigationModel($record->getAttributes());
        }

        return null;
    }

    /**
     * @param NavigationModel $model
     *
     * @return bool|NavigationModel
     */
    public function save(NavigationModel $model)
    {
        $record = NavigationRecord::findOne(['id' => $model->id]);

        if (!$record) {
            $record = new NavigationRecord();
        }

        $model->validate();

        if ($model->hasErrors()) {
            return false;
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

        if ($record->hasErrors()) {
            $model->addErrors($record->getErrors());

            return false;
        }

        if (!$record->save()) {
            return false;
        }

        if (!$model->id) {
            $model->id = $record->id;
        }

        return $model;
    }

    /**
     * @param NavigationModel $modal
     *
     * @return bool
     */
    public function delete(NavigationModel $modal): bool
    {
        $record = NavigationRecord::findOne(['id' => $modal->id]);

        if ($record) {
            return $record->delete() === true;
        }

        return false;
    }

    /**
     * @param integer $layoutId
     *
     * @return bool
     */
    public function deleteByLayoutId($layoutId): bool
    {
        $records = NavigationRecord::findAll(['layoutId' => $layoutId]);

        if ($records) {
            foreach ($records as $record) {
                if (!$record->delete()) {
                    return false;
                }
            }
        }

        return true;
    }
}
