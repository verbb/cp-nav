<?php
namespace verbb\cpnav\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class CpNavAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->sourcePath = "@verbb/cpnav/resources/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/cp-nav.js',
        ];

        $this->css = [
            'css/cp-nav.css',
        ];

        parent::init();
    }
}
