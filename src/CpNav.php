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

        // Register our CP routes
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, [$this, 'registerCpUrlRules']);

        // Setup default Layouts and Nav items
        Event::on(Plugins::class, Plugins::EVENT_AFTER_INSTALL_PLUGIN, function(PluginEvent $event) {
            if ($event->plugin === $this) {
                $this->cpNavService->setupDefaults();
            }
        });

        // Old modifyCpNav hook as event
        Event::on(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, function(RegisterCpNavItemsEvent $event) {
            // Don't run the plugins custom menu during a migration
            if (Craft::$app->getRequest()->getUrl() == '/actions/update/updateDatabase') {
                return true;
            }

            if (Craft::$app->request->isCpRequest) {
                $this->cpNavService->modifyCpNav($event->navItems);
            }
        });
    }

    public function registerCpUrlRules(RegisterUrlRulesEvent $event)
    {
        $rules = [
            'cp-nav' => 'cp-nav/navigation/index',
            'cp-nav/navigation/get-hud-html' => 'cp-nav/navigation/getHudHtml',
            'cp-nav/layouts' => 'cp-nav/layout/index',
            'cp-nav/layouts/get-hud-html' => 'cp-nav/layouts/getHudHtml',
        ];

        $event->rules = array_merge($event->rules, $rules);
    }

    public function getSettingsResponse()
    {
        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('cp-nav'));
    }
}
