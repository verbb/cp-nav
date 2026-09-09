<?php
namespace verbb\cpnav;

use verbb\cpnav\base\PluginTrait;
use verbb\cpnav\helpers\Plugin as CpNavPluginHelper;
use verbb\cpnav\helpers\ProjectConfigData;
use verbb\cpnav\models\Settings;
use verbb\cpnav\services\Layouts;
use verbb\cpnav\nav\sources\NavSourcesInvalidator;

use Craft;
use craft\base\Plugin;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\ProjectConfig;
use craft\web\UrlManager;

use yii\base\Event;

class CpNav extends Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;


    // Properties
    // =========================================================================

    public bool $hasCpSettings = true;
    public string $schemaVersion = '6.0.0';
    public string $minVersionRequired = '5.0.0';


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerProjectConfigEventHandlers();
        $this->_registerSourcesInvalidation();
        $this->_registerNavRender();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            // No need to do anything if this is an action request (despite being a CP request)
            if (!Craft::$app->getRequest()->getIsActionRequest()) {
                $this->_registerCpRoutes();

                CpNavPluginHelper::registerSidebarAssets();
            }
        }
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('cp-nav/settings'));
    }


    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'cp-nav' => 'cp-nav/admin/index',
                'cp-nav/settings' => 'cp-nav/settings/index',
                'cp-nav/api/layout-tree' => 'cp-nav/api/layout-tree',
                'cp-nav/api/update-node' => 'cp-nav/api/update-node',
                'cp-nav/api/create-node' => 'cp-nav/api/create-node',
                'cp-nav/api/delete-node' => 'cp-nav/api/delete-node',
                'cp-nav/api/reorder-nodes' => 'cp-nav/api/reorder-nodes',
                'cp-nav/api/indent-node' => 'cp-nav/api/indent-node',
                'cp-nav/api/outdent-node' => 'cp-nav/api/outdent-node',
                'cp-nav/api/reparent-node' => 'cp-nav/api/reparent-node',
                'cp-nav/api/refresh-sources' => 'cp-nav/api/refresh-sources',
                'cp-nav/api/acknowledge-new-items' => 'cp-nav/api/acknowledge-new-items',
                'cp-nav/api/reset-layout' => 'cp-nav/api/reset-layout',
                'cp-nav/static-icons' => 'cp-nav/static-icons/index',
                'cp-nav/static-icons/view' => 'cp-nav/static-icons/view',
                'cp-nav/layouts' => 'cp-nav/layout/index',
                'cp-nav/layouts/get-hud-html' => 'cp-nav/layouts/getHudHtml',
            ]);
        });
    }

    private function _registerProjectConfigEventHandlers(): void
    {
        Craft::$app->getProjectConfig()->onAdd(Layouts::CONFIG_LAYOUT_KEY . '.{uid}', [$this->getLayouts(), 'handleChangedLayout'])
            ->onUpdate(Layouts::CONFIG_LAYOUT_KEY . '.{uid}', [$this->getLayouts(), 'handleChangedLayout'])
            ->onRemove(Layouts::CONFIG_LAYOUT_KEY . '.{uid}', [$this->getLayouts(), 'handleDeletedLayout']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event) {
            $event->config['cp-nav'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    private function _registerNavRender(): void
    {
        // Native Craft sidebar via RegisterCpNavItemsEvent — no MutationObserver.
        $this->getNavRenderer()->register();
    }

    private function _registerSourcesInvalidation(): void
    {
        (new NavSourcesInvalidator())->register();
    }

}
