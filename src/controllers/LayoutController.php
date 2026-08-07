<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\Plugin as CpNavPluginHelper;
use verbb\cpnav\models\Layout;

use Craft;
use craft\elements\User;
use craft\helpers\Json;
use craft\web\Controller;

use yii\web\Response;

class LayoutController extends Controller
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
        $layouts = CpNav::$plugin->getLayouts()->getAllLayouts();

        CpNavPluginHelper::registerSettingsAssets();

        return $this->renderTemplate('cp-nav/layouts', [
            'layouts' => $layouts,
        ]);
    }

    public function actionGetHudHtml(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $view = Craft::$app->getView();
        $layoutId = $this->request->getParam('id');

        if ($layoutId) {
            $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);
        } else {
            $layout = new Layout();
        }

        $variables = [
            'layout' => $layout,
        ];

        if (Craft::$app->getEdition() === Craft::Solo) {
            $variables['soloAccount'] = User::find()->status(null)->one();
        } else {
            $variables['allGroups'] = Craft::$app->userGroups->getAllGroups();
        }

        $template = $this->request->getParam('template', 'cp-nav/_includes/layout-hud');

        $view->startJsBuffer();
        $bodyHtml = $view->renderTemplate($template, $variables);
        $footHtml = $view->clearJsBuffer();

        return $this->asJson([
            'html' => $bodyHtml,
            'footerJs' => $footHtml,
        ]);
    }

    public function actionNew(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layout = new Layout();
        $layout->name = $this->request->getRequiredParam('name');
        $layout->isDefault = false;
        $layout->permissions = $this->request->getParam('permissions') ?: [];

        if (!CpNav::$plugin->getLayouts()->saveLayout($layout)) {
            return $this->asModelFailure($layout, Craft::t('cp-nav', 'Couldn’t save layout.'), 'layout');
        }

        // New layouts start with an empty overlay (registry default-position insert at render).
        CpNav::$plugin->getNavBuilder()->resetLayout($layout->id);

        return $this->asModelSuccess($layout, Craft::t('cp-nav', '{layout} saved.', [
            'layout' => $layout->name,
        ]), 'layout');
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->request->getRequiredParam('id');
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return $this->asFailure(Craft::t('cp-nav', 'No layout model found.'));
        }

        $layout->name = $this->request->getRequiredParam('name');
        $layout->isDefault = false;
        $layout->permissions = $this->request->getParam('permissions') ?: [];

        if (!CpNav::$plugin->getLayouts()->saveLayout($layout)) {
            return $this->asModelFailure($layout, Craft::t('cp-nav', 'Couldn’t save layout.'), 'layout');
        }

        return $this->asModelSuccess($layout, Craft::t('cp-nav', '{layout} saved.', [
            'layout' => $layout->name,
        ]), 'layout');
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutIds = Json::decode($this->request->getRequiredBodyParam('ids'));
        CpNav::$plugin->getLayouts()->reorderLayouts($layoutIds);

        return $this->asSuccess();
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->request->getRequiredBodyParam('id');

        CpNav::$plugin->getLayouts()->deleteLayoutById($layoutId);

        return $this->asSuccess();
    }

    public function actionDuplicate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = (int)$this->request->getRequiredBodyParam('id');
        $source = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$source) {
            return $this->asFailure(Craft::t('cp-nav', 'No layout model found.'));
        }

        $name = (string)$this->request->getParam('name', Craft::t('cp-nav', '{name} copy', [
            'name' => $source->name,
        ]));

        $layout = CpNav::$plugin->getLayouts()->duplicateLayout($source, $name);

        if (!$layout) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t save layout.'));
        }

        return $this->asModelSuccess($layout, Craft::t('cp-nav', '{layout} saved.', [
            'layout' => $layout->name,
        ]), 'layout');
    }
}
