<?php
namespace verbb\cpnav\base;

use verbb\cpnav\CpNav;
use verbb\cpnav\services\Layouts;
use verbb\cpnav\services\Navigations;
use verbb\cpnav\services\Service;

use verbb\base\LogTrait;
use verbb\base\helpers\Plugin;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static ?CpNav $plugin = null;


    // Traits
    // =========================================================================

    use LogTrait;
    

    // Static Methods
    // =========================================================================

    public static function config(): array
    {
        Plugin::bootstrapPlugin('cp-nav');

        return [
            'components' => [
                'layouts' => Layouts::class,
                'navigations' => Navigations::class,
                'service' => Service::class,
            ],
        ];
    }


    // Public Methods
    // =========================================================================

    public function getLayouts(): Layouts
    {
        return $this->get('layouts');
    }

    public function getNavigations(): Navigations
    {
        return $this->get('navigations');
    }

    public function getService(): Service
    {
        return $this->get('service');
    }

}