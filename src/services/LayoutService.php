<?php

namespace verbb\cpnav\services;

use verbb\cpnav\models\Layout as LayoutModel;
use verbb\cpnav\records\Layout as LayoutRecord;

use Craft;
use craft\base\Component;
use craft\elements\User;

class LayoutService extends Component
{

    // Public Methods
    // =========================================================================

    /**
     * @param null $indexBy
     *
     * @return array
     */
    public function getAll($indexBy = null): array
    {
        $records = LayoutRecord::find()
            ->indexBy($indexBy)
            ->all();
        $models = [];

        foreach ($records as $record) {
            $model = new LayoutModel($record->getAttributes());

            $models[] = $model;
        }

        return $models;
    }

    /**
     * @param integer $layoutId
     *
     * @return null|\verbb\cpnav\models\Layout
     */
    public function getById($layoutId)
    {
        $record = LayoutRecord::findOne(['id' => $layoutId]);

        if ($record) {
            return new LayoutModel($record->getAttributes());
        }

        return null;
    }

    /**
     * @return null|\verbb\cpnav\models\Layout
     */
    public function getByUserId()
    {
        $layoutId = 1; // Default layout
        $userId = Craft::$app->getUser()->id;
        $records = LayoutRecord::find()->all();

        if (Craft::$app->getEdition() == Craft::Client) {
            $client = User::find()->client()->status(null)->one();

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

            $variables['clientAccount'] = $client;
        } else if (Craft::$app->getEdition() == Craft::Pro) {
            $groups = Craft::$app->userGroups->getGroupsByUserId($userId);

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

        return $this->getById($layoutId);
    }

    /**
     * @param integer $layoutId
     *
     * @return bool
     */
    public function setDefaultLayout($layoutId): bool
    {
        $layout = new LayoutRecord();
        $layout->id = $layoutId;
        $layout->name = 'Default';
        $layout->isDefault = true;

        return $layout->save();
    }

    /**
     * @param LayoutModel $model
     *
     * @return bool|LayoutModel
     */
    public function save(LayoutModel $model)
    {
        $record = LayoutRecord::findOne(['id' => $model->id]);

        if (!$record) {
            $record = new LayoutRecord();
        }

        $model->validate();

        if ($model->hasErrors()) {
            return false;
        }

        $record->name = $model->name;
        $record->permissions = $model->permissions;
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
     * @param LayoutModel $model
     *
     * @return bool
     */
    public function delete(LayoutModel $model): bool
    {
        $record = LayoutRecord::findOne(['id' => $model->id]);

        if ($record) {
            // Delete all fields for this layout (obsolete because of foreign key cascade)
//            CpNav::$plugin->navigationService->deleteByLayoutId($model->id);

            return $record->delete() === true;
        }

        return false;
    }
}
