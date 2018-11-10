<?php
namespace verbb\cpnav;

use verbb\cpnav\base\PluginTrait;

use Craft;
use craft\base\Plugin;
use craft\events\PluginEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Plugins;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;

use yii\base\Event;

class CpNav extends Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        $this->_setPluginComponents();
        $this->_setLogging();
        $this->_registerCpRoutes();
        $this->_registerCpNavItems();
    }

    public function getSettingsResponse()
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('cp-nav'));
    }


    // Private Methods
    // =========================================================================

    private function _registerCpRoutes()
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'cp-nav' => 'cp-nav/navigation/index',
                'cp-nav/navigation/get-hud-html' => 'cp-nav/navigation/getHudHtml',
                'cp-nav/layouts' => 'cp-nav/layout/index',
                'cp-nav/layouts/get-hud-html' => 'cp-nav/layouts/getHudHtml',
            ]);
        });
    }

    private function _registerCpNavItems()
    {
        Event::on(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, function(RegisterCpNavItemsEvent $event) {
            $request = Craft::$app->getRequest();

            // Don't run the plugins custom menu for console requests
            if ($request->getIsConsoleRequest()) {
                return;
            }

            // Don't run the plugins custom menu during a migration
            if ($request->getUrl() == '/actions/update/updateDatabase') {
                return;
            }

            if ($request->isCpRequest) {
                $this->cpNavService->modifyCpNav($event->navItems);
            }
        });
    }

}
