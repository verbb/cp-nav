<?php
namespace verbb\cpnav\base;

use verbb\cpnav\CpNav;
use verbb\cpnav\services\LayoutsService;
use verbb\cpnav\services\NavigationsService;
use verbb\cpnav\services\Service;
use verbb\base\BaseHelper;

use Craft;

use yii\log\Logger;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static CpNav $plugin;


    // Static Methods
    // =========================================================================

    public static function log(string $message, array $params = []): void
    {
        $message = Craft::t('cp-nav', $message, $params);

        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'cp-nav');
    }

    public static function error(string $message, array $params = []): void
    {
        $message = Craft::t('cp-nav', $message, $params);

        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'cp-nav');
    }


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

    public function getService(): Service
    {
        return $this->get('service');
    }


    // Private Methods
    // =========================================================================

    private function _registerComponents(): void
    {
        $this->setComponents([
            'layouts' => LayoutsService::class,
            'navigations' => NavigationsService::class,
            'service' => Service::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _registerLogTarget(): void
    {
        BaseHelper::setFileLogging('cp-nav');
    }

}