<?php

namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout as LayoutModel;
use verbb\cpnav\models\Navigation as NavigationModel;

use Craft;
use craft\elements\User;
use craft\web\Controller;

use yii\web\Response;

class LayoutController extends Controller
{

    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        $layouts = CpNav::$plugin->layoutService->getAll();

        $this->renderTemplate('cp-nav/layouts', [
            'layouts' => $layouts,
        ]);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionGetHudHtml(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $layoutId = $request->getParam('id');

        if ($layoutId) {
            $layout = CpNav::$plugin->layoutService->getById($layoutId);
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

        try {
            return $this->asJson([
                'html'     => $bodyHtml,
                'footerJs' => $footHtml,
            ]);
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * @return \yii\web\Response
     */
    public function actionNew(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();

        $layout = new LayoutModel();
        $layout->name = $request->getRequiredParam('name');
        $layout->permissions = json_encode($request->getParam('permissions'));

        CpNav::$plugin->layoutService->save($layout);

        if (!$layout->hasErrors()) {
            // Copy default layout navigation

            /** @var NavigationModel $nav */
            foreach (CpNav::$plugin->navigationService->getByLayoutId(1) as $nav) {

                $model = new NavigationModel([
                    'layoutId'   => $layout->id,
                    'handle'     => $nav->handle,
                    'prevLabel'  => $nav->prevLabel,
                    'currLabel'  => $nav->currLabel,
                    'enabled'    => $nav->enabled,
                    'order'      => $nav->order,
                    'prevUrl'    => $nav->prevUrl,
                    'url'        => $nav->url,
                    'icon'       => $nav->icon,
                    'customIcon' => $nav->customIcon,
                    'manualNav'  => $nav->manualNav,
                    'newWindow'  => $nav->newWindow,
                ]);

                CpNav::$plugin->navigationService->save($model);
            }
        }

        try {
            if (!$layout->hasErrors()) {
                return $this->asJson(['success' => true, 'layouts' => $layout]);
            }

            $error = '';
            foreach ($layout->getFirstErrors() as $firstError) {
                $error = $firstError;
                break;
            }

            return $this->asJson(['error' => $error]);
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * @return \yii\web\Response
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();

        $layoutId = $request->getRequiredParam('id');
        $model = CpNav::$plugin->layoutService->getById($layoutId);

        if ($model) {
            $model->name = $request->getRequiredParam('name');
            $model->permissions = json_encode($request->getParam('permissions'));

            CpNav::$plugin->layoutService->save($model);
        }

        try {
            if ($model && !$model->hasErrors()) {
                $json = $this->asJson(['success' => true, 'layout' => $model]);
            } elseif ($model->hasErrors()) {
                $error = '';
                foreach ($model->getFirstErrors() as $firstError) {
                    $error = $firstError;
                    break;
                }
                $json = $this->asJson(['error' => $error]);
            } else {
                $json = $this->asJson(['error' => 'No layout model found.']);
            }

            return $json;
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * @return \yii\web\Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();

        $layoutId = $request->getRequiredParam('id');
        $model = CpNav::$plugin->layoutService->getById($layoutId);

        if ($model) {
            CpNav::$plugin->layoutService->delete($model);
        }

        try {
            if ($model && !$model->hasErrors()) {
                $layouts = CpNav::$plugin->layoutService->getAll();
                $json = $this->asJson(['success' => true, 'layouts' => $layouts]);
            } elseif ($model->hasErrors()) {
                $error = '';
                foreach ($model->getFirstErrors() as $firstError) {
                    $error = $firstError;
                    break;
                }
                $json = $this->asJson(['error' => $error]);
            } else {
                $json = $this->asJson(['error' => 'No layout model found.']);
            }

            return $json;
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }
}
