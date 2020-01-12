<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\events\LayoutEvent;
use verbb\cpnav\models\Layout as LayoutModel;
use verbb\cpnav\records\Layout as LayoutRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\User;

class LayoutsService extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_LAYOUT = 'beforeSaveLayout';
    const EVENT_AFTER_SAVE_LAYOUT = 'afterSaveLayout';
    const EVENT_BEFORE_DELETE_LAYOUT = 'beforeDeleteLayout';
    const EVENT_AFTER_DELETE_LAYOUT = 'afterDeleteLayout';


    // Public Methods
    // =========================================================================

    public function getAllLayouts(): array
    {
        $layouts = [];

        foreach ($this->_createLayoutsQuery()->all() as $result) {
            $layouts[] = new LayoutModel($result);
        }

        return $layouts;
    }

    public function getLayoutById(int $id)
    {
        $result = $this->_createLayoutsQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? new LayoutModel($result) : null;
    }

    public function getLayoutForCurrentUser()
    {
        $layoutForUser = null;
        $layouts = $this->getAllLayouts();

        if (Craft::$app->getEdition() == Craft::Solo) {
            $solo = User::find()->status(null)->one();

            // Is there even a solo account?
            if ($solo) {
                foreach ($layouts as $key => $layout) {
                    $permissions = json_decode($layout->permissions);

                    if (is_array($permissions) && in_array('solo', $permissions, false)) {
                        $layoutForUser = $layout;

                        break;
                    }
                }
            }
        } else if (Craft::$app->getEdition() == Craft::Pro) {
            $userId = Craft::$app->getUser()->id;
            $groups = Craft::$app->userGroups->getGroupsByUserId($userId);

            foreach ($groups as $index => $group) {
                foreach ($layouts as $key => $layout) {
                    $permissions = json_decode($layout->permissions);

                    if (is_array($permissions) && in_array($group->id, $permissions, false)) {
                        $layoutForUser = $layout;

                        break 2;
                    }
                }
            }
        }

        if (!$layoutForUser) {
            return $this->getLayoutById(1);
        }

        return $layoutForUser;
    }

    public function setDefaultLayout($layoutId): bool
    {
        $layout = new LayoutRecord();
        $layout->id = $layoutId;
        $layout->name = 'Default';
        $layout->isDefault = true;

        return $layout->save();
    }

    public function saveLayout(LayoutModel $layout, bool $runValidation = true): bool
    {
        $isNewLayout = !$layout->id;

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_LAYOUT)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_LAYOUT, new LayoutEvent([
                'layout' => $layout,
                'isNew' => $isNewLayout,
            ]));
        }

        if ($runValidation && !$layout->validate()) {
            Craft::info('Layout not saved due to validation error.', __METHOD__);
            return false;
        }

        $layoutRecord = $this->_getLayoutRecordById($layout->id);

        $layoutRecord->name = $layout->name;
        $layoutRecord->permissions = $layout->permissions;

        // Save the record
        $layoutRecord->save(false);

        // Now that we have a ID, save it on the model
        if ($isNewLayout) {
            $layout->id = $layoutRecord->id;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_LAYOUT)) {
            $this->trigger(self::EVENT_AFTER_SAVE_LAYOUT, new LayoutEvent([
                'layout' => $layout,
                'isNew' => $isNewLayout,
            ]));
        }

        return true;
    }

    public function deleteLayoutById(int $layoutId): bool
    {
        $layout = $this->getLayoutById($layoutId);

        if (!$layout) {
            return false;
        }

        return $this->deleteLayout($layout);
    }

    public function deleteLayout(LayoutModel $layout): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_LAYOUT)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_LAYOUT, new LayoutEvent([
                'layout' => $layout,
            ]));
        }

        Craft::$app->getDb()->createCommand()
            ->delete('{{%cpnav_layout}}', ['id' => $layout->id])
            ->execute();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_LAYOUT)) {
            $this->trigger(self::EVENT_AFTER_DELETE_LAYOUT, new LayoutEvent([
                'layout' => $layout,
            ]));
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _getLayoutRecordById(int $layoutId = null): LayoutRecord
    {
        if ($layoutId !== null) {
            $layoutRecord = LayoutRecord::findOne($layoutId);

            if (!$layoutRecord) {
                throw new LayoutNotFoundException("No layout exists with the ID '{$layoutId}'");
            }
        } else {
            $layoutRecord = new LayoutRecord();
        }

        return $layoutRecord;
    }

    private function _createLayoutsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'isDefault',
                'permissions',
                'dateUpdated',
                'dateCreated',
                'uid',
            ])
            ->from(['{{%cpnav_layout}}']);
    }
}
