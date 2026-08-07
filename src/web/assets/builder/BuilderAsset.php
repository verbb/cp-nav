<?php
namespace verbb\cpnav\web\assets\builder;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

use verbb\base\assetbundles\CpAsset as VerbbCpAsset;

/**
 * Vite dist owner for all CP Nav CP entries (builder / settings / sidebar).
 * Manifest entries are registered via {@see \verbb\cpnav\helpers\Plugin}.
 */
class BuilderAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/cpnav/web/assets/builder/dist';

        $this->depends = [
            VerbbCpAsset::class,
            CraftCpAsset::class,
        ];

        parent::init();
    }
}
