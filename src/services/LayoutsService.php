<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\events\LayoutEvent;
use verbb\cpnav\events\ReorderLayoutsEvent;
use verbb\cpnav\models\Layout as LayoutModel;
use verbb\cpnav\records\Layout as LayoutRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\Structure;

class LayoutsService extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_LAYOUT = 'beforeSaveLayout';
    const EVENT_AFTER_SAVE_LAYOUT = 'afterSaveLayout';
    const EVENT_BEFORE_APPLY_LAYOUT_DELETE = 'beforeApplyLayoutDelete';
    const EVENT_BEFORE_DELETE_LAYOUT = 'beforeDeleteLayout';
    const EVENT_AFTER_DELETE_LAYOUT = 'afterDeleteLayout';
    const EVENT_BEFORE_REORDER_LAYOUTS = 'beforeReorderLayouts';
    const EVENT_AFTER_REORDER_LAYOUTS = 'afterReorderLayouts';

    const CONFIG_LAYOUT_KEY = 'cp-nav.layouts';


    // Properties
    // =========================================================================

    private $_layouts;


    // Public Methods
    // =========================================================================

    public function getAllLayouts(): array
    {
        if ($this->_layouts !== null) {
            return $this->_layouts;
        }

        $this->_layouts = [];

        foreach ($this->_createLayoutQuery()->all() as $result) {
            $this->_layouts[] = new LayoutModel($result);
        }

        return $this->_layouts;
    }

    public function getLayoutById(int $id)
    {
        return ArrayHelper::firstWhere($this->getAllLayouts(), 'id', $id);
    }

    public function getLayoutByUid(string $uid)
    {
        return ArrayHelper::firstWhere($this->getAllLayouts(), 'uid', $uid, true);
    }

    public function getLayoutForCurrentUser()
    {
        $layoutForUser = null;
        $layouts = $this->getAllLayouts();

        // Check if we're editing
        $layoutId = Craft::$app->getRequest()->getParam('layoutId');

        if ($layoutId) {
            return $this->getLayoutById($layoutId);
        }

        if (Craft::$app->getEdition() == Craft::Solo) {
            $solo = User::find()->status(null)->one();

            // Is there even a solo account?
            if ($solo) {
                foreach ($layouts as $key => $layout) {
                    if (is_array($layout->permissions) && in_array('solo', $layout->permissions, false)) {
                        return $layout;
                    }
                }
            }
        } else if (Craft::$app->getEdition() == Craft::Pro) {
            $userId = Craft::$app->getUser()->id;
            $groups = Craft::$app->userGroups->getGroupsByUserId($userId);

            foreach ($groups as $index => $group) {
                foreach ($layouts as $key => $layout) {
                    if (is_array($layout->permissions) && in_array($group->uid, $layout->permissions, false)) {
                        return $layout;
                    }
                }
            }
        }

        return $this->getLayoutById(1);
    }

    public function saveLayout(LayoutModel $layout, $isNewLayout = null, bool $runValidation = true): bool
    {
        if ($isNewLayout === null) {
            $isNewLayout = !$layout->id;
        }

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

        if ($isNewLayout) {
            $layout->uid = StringHelper::UUID();

            $layout->sortOrder = ((int)(new Query())
                    ->from(['{{%cpnav_layout}}'])
                    ->max('[[sortOrder]]')) + 1;
        } else {
            $layout->uid = Db::uidById('{{%cpnav_layout}}', $layout->id);
        }

        $projectConfig = Craft::$app->getProjectConfig();

        $configData = [
            'name' => $layout->name,
            'isDefault' => $layout->isDefault,
            'permissions' => Json::encode($layout->permissions),
            'sortOrder' => (int)$layout->sortOrder,
        ];

        $configPath = self::CONFIG_LAYOUT_KEY . '.' . $layout->uid;
        $projectConfig->set($configPath, $configData);

        if ($isNewLayout) {
            $layout->id = Db::idByUid('{{%cpnav_layout}}', $layout->uid);
        }

        return true;
    }

    public function handleChangedLayout(ConfigEvent $event)
    {
        $layoutUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $layoutRecord = $this->_getLayoutRecord($layoutUid);
            $isNewLayout = $layoutRecord->getIsNewRecord();

            $layoutRecord->name = $data['name'];
            $layoutRecord->isDefault = $data['isDefault'];
            $layoutRecord->permissions = $data['permissions'];
            $layoutRecord->sortOrder = $data['sortOrder'] ?? 0;
            $layoutRecord->uid = $layoutUid;

            $layoutRecord->save(false);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        $this->_layouts = null;

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_LAYOUT)) {
            $this->trigger(self::EVENT_AFTER_SAVE_LAYOUT, new LayoutEvent([
                'layout' => $this->getLayoutById($layoutRecord->id),
                'isNew' => $isNewLayout,
            ]));
        }
    }

    public function reorderLayouts(array $layoutIds): bool
    {
        // Fire a 'beforeReorderLayouts' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_REORDER_LAYOUTS)) {
            $this->trigger(self::EVENT_BEFORE_REORDER_LAYOUTS, new ReorderLayoutsEvent([
                'layoutIds' => $layoutIds,
            ]));
        }

        $projectConfig = Craft::$app->getProjectConfig();

        $uidsByIds = Db::uidsByIds('{{%cpnav_layout}}', $layoutIds);

        foreach ($layoutIds as $sortOrder => $layoutId) {
            if (!empty($uidsByIds[$layoutId])) {
                $layoutUid = $uidsByIds[$layoutId];
                $projectConfig->set(self::CONFIG_LAYOUT_KEY . '.' . $layoutUid . '.sortOrder', $sortOrder + 1, 'Reorder layouts');
            }
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_REORDER_LAYOUTS)) {
            $this->trigger(self::EVENT_AFTER_REORDER_LAYOUTS, new ReorderLayoutsEvent([
                'layoutIds' => $layoutIds,
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

        Craft::$app->getProjectConfig()->remove(self::CONFIG_LAYOUT_KEY . '.' . $layout->uid);

        return true;
    }

    public function handleDeletedLayout(ConfigEvent $event)
    {
        $layoutUid = $event->tokenMatches[0];

        $layout = $this->getLayoutByUid($layoutUid);

        if (!$layout) {
            return;
        }

        if ($this->hasEventHandlers(self::EVENT_BEFORE_APPLY_LAYOUT_DELETE)) {
            $this->trigger(self::EVENT_BEFORE_APPLY_LAYOUT_DELETE, new LayoutEvent([
                'layout' => $layout,
            ]));
        }

        Craft::$app->getDb()->createCommand()
            ->delete('{{%cpnav_layout}}', ['uid' => $layoutUid])
            ->execute();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_LAYOUT)) {
            $this->trigger(self::EVENT_AFTER_DELETE_LAYOUT, new LayoutEvent([
                'layout' => $layout,
            ]));
        }
    }


    // Private Methods
    // =========================================================================

    private function _getLayoutRecord(string $uid): LayoutRecord
    {
        return LayoutRecord::findOne(['uid' => $uid]) ?? new LayoutRecord();
    }

    private function _createLayoutQuery(): Query
    {
        $query = (new Query())
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

        $schemaVersion = Craft::$app->getProjectConfig()->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.7', '>=')) {
            $query->addSelect(['sortOrder']);
            $query->orderBy(['sortOrder' => SORT_ASC]);
        }

        return $query;
    }
}
