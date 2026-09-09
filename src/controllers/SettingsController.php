<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Settings;

use craft\web\Controller;

use yii\web\Response;

class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $this->requireAdmin();

        /* @var Settings $settings */
        $settings = CpNav::$plugin->getSettings();

        return $this->renderTemplate('cp-nav/settings/index', [
            'settings' => $settings,
        ]);
    }
}
