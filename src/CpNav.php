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
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;

use yii\base\Event;

class CpNav extends Plugin
{
    // Properties
    // =========================================================================

    public bool $hasCpSettings = true;
    public string $schemaVersion = '2.0.7';
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

        $this->_setPluginComponents();
        $this->_setLogging();
        $this->_registerCpRoutes();
        $this->_registerCpNavItems();
        $this->_registerProjectConfigEventListeners();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
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

    private function _registerCpNavItems(): void
    {
        $request = Craft::$app->getRequest();

        if ($request->isCpRequest) {
            Event::on(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, function(RegisterCpNavItemsEvent $event) {
                // Check to see if the nav needs to be updated
                $this->getService()->checkUpdatedNavItems($event);

                // Check to see if the nav needs to be updated
                $this->getService()->processPendingNavItems($event);

                // Generate our custom nav instead
                $this->getService()->generateNavigation($event);
            });
        }
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

}
