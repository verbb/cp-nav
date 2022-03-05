<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;

use craft\web\Controller;

use yii\web\Response;

class DefaultController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings(): Response
    {
        $settings = CpNav::$plugin->getSettings();

        $layouts = CpNav::$plugin->getLayouts()->getAllLayouts();

        return $this->renderTemplate('cp-nav/settings', [
            'settings' => $settings,
            'layouts' => $layouts,
        ]);
    }

}