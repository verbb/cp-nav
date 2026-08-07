<?php
namespace verbb\cpnav\base;

use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\Plugin as CpNavPluginHelper;
use verbb\cpnav\nav\builder\NavBuilderApi;
use verbb\cpnav\nav\builder\NavBuilderService;
use verbb\cpnav\nav\customization\NavCustomization;
use verbb\cpnav\nav\render\NavPermissions;
use verbb\cpnav\nav\render\NavRenderer;
use verbb\cpnav\nav\resolve\NavResolver;
use verbb\cpnav\nav\sources\NavSourceBuilder;
use verbb\cpnav\nav\sources\NavSources;
use verbb\cpnav\services\Layouts;
use verbb\cpnav\web\assets\builder\BuilderAsset;

use craft\helpers\App;

use nystudio107\pluginvite\services\VitePluginService;

use verbb\base\LogTrait;
use verbb\base\helpers\Plugin;

trait PluginTrait
{
    // Traits
    // =========================================================================

    use LogTrait;


    // Properties
    // =========================================================================

    public static ?CpNav $plugin = null;


    // Static Methods
    // =========================================================================

    public static function config(): array
    {
        Plugin::bootstrapPlugin('cp-nav');

        return [
            'components' => [
                'layouts' => Layouts::class,
                'navBuilder' => NavBuilderService::class,
                'navBuilderApi' => NavBuilderApi::class,
                'navSources' => NavSources::class,
                'navSourceBuilder' => NavSourceBuilder::class,
                'navResolver' => NavResolver::class,
                'navCustomization' => NavCustomization::class,
                'navPermissions' => NavPermissions::class,
                'navRenderer' => NavRenderer::class,
                'vite' => [
                    'class' => VitePluginService::class,
                    'assetClass' => BuilderAsset::class,
                    // Default off: Craft serves built CP dist. Opt in with
                    // CPNAV_USE_VITE_DEV_SERVER=true for plugin-local HMR.
                    'useDevServer' => filter_var(App::parseEnv('$CPNAV_USE_VITE_DEV_SERVER') ?: false, FILTER_VALIDATE_BOOL),
                    'devServerPublic' => rtrim(App::parseEnv('$CPNAV_CP_DEV_SERVER_PUBLIC') ?: 'http://localhost:4021/', '/') . '/',
                    'errorEntry' => 'src/main.tsx',
                    'cacheKeySuffix' => '',
                    'devServerInternal' => rtrim(App::parseEnv('$CPNAV_CP_DEV_SERVER_INTERNAL') ?: 'http://localhost:4021/', '/') . '/',
                    'checkDevServer' => true,
                    'includeReactRefreshShim' => true,
                ],
            ],
        ];
    }


    // Public Methods
    // =========================================================================

    public function getLayouts(): Layouts
    {
        return $this->get('layouts');
    }

    public function getNavBuilder(): NavBuilderService
    {
        return $this->get('navBuilder');
    }

    public function getNavBuilderApi(): NavBuilderApi
    {
        return $this->get('navBuilderApi');
    }

    public function getNavSources(): NavSources
    {
        return $this->get('navSources');
    }

    public function getNavSourceBuilder(): NavSourceBuilder
    {
        return $this->get('navSourceBuilder');
    }

    public function getNavResolver(): NavResolver
    {
        return $this->get('navResolver');
    }

    public function getNavCustomization(): NavCustomization
    {
        return $this->get('navCustomization');
    }

    public function getNavPermissions(): NavPermissions
    {
        return $this->get('navPermissions');
    }

    public function getNavRenderer(): NavRenderer
    {
        return $this->get('navRenderer');
    }

    public function getVite(): VitePluginService
    {
        return $this->get('vite');
    }

    public function registerSidebarAssets(): void
    {
        CpNavPluginHelper::registerSidebarAssets();
    }

    public function registerSettingsAssets(): void
    {
        CpNavPluginHelper::registerSettingsAssets();
    }

    public function registerBuilderAssets(): void
    {
        CpNavPluginHelper::registerBuilderAssets();
    }

}
