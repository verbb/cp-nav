<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;

use Craft;
use craft\web\Controller;

class DefaultController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings()
    {
        $settings = CpNav::$plugin->getSettings();

        $layouts = CpNav::$plugin->getLayouts()->getAllLayouts();

        return $this->renderTemplate('cp-nav/settings', [
            'settings' => $settings,
            'layouts' => $layouts,
        ]);
    }

}