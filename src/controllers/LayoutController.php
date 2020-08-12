<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout as LayoutModel;
use verbb\cpnav\models\Navigation as NavigationModel;

use Craft;
use craft\elements\User;
use craft\helpers\Json;
use craft\web\Controller;

use yii\web\Response;

class LayoutController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        $layouts = CpNav::$plugin->getLayouts()->getAllLayouts();

        $this->renderTemplate('cp-nav/layouts', [
            'layouts' => $layouts,
        ]);
    }

    public function actionGetHudHtml(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $layoutId = $request->getParam('id');

        if ($layoutId) {
            $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);
        } else {
            $layout = new LayoutModel();
        }

        $variables = [
            'layout' => $layout,
        ];

        if (Craft::$app->getEdition() == Craft::Solo) {
            $variables['soloAccount'] = User::find()->status(null)->one();
        } else if (Craft::$app->getEdition() == Craft::Pro) {
            $variables['allGroups'] = Craft::$app->userGroups->getAllGroups();
        }

        $template = $request->getParam('template', 'cp-nav/_includes/layout-hud');

        Craft::$app->view->startJsBuffer();
        $bodyHtml = Craft::$app->view->renderTemplate($template, $variables);
        $footHtml = Craft::$app->view->clearJsBuffer();

        return $this->asJson([
            'html'     => $bodyHtml,
            'footerJs' => $footHtml,
        ]);
    }

    public function actionNew(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $layout = new LayoutModel();
        $layout->name = $request->getRequiredParam('name');
        $layout->isDefault = false;
        $layout->permissions = $request->getParam('permissions');

        if (!CpNav::$plugin->getLayouts()->saveLayout($layout)) {
            return $this->asJson(['error' => $this->_getErrorString($layout)]);
        }

        CpNav::$plugin->getService()->populateOriginalNavigationItems($layout->id);

        return $this->asJson(['success' => true, 'layouts' => $layout]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $layoutId = $request->getRequiredParam('id');
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return $this->asJson(['error' => Craft::t('cp-nav', 'No layout model found.')]);
        }

        $layout->name = $request->getRequiredParam('name');
        $layout->isDefault = false;
        $layout->permissions = $request->getParam('permissions');

        if (!CpNav::$plugin->getLayouts()->saveLayout($layout)) {
            return $this->asJson(['error' => $this->_getErrorString($layout)]);
        }

        return $this->asJson(['success' => true, 'layout' => $layout]);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $layoutId = $request->getRequiredParam('id');
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return $this->asJson(['error' => Craft::t('cp-nav', 'No layout model found.')]);
        }

        if (!CpNav::$plugin->getLayouts()->deleteLayout($layout)) {
            return $this->asJson(['error' => $this->_getErrorString($layout)]);
        }

        $layouts = CpNav::$plugin->getLayouts()->getAllLayouts();

        return $this->asJson(['success' => true, 'layouts' => $layouts]);
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutIds = Json::decode($this->request->getRequiredBodyParam('ids'));
        CpNav::$plugin->getLayouts()->reorderLayouts($layoutIds);

        return $this->asJson(['success' => true]);
    }
    

    // Private Methods
    // =========================================================================

    private function _getErrorString($object)
    {
        return $object->getErrorSummary(true)[0] ?? '';
    }
}
