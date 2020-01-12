<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\events\NavigationEvent;
use verbb\cpnav\models\Navigation as NavigationModel;
use verbb\cpnav\records\Navigation as NavigationRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;

class NavigationsService extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_NAVIGATION = 'beforeSaveNavigation';
    const EVENT_AFTER_SAVE_NAVIGATION = 'afterSaveNavigation';
    const EVENT_BEFORE_DELETE_NAVIGATION = 'beforeDeleteNavigation';
    const EVENT_AFTER_DELETE_NAVIGATION = 'afterDeleteNavigation';


    // Public Methods
    // =========================================================================

    public function getAllManualNavigations(int $layoutId)
    {
        $navigations = [];

        $query = $this->_createNavigationsQuery()
            ->where(['layoutId' => $layoutId])
            ->andFilterWhere([
                'or',
                ['is', 'manualNav', null],
                ['<>', 'manualNav', '1'],
            ])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        foreach ($query as $result) {
            $navigations[] = new NavigationModel($result);
        }

        return $navigations;
    }

    public function getNavigationsByLayoutId(int $layoutId, $enabledOnly = false)
    {
        $navigations = [];

        $query = $this->_createNavigationsQuery()
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

        $query = $this->_createNavigationsQuery()
            ->where(['handle' => $handle])
            ->all();

        foreach ($query as $result) {
            $navigations[] = new NavigationModel($result);
        }

        return $navigations;
    }

    public function getNavigationById(int $id)
    {
        $result = $this->_createNavigationsQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? new NavigationModel($result) : null;
    }

    public function getNavigationByHandle(int $layoutId, string $handle)
    {
        $result = $this->_createNavigationsQuery()
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

        $navigationRecord = $this->_getNavigationRecordById($navigation->id);

        $navigationRecord->layoutId = $navigation->layoutId;
        $navigationRecord->handle = $navigation->handle;
        $navigationRecord->currLabel = $navigation->currLabel;
        $navigationRecord->prevLabel = $navigation->prevLabel;
        $navigationRecord->enabled = $navigation->enabled;
        $navigationRecord->order = $navigation->order;
        $navigationRecord->url = $navigation->url;
        $navigationRecord->prevUrl = $navigation->prevUrl;
        $navigationRecord->icon = $navigation->icon;
        $navigationRecord->customIcon = $navigation->customIcon;
        $navigationRecord->manualNav = $navigation->manualNav;
        $navigationRecord->newWindow = $navigation->newWindow;

        // Save the record
        $navigationRecord->save(false);

        // Now that we have a ID, save it on the model
        if ($isNewNavigation) {
            $navigation->id = $navigationRecord->id;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_NAVIGATION)) {
            $this->trigger(self::EVENT_AFTER_SAVE_NAVIGATION, new NavigationEvent([
                'navigation' => $navigation,
                'isNew' => $isNewNavigation,
            ]));
        }

        return true;
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

        Craft::$app->getDb()->createCommand()
            ->delete('{{%cpnav_navigation}}', ['id' => $navigation->id])
            ->execute();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_NAVIGATION)) {
            $this->trigger(self::EVENT_AFTER_DELETE_NAVIGATION, new NavigationEvent([
                'navigation' => $navigation,
            ]));
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _getNavigationRecordById(int $navigationId = null): NavigationRecord
    {
        if ($navigationId !== null) {
            $navigationRecord = NavigationRecord::findOne($navigationId);

            if (!$navigationRecord) {
                throw new NavigationNotFoundException("No navigation exists with the ID '{$navigationId}'");
            }
        } else {
            $navigationRecord = new NavigationRecord();
        }

        return $navigationRecord;
    }

    private function _createNavigationsQuery(): Query
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
                'manualNav',
                'newWindow',
                'dateUpdated',
                'dateCreated',
                'uid',
            ])
            ->from(['{{%cpnav_navigation}}']);
    }
}
