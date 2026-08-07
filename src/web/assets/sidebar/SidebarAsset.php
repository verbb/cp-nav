<?php
namespace verbb\cpnav\web\assets\sidebar;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

use verbb\base\assetbundles\CpAsset as VerbbCpAsset;

/**
 * Global CP sidebar chrome — owns `sidebar/dist` from `vite.sidebar.config.ts`.
 */
class SidebarAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/cpnav/web/assets/sidebar/dist';

        $this->depends = [
            VerbbCpAsset::class,
            CraftCpAsset::class,
        ];

        $this->js = [
            ['js/sidebar.js', 'type' => 'module'],
        ];

        $this->css = [
            'css/sidebar.css',
        ];

        parent::init();
    }
}
