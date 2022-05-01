<?php
namespace verbb\cpnav;

use verbb\cpnav\base\PluginTrait;
use verbb\cpnav\assetbundles\CpNavAsset;
use verbb\cpnav\models\Settings;
use verbb\cpnav\services\LayoutsService;
use verbb\cpnav\services\NavigationsService;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;

use yii\base\Event;

class CpNav extends Plugin
{
    // Properties
    // =========================================================================

    public bool $hasCpSettings = true;
    public string $schemaVersion = '2.0.11';
    public string $minVersionRequired = '3.0.17';


    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerComponents();
        $this->_registerLogTarget();
        $this->_registerProjectConfigEventListeners();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerTemplateHooks();

            Craft::$app->getView()->registerAssetBundle(CpNavAsset::class);
        }
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('cp-nav'));
    }


    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'cp-nav' => 'cp-nav/navigation/index',
                'cp-nav/navigation/get-hud-html' => 'cp-nav/navigation/getHudHtml',
                'cp-nav/layouts' => 'cp-nav/layout/index',
                'cp-nav/layouts/get-hud-html' => 'cp-nav/layouts/getHudHtml',
                'cp-nav/settings' => 'cp-nav/default/settings',
            ]);
        });
    }

    private function _registerProjectConfigEventListeners(): void
    {
        Craft::$app->getProjectConfig()->onAdd(NavigationsService::CONFIG_NAVIGATION_KEY . '.{uid}', [$this->getNavigations(), 'handleChangedNavigation'])
            ->onUpdate(NavigationsService::CONFIG_NAVIGATION_KEY . '.{uid}', [$this->getNavigations(), 'handleChangedNavigation'])
            ->onRemove(NavigationsService::CONFIG_NAVIGATION_KEY . '.{uid}', [$this->getNavigations(), 'handleDeletedNavigation']);

        Craft::$app->getProjectConfig()->onAdd(LayoutsService::CONFIG_LAYOUT_KEY . '.{uid}', [$this->getLayouts(), 'handleChangedLayout'])
            ->onUpdate(LayoutsService::CONFIG_LAYOUT_KEY . '.{uid}', [$this->getLayouts(), 'handleChangedLayout'])
            ->onRemove(LayoutsService::CONFIG_LAYOUT_KEY . '.{uid}', [$this->getLayouts(), 'handleDeletedLayout']);
    }

    private function _registerTemplateHooks(): void
    {
        // We need to hook into the CP layout to save some global Twig variables, used in our custom navigation Twig template.
        // For Craft, these would already be there, but as we're providing our own template, we need to slot them in.
        // We don't actually output the HTML for the nav here, instead it's added via JS as early as possible.
        Craft::$app->getView()->hook('cp.layouts.base', [$this->getService(), 'renderNavigation']);
    }

}
