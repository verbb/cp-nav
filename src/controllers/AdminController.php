<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\Plugin as CpNavPluginHelper;

use craft\web\Controller;

use yii\web\Response;

class AdminController extends Controller
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
        $layoutId = $this->request->getParam('layoutId');

        $layouts = CpNav::$plugin->getLayouts()->getAllLayouts();
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId, true);

        CpNavPluginHelper::registerBuilderAssets();

        return $this->renderTemplate('cp-nav/admin', [
            'layouts' => $layouts,
            'layout' => $layout,
            'layoutOptions' => array_map(
                fn($item) => ['id' => $item->id, 'name' => $item->name],
                $layouts,
            ),
        ]);
    }
}
