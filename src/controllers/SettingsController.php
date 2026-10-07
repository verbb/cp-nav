<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Settings;

use yii\web\Response;

use verbb\base\controllers\SettingsController as BaseSettingsController;

class SettingsController extends BaseSettingsController
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        $this->requireAdmin();

        return parent::beforeAction($action);
    }

    public function actionIndex(): Response
    {
        /* @var Settings $settings */
        $settings = CpNav::$plugin->getSettings();

        return $this->renderTemplate('cp-nav/settings/index', [
            'settings' => $settings,
        ]);
    }
}
