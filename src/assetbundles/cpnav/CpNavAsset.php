<?php

namespace verbb\cpnav\assetbundles\CpNav;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class CpNavAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * Initializes the bundle.
     */
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = "@verbb/cpnav/assetbundles/cpnav/dist";

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'js/cp-nav.js',
        ];

        $this->css = [
            'css/cp-nav.css',
        ];

        parent::init();
    }
}
