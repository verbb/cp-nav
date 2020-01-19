<?php
namespace verbb\cpnav\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

use verbb\base\assetbundles\CpAsset as VerbbCpAsset;

class CpNavSettingsAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->sourcePath = "@verbb/cpnav/resources/dist";

        $this->depends = [
            VerbbCpAsset::class,
            CpAsset::class,
        ];

        $this->js = [
            'js/cp-nav-settings.js',
        ];

        $this->css = [
            'css/cp-nav-settings.css',
        ];

        parent::init();
    }
}
