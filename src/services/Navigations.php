<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\events\NavigationEvent;
use verbb\cpnav\models\Navigation;
use verbb\cpnav\records\Navigation as NavigationRecord;

use Craft;
use craft\base\Component;
use craft\base\MemoizableArray;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use Throwable;

class Navigations extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_NAVIGATION = 'beforeSaveNavigation';
    public const EVENT_AFTER_SAVE_NAVIGATION = 'afterSaveNavigation';
    public const EVENT_BEFORE_APPLY_NAVIGATION_DELETE = 'beforeApplyNavigationDelete';
    public const EVENT_BEFORE_DELETE_NAVIGATION = 'beforeDeleteNavigation';
    public const EVENT_AFTER_DELETE_NAVIGATION = 'afterDeleteNavigation';

    public const CONFIG_NAVIGATION_KEY = 'cp-nav.navigations';
    

    // Properties
    // =========================================================================

    private ?MemoizableArray $_navigations = null;


    // Public Methods
    // =========================================================================

    public function getAllNavigations(): array
    {
        return $this->_navigations()->all();
    }

    public function getAllNavigationsByLayoutId(int $layoutId): array
    {
        return $this->_navigations()->where('layoutId', $layoutId, true)->all();
    }

    public function getAllNavigationsByHandle(string $handle): array
    {
        return $this->_navigations()->where('handle', $handle, true)->all();
    }

    public function getNavigationById(int $id): ?Navigation
    {
        return $this->_navigations()->firstWhere('id', $id);
    }

    public function getNavigationByHandle(string $handle): ?Navigation
    {
        return $this->_navigations()->firstWhere('handle', $handle, true);
    }

    public function getNavigationByUid(string $uid): ?Navigation
    {
        return $this->_navigations()->firstWhere('uid', $uid, true);
    }

    public function saveNavigation(Navigation $navigation, bool $runValidation = true): bool
    {
        $isNewNavigation = !$navigation->id;

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_NAVIGATION)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_NAVIGATION, new NavigationEvent([
                'navigation' => $navigation,
                'isNew' => $isNewNavigation,
            ]));
        }

        if ($runValidation && !$navigation->validate()) {
            Craft::info('Navigation not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewNavigation) {
            $navigation->uid = StringHelper::UUID();

            $navigation->sortOrder = (new Query())
                ->from(['{{%cpnav_navigation}}'])
                ->where(['layoutId' => $navigation->layoutId])
                ->max('[[sortOrder]]') + 1;
        } else if (!$navigation->uid) {
            $navigation->uid = Db::uidById('{{%cpnav_navigation}}', $navigation->id);
        }

        $configPath = self::CONFIG_NAVIGATION_KEY . '.' . $navigation->uid;
        Craft::$app->getProjectConfig()->set($configPath, $navigation->getConfig(), "Saving navigation “{$navigation->handle}”", true, true);

        if ($isNewNavigation) {
            $navigation->id = Db::idByUid('{{%cpnav_navigation}}', $navigation->uid);
        }

        return true;
    }

    public function handleChangedNavigation(ConfigEvent $event): void
    {
        $navigationUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $layoutUid = $data['layout'] ?? null;

        // Ensure we have the layout in the place first
        if ($layoutUid) {
            Craft::$app->getProjectConfig()->processConfigChanges(Layouts::CONFIG_LAYOUT_KEY . '.' . $layoutUid);
        } else {
            // In case the layout UID isn't set yet, we can't go further
            return;
        }

        $layout = CpNav::$plugin->getLayouts()->getLayoutByUid($layoutUid);

        if (!$layout) {
            return;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $navigationRecord = $this->_getNavigationRecord($navigationUid);
            $isNewNavigation = $navigationRecord->getIsNewRecord();

            $prevParentUid = $data['prevParent'] ?? '';
            $parentUid = $data['parent'] ?? '';

            $prevParent = $this->getNavigationByUid($prevParentUid);
            $parent = $this->getNavigationByUid($parentUid);

            $level = $data['level'] ?? null;

            // If a subnav item, and no parent exists yet, ensure that's applied first
            if ($level === 2 && !$parent) {
                Craft::$app->getProjectConfig()->processConfigChanges(self::CONFIG_NAVIGATION_KEY . '.' . $parentUid);
            }

            $navigationRecord->layoutId = $layout->id;
            $navigationRecord->handle = $data['handle'] ?? '';
            $navigationRecord->prevLabel = $data['prevLabel'] ?? '';
            $navigationRecord->currLabel = $data['currLabel'] ?? '';
            $navigationRecord->enabled = $data['enabled'] ?? false;
            $navigationRecord->sortOrder = $data['sortOrder'] ?? null;
            $navigationRecord->prevLevel = $data['prevLevel'] ?? null;
            $navigationRecord->level = $data['level'] ?? null;
            $navigationRecord->prevParentId = $prevParent->id ?? null;
            $navigationRecord->parentId = $parent->id ?? null;
            $navigationRecord->prevUrl = $data['prevUrl'] ?? '';
            $navigationRecord->url = $data['url'] ?? '';
            $navigationRecord->icon = $data['icon'] ?? '';
            $navigationRecord->customIcon = $data['customIcon'] ?? '';
            $navigationRecord->type = $data['type'] ?? '';
            $navigationRecord->newWindow = $data['newWindow'] ?? false;
            $navigationRecord->subnavBehaviour = $data['subnavBehaviour'] ?? null;
            $navigationRecord->uid = $navigationUid;

            $navigationRecord->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        $this->_navigations = null;

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_NAVIGATION)) {
            $this->trigger(self::EVENT_AFTER_SAVE_NAVIGATION, new NavigationEvent([
                'navigation' => $this->getNavigationById($navigationRecord->id),
                'isNew' => $isNewNavigation,
            ]));
        }
    }

    public function deleteNavigationById(int $navigationId): bool
    {
        $navigation = $this->getNavigationById($navigationId);

        if (!$navigation) {
            return false;
        }

        return $this->deleteNavigation($navigation);
    }

    public function deleteNavigationFromAllLayouts(string $handle): bool
    {
        $navigations = $this->getAllNavigationsByHandle($handle);

        foreach ($navigations as $navigation) {
            // Remember to fetch the original children (subnav) for this navigation - not the current
            // which are custom and might've moved around.
            foreach ($navigation->getPrevChildren() as $child) {
                $this->deleteNavigation($child);
            }

            $this->deleteNavigation($navigation);
        }

        return true;
    }

    public function deleteNavigation(Navigation $navigation): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_NAVIGATION)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_NAVIGATION, new NavigationEvent([
                'navigation' => $navigation,
            ]));
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_NAVIGATION_KEY . '.' . $navigation->uid, "Delete navigation “{$navigation->handle}”");

        return true;
    }

    public function handleDeletedNavigation(ConfigEvent $event): void
    {
        $navigationUid = $event->tokenMatches[0];

        $navigation = $this->getNavigationByUid($navigationUid);

        if (!$navigation) {
            return;
        }

        if ($this->hasEventHandlers(self::EVENT_BEFORE_APPLY_NAVIGATION_DELETE)) {
            $this->trigger(self::EVENT_BEFORE_APPLY_NAVIGATION_DELETE, new NavigationEvent([
                'navigation' => $navigation,
            ]));
        }

        Db::delete('{{%cpnav_navigation}}', [
            'uid' => $navigationUid,
        ]);

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_NAVIGATION)) {
            $this->trigger(self::EVENT_AFTER_DELETE_NAVIGATION, new NavigationEvent([
                'navigation' => $navigation,
            ]));
        }

        // We also need to now update any nav's that were children of this deleted nav and reset them.
        // Only handle manual or dividers, as all other instances will take care of themselves
        foreach ($navigation->getChildren() as $child) {
            if (!in_array($child->type, ['divider', 'manual'])) {
                continue;
            }

            $child->level = 1;
            $child->parentId = null;

            $this->saveNavigation($child);
        }

        // Clear caches
        $this->_navigations = null;
    }


    // Private Methods
    // =========================================================================

    private function _navigations(): MemoizableArray
    {
        if (!isset($this->_navigations)) {
            $navigations = [];

            foreach ($this->_createNavigationQuery()->all() as $result) {
                $navigations[] = new Navigation($result);
            }

            // Also prepare and child navs, by setting them as children. Basically, a simplified
            // structure, that's limited to only 2 levels.
            foreach ($navigations as $navigation) {
                $parentId = $navigation->parentId ?? $navigation->prevParentId;

                if ($parentId && $parentNavigation = ArrayHelper::firstWhere($navigations, 'id', $parentId)) {
                    $parentNavigation->addChild($navigation);
                }

                // Also keep track of the previous (original) parent
                if ($navigation->prevParentId && $parentNavigation = ArrayHelper::firstWhere($navigations, 'id', $navigation->prevParentId)) {
                    $parentNavigation->addPrevChild($navigation);
                }
            }

            $this->_navigations = new MemoizableArray($navigations);
        }

        return $this->_navigations;
    }

    private function _getNavigationRecord(string $uid): NavigationRecord
    {
        return NavigationRecord::findOne(['uid' => $uid]) ?? new NavigationRecord();
    }

    private function _createNavigationQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'layoutId',
                'handle',
                'prevLabel',
                'currLabel',
                'enabled',
                'sortOrder',
                'prevLevel',
                'level',
                'prevParentId',
                'parentId',
                'prevUrl',
                'url',
                'icon',
                'customIcon',
                'type',
                'newWindow',
                'subnavBehaviour',
                'dateUpdated',
                'dateCreated',
                'uid',
            ])
            ->from(['{{%cpnav_navigation}}'])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }
}
