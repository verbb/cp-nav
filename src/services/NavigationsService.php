<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\events\NavigationEvent;
use verbb\cpnav\helpers\ProjectConfig as ProjectConfigHelper;
use verbb\cpnav\models\Navigation as NavigationModel;
use verbb\cpnav\records\Navigation as NavigationRecord;

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

class NavigationsService extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_NAVIGATION = 'beforeSaveNavigation';
    const EVENT_AFTER_SAVE_NAVIGATION = 'afterSaveNavigation';
    const EVENT_BEFORE_APPLY_NAVIGATION_DELETE = 'beforeApplyNavigationDelete';
    const EVENT_BEFORE_DELETE_NAVIGATION = 'beforeDeleteNavigation';
    const EVENT_AFTER_DELETE_NAVIGATION = 'afterDeleteNavigation';

    const CONFIG_NAVIGATION_KEY = 'cp-nav.navigations';
    

    // Properties
    // =========================================================================

    private $_navigations;


    // Public Methods
    // =========================================================================

    public function getAllNavigations(): array
    {
        if ($this->_navigations !== null) {
            return $this->_navigations;
        }

        $this->_navigations = [];

        foreach ($this->_createNavigationQuery()->all() as $result) {
            $this->_navigations[] = new NavigationModel($result);
        }

        return $this->_navigations;
    }

    public function getNavigationsByLayoutId(int $layoutId, $enabledOnly = false)
    {
        $navigations = [];

        $query = $this->_createNavigationQuery()
            ->where(['layoutId' => $layoutId])
            ->orderBy(['order' => SORT_ASC]);

        if ($enabledOnly) {
            $query->andWhere(['enabled' => true]);
        }

        foreach ($query->all() as $result) {
            $navigations[] = new NavigationModel($result);
        }

        return $navigations;
    }

    public function getAllNavigationsByHandle(string $handle)
    {
        $navigations = [];

        $query = $this->_createNavigationQuery()
            ->where(['handle' => $handle])
            ->all();

        foreach ($query as $result) {
            $navigations[] = new NavigationModel($result);
        }

        return $navigations;
    }

    public function getNavigationById(int $id)
    {
        return ArrayHelper::firstWhere($this->getAllNavigations(), 'id', $id);
    }

    public function getNavigationByUid(string $uid)
    {
        return ArrayHelper::firstWhere($this->getAllNavigations(), 'uid', $uid, true);
    }

    public function getNavigationByHandle(int $layoutId, string $handle)
    {
        $result = $this->_createNavigationQuery()
            ->where(['layoutId' => $layoutId, 'handle' => $handle])
            ->one();

        return $result ? new NavigationModel($result) : null;
    }

    public function saveNavigation(NavigationModel $navigation, bool $runValidation = true): bool
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
        } else {
            $navigation->uid = Db::uidById('{{%cpnav_navigation}}', $navigation->id);
        }

        $projectConfig = Craft::$app->getProjectConfig();

        $configData = [
            'layout' => $navigation->getLayout()->uid,
            'handle' => $navigation->handle,
            'currLabel' => $navigation->currLabel,
            'prevLabel' => $navigation->prevLabel,
            'enabled' => $navigation->enabled,
            'order' => $navigation->order,
            'url' => $navigation->url,
            'prevUrl' => $navigation->prevUrl,
            'icon' => $navigation->icon,
            'customIcon' => $navigation->customIcon,
            'type' => $navigation->type,
            'newWindow' => $navigation->newWindow,
        ];

        $configPath = self::CONFIG_NAVIGATION_KEY . '.' . $navigation->uid;
        $projectConfig->set($configPath, $configData);

        if ($isNewNavigation) {
            $navigation->id = Db::idByUid('{{%cpnav_navigation}}', $navigation->uid);
        }

        return true;
    }

    public function handleChangedNavigation(ConfigEvent $event)
    {
        $navigationUid = $event->tokenMatches[0];
        $data = $event->newValue;

        // Make sure layouts are processed
        ProjectConfigHelper::ensureAllLayoutsProcessed();

        $layoutUid = $data['layout'] ?? '';

        $layout = CpNav::$plugin->getLayouts()->getLayoutByUid($layoutUid);
        $navigationRecord = $this->_getNavigationRecord($navigationUid, true);

        if (!$layout || !$navigationRecord) {
            return;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $isNewNavigation = $navigationRecord->getIsNewRecord();

            $navigationRecord->layoutId = $layout->id;
            $navigationRecord->handle = $data['handle'] ?? '';
            $navigationRecord->currLabel = $data['currLabel'] ?? '';
            $navigationRecord->prevLabel = $data['prevLabel'] ?? '';
            $navigationRecord->enabled = $data['enabled'] ?? '';
            $navigationRecord->order = $data['order'] ?? '';
            $navigationRecord->url = $data['url'] ?? '';
            $navigationRecord->prevUrl = $data['prevUrl'] ?? '';
            $navigationRecord->icon = $data['icon'] ?? '';
            $navigationRecord->customIcon = $data['customIcon'] ?? '';
            $navigationRecord->type = $data['type'] ?? '';
            $navigationRecord->newWindow = $data['newWindow'] ?? '';
            $navigationRecord->uid = $navigationUid;

            $navigationRecord->save(false);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_NAVIGATION)) {
            $this->trigger(self::EVENT_AFTER_SAVE_NAVIGATION, new NavigationEvent([
                'navigation' => $this->getNavigationById($navigationRecord->id),
                'isNew' => $isNewNavigation,
            ]));
        }
    }

    public function saveNavigationToAllLayouts(NavigationModel $navigation)
    {
        $layouts = CpNav::$plugin->getLayouts()->getAllLayouts();

        // Sanity check, in case its already there
        $navigations = $this->getAllNavigationsByHandle($navigation->handle);

        if ($navigations) {
            return;
        }

        foreach ($layouts as $layout) {
            $nav = clone $navigation;
            $nav->layoutId = $layout->id;

            $this->saveNavigation($nav);
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
            $this->deleteNavigation($navigation);
        }

        return true;
    }

    public function deleteNavigation(NavigationModel $navigation): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_NAVIGATION)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_NAVIGATION, new NavigationEvent([
                'navigation' => $navigation,
            ]));
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_NAVIGATION_KEY . '.' . $navigation->uid);

        return true;
    }

    public function handleDeletedNavigation(ConfigEvent $event)
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

        Craft::$app->getDb()->createCommand()
            ->delete('{{%cpnav_navigation}}', ['uid' => $navigationUid])
            ->execute();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_NAVIGATION)) {
            $this->trigger(self::EVENT_AFTER_DELETE_NAVIGATION, new NavigationEvent([
                'navigation' => $navigation,
            ]));
        }
    }


    // Private Methods
    // =========================================================================

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
                'order',
                'prevUrl',
                'url',
                'icon',
                'customIcon',
                'type',
                'newWindow',
                'dateUpdated',
                'dateCreated',
                'uid',
            ])
            ->from(['{{%cpnav_navigation}}']);
    }
}
