<?php
namespace verbb\cpnav\web\assets\settings;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

use verbb\base\assetbundles\CpAsset as VerbbCpAsset;

/**
 * Layouts settings page — owns `settings/dist` from `vite.settings.config.ts`.
 */
class SettingsAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/cpnav/web/assets/settings/dist';

        $this->depends = [
            VerbbCpAsset::class,
            CraftCpAsset::class,
        ];

        $this->js = [
            ['js/settings.js', 'type' => 'module'],
        ];

        $this->css = [
            'css/settings.css',
        ];

        parent::init();
    }
}
