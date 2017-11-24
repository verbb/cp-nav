<?php

namespace verbb\cpnav;

use verbb\cpnav\services\LayoutService;
use verbb\cpnav\services\NavigationService;
use verbb\cpnav\services\CpNavService;

use Craft;
use craft\base\Plugin;
use craft\events\PluginEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Plugins;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;

use yii\base\Event;

/**
 * @property  LayoutService     $layoutService
 * @property  NavigationService $navigationService
 * @property  CpNavService      $cpNavService
 */
class CpNav extends Plugin
{

    // Static Properties
    // =========================================================================

    /**
     * @var CpNav
     */
    public static $plugin;


    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register Components (Services)
        $this->setComponents([
            'layoutService'     => LayoutService::class,
            'navigationService' => NavigationService::class,
            'cpNavService'      => CpNavService::class,
        ]);

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['cp-nav'] = 'cp-nav/navigation/index';
                $event->rules['cp-nav/navigation/get-hud-html'] = 'cp-nav/navigation/getHudHtml';
                $event->rules['cp-nav/layouts'] = 'cp-nav/layout/index';
                $event->rules['cp-nav/layouts/get-hud-html'] = 'cp-nav/layouts/getHudHtml';
            }
        );

        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {

                    // Setup default Layouts and Nav items
                    $this->cpNavService->setupDefaults();
                }
            }
        );

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


    // Protected Methods
    // =========================================================================

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate('cp-nav/settings');
    }
}
