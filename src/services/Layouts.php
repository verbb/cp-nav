<?php
namespace verbb\cpnav\services;

use verbb\cpnav\events\LayoutEvent;
use verbb\cpnav\events\ReorderLayoutsEvent;
use verbb\cpnav\models\Layout;
use verbb\cpnav\records\Layout as LayoutRecord;

use Craft;
use craft\base\Component;
use craft\base\MemoizableArray;
use craft\db\Query;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use Throwable;

class Layouts extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_LAYOUT = 'beforeSaveLayout';
    public const EVENT_AFTER_SAVE_LAYOUT = 'afterSaveLayout';
    public const EVENT_BEFORE_APPLY_LAYOUT_DELETE = 'beforeApplyLayoutDelete';
    public const EVENT_BEFORE_DELETE_LAYOUT = 'beforeDeleteLayout';
    public const EVENT_AFTER_DELETE_LAYOUT = 'afterDeleteLayout';
    public const EVENT_BEFORE_REORDER_LAYOUTS = 'beforeReorderLayouts';
    public const EVENT_AFTER_REORDER_LAYOUTS = 'afterReorderLayouts';

    public const CONFIG_LAYOUT_KEY = 'cp-nav.layouts';


    // Properties
    // =========================================================================

    private ?MemoizableArray $_layouts = null;


    // Public Methods
    // =========================================================================

    public function getAllLayouts(): array
    {
        return $this->_layouts()->all();
    }

    public function getLayoutById(?int $layoutId, bool $getDefault = false): ?Layout
    {
        $layout = $this->_layouts()->firstWhere('id', $layoutId);

        if (!$layout && $getDefault) {
            return $this->getDefaultLayout();
        }

        return $layout;
    }

    public function getLayoutByUid(string $layoutUid): ?Layout
    {
        return $this->_layouts()->firstWhere('uid', $layoutUid);
    }

    public function getDefaultLayout(): ?Layout
    {
        return $this->_layouts()->firstWhere('isDefault', true);
    }

    public function getLayoutForCurrentUser(): ?Layout
    {
        // Check if we're editing
        $layoutId = Craft::$app->getRequest()->getParam('layoutId');

        if ($layoutId) {
            return $this->getLayoutById($layoutId);
        }

        $layouts = $this->getAllLayouts();

        if (Craft::$app->getEdition() == Craft::Solo) {
            // Is there even a solo account?
            if ($solo = User::find()->status(null)->one()) {
                foreach ($layouts as $layout) {
                    if (is_array($layout->permissions) && in_array('solo', $layout->permissions, false)) {
                        return $layout;
                    }
                }
            }
        } else if (Craft::$app->getEdition() == Craft::Pro) {
            if ($userId = Craft::$app->getUser()->id) {
                $groups = Craft::$app->userGroups->getGroupsByUserId($userId);

                foreach ($groups as $group) {
                    foreach ($layouts as $layout) {
                        if (is_array($layout->permissions) && in_array($group->uid, $layout->permissions, false)) {
                            return $layout;
                        }
                    }
                }
            }
        }

        // Otherwise, fetch the default layout
        return $this->getDefaultLayout();
    }

    public function saveLayout(Layout $layout, bool $runValidation = true): bool
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

        if ($isNewLayout) {
            $layout->uid = StringHelper::UUID();

            $layout->sortOrder = (new Query())
                ->from(['{{%cpnav_layout}}'])
                ->max('[[sortOrder]]') + 1;
        } else if (!$layout->uid) {
            $layout->uid = Db::uidById('{{%cpnav_layout}}', $layout->id);
        }

        $configPath = self::CONFIG_LAYOUT_KEY . '.' . $layout->uid;

        Craft::$app->getProjectConfig()->set($configPath, $layout->getConfig(), "Saving layout “{$layout->name}”");

        if ($isNewLayout) {
            $layout->id = Db::idByUid('{{%cpnav_layout}}', $layout->uid);
        }

        return true;
    }

    public function handleChangedLayout(ConfigEvent $event): void
    {
        $layoutUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $layoutRecord = $this->_getLayoutRecord($layoutUid);
            $isNewLayout = $layoutRecord->getIsNewRecord();

            $layoutRecord->name = $data['name'];
            $layoutRecord->isDefault = (bool)$data['isDefault'];
            $layoutRecord->permissions = $data['permissions'] ?? [];
            $layoutRecord->sortOrder = $data['sortOrder'] ?? 0;
            $layoutRecord->uid = $layoutUid;

            $layoutRecord->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
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

    public function deleteLayout(Layout $layout): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_LAYOUT)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_LAYOUT, new LayoutEvent([
                'layout' => $layout,
            ]));
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_LAYOUT_KEY . '.' . $layout->uid, "Delete layout “{$layout->name}”");

        return true;
    }

    public function handleDeletedLayout(ConfigEvent $event): void
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

        Db::delete('{{%cpnav_layout}}', [
            'uid' => $layoutUid,
        ]);

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_LAYOUT)) {
            $this->trigger(self::EVENT_AFTER_DELETE_LAYOUT, new LayoutEvent([
                'layout' => $layout,
            ]));
        }
    }


    // Private Methods
    // =========================================================================

    private function _layouts(): MemoizableArray
    {
        if (!isset($this->_layouts)) {
            $layouts = [];

            foreach ($this->_createLayoutQuery()->all() as $result) {
                $layouts[] = new Layout($result);
            }

            $this->_layouts = new MemoizableArray($layouts);
        }

        return $this->_layouts;
    }

    private function _getLayoutRecord(string $uid): LayoutRecord
    {
        return LayoutRecord::findOne(['uid' => $uid]) ?? new LayoutRecord();
    }

    private function _createLayoutQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'isDefault',
                'permissions',
                'sortOrder',
                'dateUpdated',
                'dateCreated',
                'uid',
            ])
            ->from(['{{%cpnav_layout}}'])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }
}
