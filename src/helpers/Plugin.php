<?php
namespace verbb\cpnav\helpers;

use verbb\cpnav\CpNav;
use verbb\cpnav\web\assets\builder\BuilderAsset;
use verbb\cpnav\web\assets\settings\SettingsAsset;
use verbb\cpnav\web\assets\sidebar\SidebarAsset;

use Craft;

class Plugin
{
    // Static Methods
    // =========================================================================

    public static function registerSidebarAssets(): void
    {
        Craft::$app->getView()->registerAssetBundle(SidebarAsset::class);
    }

    public static function registerSettingsAssets(): void
    {
        Craft::$app->getView()->registerAssetBundle(SettingsAsset::class);
    }

    public static function registerBuilderAssets(): void
    {
        Craft::$app->getView()->registerAssetBundle(BuilderAsset::class);

        self::registerAsset('src/main.tsx');
    }

    public static function registerAsset(string $path): void
    {
        $viteService = CpNav::$plugin->getVite();

        $scriptOptions = [
            'depends' => [
                BuilderAsset::class,
            ],
            'onload' => '',
        ];

        $styleOptions = [
            'depends' => [
                BuilderAsset::class,
            ],
        ];

        $viteService->register($path, false, $scriptOptions, $styleOptions);

        if ($viteService->devServerRunning()) {
            $viteService->register('@vite/client', false);
        }
    }

    public static function isDebug(): bool
    {
        return CpNav::$plugin->getVite()->devServerRunning();
    }
}
