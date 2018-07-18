<?php
namespace verbb\cpnav\base;

use verbb\cpnav\services\LayoutService;
use verbb\cpnav\services\NavigationService;
use verbb\cpnav\services\CpNavService;

use Craft;

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

}