<?php
namespace Craft;

class CpNav_NavService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function getAllManual($layoutId, $indexBy = null)
    {
        $records = CpNav_NavRecord::model()->ordered()->findAll(array('condition' => 'layoutId = '.$layoutId.' AND (manualNav IS NULL OR manualNav <> 1)'));
        return CpNav_NavModel::populateModels($records, $indexBy);
    }

    public function getByLayoutId($layoutId, $indexBy = null)
    {
        $records = CpNav_NavRecord::model()->ordered()->findAllByAttributes(array('layoutId' => $layoutId));
        return CpNav_NavModel::populateModels($records, $indexBy);
    }

    public function getById($id)
    {
        $record = CpNav_NavRecord::model()->findById($id);

        if ($record) {
            return CpNav_NavModel::populateModel($record);
        }
    }

    public function getByHandle($layoutId, $handle)
    {
        $record = CpNav_NavRecord::model()->findByAttributes(array('handle' => $handle, 'layoutId' => $layoutId));

        if ($record) {
            return CpNav_NavModel::populateModel($record);
        }
    }

    public function save(CpNav_NavModel $model)
    {
        if ($model->id) {
            $record = CpNav_NavRecord::model()->findById($model->id);
        } else {
            $record = new CpNav_NavRecord();
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

        if (!$model->hasErrors()) {
            $record->save(false);

            if (!$model->id) {
                $model->id = $record->id;
            }
        }

        return $model;
    }

    public function delete(CpNav_NavModel $modal)
    {
        $navRecord = CpNav_NavRecord::model()->findById($modal->id);

        $navRecord->delete();
    }
}
