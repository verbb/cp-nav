<?php
namespace verbb\cpnav\base;

use verbb\cpnav\CpNav;
use verbb\cpnav\services\LayoutService;
use verbb\cpnav\services\NavigationService;
use verbb\cpnav\services\CpNavService;

use Craft;
use craft\log\FileTarget;

use yii\log\Logger;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static $plugin;


    // Public Methods
    // =========================================================================

    public function getLayoutService()
    {
        return $this->get('layoutService');
    }

    public function getNavigationService()
    {
        return $this->get('navigationService');
    }

    public function getCpNavService()
    {
        return $this->get('cpNavService');
    }

    private function _setPluginComponents()
    {
        $this->setComponents([
            'layoutService' => LayoutService::class,
            'navigationService' => NavigationService::class,
            'cpNavService' => CpNavService::class,
        ]);
    }

    private function _setLogging()
    {
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/cp-nav.log'),
            'categories' => ['cp-nav'],
        ]);
    }

    public static function log($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'cp-nav');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'cp-nav');
    }

}