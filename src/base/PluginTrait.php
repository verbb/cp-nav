<?php
namespace verbb\cpnav\base;

use verbb\cpnav\CpNav;
use verbb\cpnav\services\LayoutsService;
use verbb\cpnav\services\NavigationsService;
use verbb\cpnav\services\PendingNavigationsService;
use verbb\cpnav\services\Service;

use Craft;

use yii\log\Logger;

use verbb\base\BaseHelper;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static CpNav $plugin;


    // Public Methods
    // =========================================================================

    public function getLayouts(): LayoutsService
    {
        return $this->get('layouts');
    }

    public function getNavigations(): NavigationsService
    {
        return $this->get('navigations');
    }

    public function getPendingNavigations(): PendingNavigationsService
    {
        return $this->get('pendingNavigations');
    }

    public function getService(): Service
    {
        return $this->get('service');
    }

    public static function log($message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'cp-nav');
    }

    public static function error($message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'cp-nav');
    }


    // Private Methods
    // =========================================================================

    private function _setPluginComponents(): void
    {
        $this->setComponents([
            'layouts' => LayoutsService::class,
            'navigations' => NavigationsService::class,
            'pendingNavigations' => PendingNavigationsService::class,
            'service' => Service::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _setLogging(): void
    {
        BaseHelper::setFileLogging('cp-nav');
    }

}