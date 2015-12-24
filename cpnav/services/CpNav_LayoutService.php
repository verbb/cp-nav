<?php
namespace Craft;

class CpNav_LayoutService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function getAll($indexBy = null)
    {
        $records = CpNav_LayoutRecord::model()->findAll();
        return CpNav_LayoutModel::populateModels($records, $indexBy);
    }

    public function getById($layoutId)
    {
        $record = CpNav_LayoutRecord::model()->findById($layoutId);

        if ($record) {
            return CpNav_LayoutModel::populateModel($record);
        }
    }

    public function getByUserId()
    {
        $layoutId = 1; // Default layout
        $userId = craft()->userSession->getUser()->id;
        $records = CpNav_LayoutRecord::model()->findAll();

        if (craft()->getEdition() == Craft::Client) {
            $client = craft()->users->getClient();

            // Is there even a client account?
            if ($client) {
                foreach ($records as $key => $record) {
                    if (is_array($record->permissions)) {
                        if (in_array('client', $record->permissions)) {
                            $layoutId = $record->id;

                            break; // break out immediately
                        }
                    }
                }
            }

            $variables['clientAccount'] = craft()->users->getClient();
        } else if (craft()->getEdition() == Craft::Pro) {
            $groups = craft()->userGroups->getGroupsByUserId($userId);

            foreach ($groups as $index => $group) {
                foreach ($records as $key => $record) {
                    if (is_array($record->permissions)) {
                        if (in_array($group->id, $record->permissions)) {
                            $layoutId = $record->id;

                            break 2; // break out immediately
                        }
                    }
                }
            }
        }

        $record = CpNav_LayoutRecord::model()->findById($layoutId);

        if ($record) {
            return CpNav_LayoutModel::populateModel($record);
        }
    }

    public function save(CpNav_LayoutModel $model)
    {
        if ($model->id) {
            $record = CpNav_LayoutRecord::model()->findById($model->id);
        } else {
            $record = new CpNav_LayoutRecord();
        }

        $record->name = $model->name;
        $record->permissions = $model->permissions;

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

    public function delete(CpNav_LayoutModel $layout)
    {
        $record = CpNav_LayoutRecord::model()->findById($layout->id);

        // Delete all fields for this layout
        $navRecords = CpNav_NavRecord::model()->deleteAll('layoutId = :layoutId', array('layoutId' => $layout->id));

        $record->delete();
    }
}

