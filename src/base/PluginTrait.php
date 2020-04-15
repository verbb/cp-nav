<?php
namespace verbb\cpnav\base;

use verbb\cpnav\CpNav;
use verbb\cpnav\services\LayoutsService;
use verbb\cpnav\services\NavigationsService;
use verbb\cpnav\services\PendingNavigationsService;
use verbb\cpnav\services\Service;

use Craft;
use craft\log\FileTarget;

use yii\log\Logger;

use verbb\base\BaseHelper;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static $plugin;


    // Public Methods
    // =========================================================================

    public function getLayouts()
    {
        return $this->get('layouts');
    }

    public function getNavigations()
    {
        return $this->get('navigations');
    }

    public function getPendingNavigations()
    {
        return $this->get('pendingNavigations');
    }

    public function getService()
    {
        return $this->get('service');
    }

    public static function log($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'cp-nav');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'cp-nav');
    }


    // Private Methods
    // =========================================================================

    private function _setPluginComponents()
    {
        $this->setComponents([
            'layouts' => LayoutsService::class,
            'navigations' => NavigationsService::class,
            'pendingNavigations' => PendingNavigationsService::class,
            'service' => Service::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _setLogging()
    {
        BaseHelper::setFileLogging('cp-nav');
    }

}