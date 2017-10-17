<?php
/**
 * CP Nav plugin for Craft CMS 3.x
 *
 * Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation.
 *
 * @link      http://verbb.io
 * @copyright Copyright (c) 2017 Verbb
 * @author    Verbb
 * @package   CpNav
 */

namespace verbb\cpnav;

use verbb\cpnav\services\LayoutService;
use verbb\cpnav\services\Navigation as NavigationService;
use verbb\cpnav\services\CpNav as CpNavService;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;

use yii\base\Event;

/**
 * @property  LayoutService $layoutService
 * @property  NavigationService $navigation
 * @property  CpNavService $cpNav
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

        // Register our site routes
//        Event::on(
//            UrlManager::class,
//            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
//            function (RegisterUrlRulesEvent $event) {
//                $event->rules['siteActionTrigger1'] = 'cp-nav/layout';
//                $event->rules['siteActionTrigger2'] = 'cp-nav/navigation';
//            }
//        );

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['cp-nav'] = 'cp-nav/navigation/index';
                $event->rules['cp-nav/layouts'] = 'cp-nav/layout/index';
            }
        );

        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {

                    // Setup default Layouts and Nav items
                    $this->cpNav->setupDefaults();
                }
            }
        );

        /**
         * Logging in Craft involves using one of the following methods:
         *
         * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
         * Craft::info(): record a message that conveys some useful information.
         * Craft::warning(): record a warning message that indicates something unexpected has happened.
         * Craft::error(): record a fatal error that should be investigated as soon as possible.
         *
         * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
         *
         * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
         * the category to the method (prefixed with the fully qualified class name) where the constant appears.
         *
         * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
         * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
         *
         * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
         */
        Craft::info(
            Craft::t(
                'cp-nav',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
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
