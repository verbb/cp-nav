<?php

namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Navigation as NavigationModel;

use Craft;
use craft\base\Volume;
use craft\elements\Asset;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\Controller;

use \yii\web\Response;

class NavigationController extends Controller
{

    // Public Methods
    // =========================================================================

    /**
     * @return void
     */
    public function actionIndex()
    {
        $layoutId = $this->_getCurrentLayoutId();

        $layouts = CpNav::$plugin->layoutService->getAll();
        $navItems = CpNav::$plugin->navigationService->getByLayoutId($layoutId);

        $this->renderTemplate('cp-nav/index', [
            'layouts'  => $layouts,
            'navItems' => $navItems,
        ]);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $layoutId = $this->_getCurrentLayoutId();

        $request = Craft::$app->getRequest();

        $navIds = Json::decodeIfJson($request->getRequiredBodyParam('ids'));
        $model = false;

        foreach ($navIds as $navOrder => $navId) {
            $model = CpNav::$plugin->navigationService->getById($navId);

            if ($model) {
                $model->order = $navOrder + 1;
                $model = CpNav::$plugin->navigationService->save($model);
            }
        }

        try {
            if ($model && !$model->hasErrors()) {
                $navs = CpNav::$plugin->navigationService->getByLayoutId($layoutId);
                $json = $this->asJson(['success' => true, 'navs' => $navs]);
            } elseif ($model->hasErrors()) {
                $errors = $model->getErrors();
                $json = $this->asJson(['error' => $errors[0]]);
            } else {
                $json = $this->asJson(['error' => 'No navigation model found.']);
            }

            return $json;
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * @return \yii\web\Response
     */
    public function actionToggle(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $layoutId = $this->_getCurrentLayoutId();

        $request = Craft::$app->getRequest();

        $toggle = $request->getRequiredBodyParam('value');
        $navId = $request->getRequiredBodyParam('id');

        $model = CpNav::$plugin->navigationService->getById($navId);

        if ($model) {
            $model->enabled = $toggle;
            CpNav::$plugin->navigationService->save($model);
        }

        try {
            if ($model && !$model->hasErrors()) {
                $navs = CpNav::$plugin->navigationService->getByLayoutId($layoutId);
                $json = $this->asJson(['success' => true, 'navs' => $navs]);
            } elseif ($model->hasErrors()) {
                $errors = $model->getErrors();
                $json = $this->asJson(['error' => $errors[0]]);
            } else {
                $json = $this->asJson(['error' => 'No navigation model found.']);
            }

            return $json;
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * @return \yii\web\Response
     */
    public function actionGetHudHtml(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $layoutId = $this->_getCurrentLayoutId();

        $request = Craft::$app->getRequest();
        $navId = $request->getParam('id');

        if ($navId) {
            $nav = CpNav::$plugin->navigationService->getById($navId);
        } else {
            $nav = new NavigationModel();
            $nav->layoutId = $layoutId;
            $nav->manualNav = true;
        }

        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $sourcesOptions = [];

        /** @var Volume $volume */
        foreach ($volumes as $volume) {
            $sourceOptions[] = [
                'label' => Html::encode($volume->name),
                'value' => $volume->id,
            ];
        }
        
        $variables = [
            'nav'         => $nav,
            'sources'     => $sourcesOptions,
            'elementType' => Asset::class,
        ];

        if ($nav->customIcon) {
            // json decode custom icon id
            $customIconId = json_decode($nav->customIcon)[0];

            $entry = Asset::find()
                ->id($customIconId)
                ->status(null);

            $variables['icons'] = $entry->all();
        }

        $template = $request->getParam('template', 'cp-nav/_includes/navigation-hud');

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
        $layoutId = $this->_getCurrentLayoutId();

        $request = Craft::$app->getRequest();
        $label = $request->getParam('currLabel');
        $url = $request->getParam('url');
        $handle = $request->getParam('handle');
        $newWindow = (bool)$request->getParam('newWindow');

        // json encode custom icon id
        $customIcon = $request->getParam('customIcon') ? json_encode($request->getParam('customIcon')) : null;

        $nav = new NavigationModel();
        $nav->layoutId = $layoutId;
        $nav->handle = $handle;
        $nav->currLabel = $label;
        $nav->prevLabel = $label;
        $nav->enabled = true;
        $nav->order = 99;
        $nav->url = $url;
        $nav->prevUrl = $url;
        $nav->icon = null;
        $nav->customIcon = $customIcon;
        $nav->manualNav = true;
        $nav->newWindow = $newWindow;

        CpNav::$plugin->navigationService->save($nav);

        try {
            if (!$nav->hasErrors()) {
                $navs = CpNav::$plugin->navigationService->getByLayoutId($layoutId);

                return $this->asJson(['success' => true, 'navs' => $navs]);
            }

            $error = '';
            foreach ($nav->getFirstErrors() as $firstError) {
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
        $layoutId = $this->_getCurrentLayoutId();

        $request = Craft::$app->getRequest();
        $navId = $request->getParam('id');
        $model = CpNav::$plugin->navigationService->getById($navId);

        if ($model) {
            $model->currLabel = $request->getParam('currLabel');
            $model->url = $request->getParam('url');
            $model->newWindow = (bool)$request->getParam('newWindow');

            // json encode custom icon id
            $customIcon = $request->getParam('customIcon') ? json_encode($request->getParam('customIcon')) : null;
            $model->customIcon = $customIcon;

            CpNav::$plugin->navigationService->save($model);
        }

        try {
            if ($model && !$model->hasErrors()) {
                $navs = CpNav::$plugin->navigationService->getByLayoutId($layoutId);
                $json = $this->asJson(['success' => true, 'nav' => $model, 'navs' => $navs]);
            } elseif ($model->hasErrors()) {
                $error = '';
                foreach ($model->getFirstErrors() as $firstError) {
                    $error = $firstError;
                    break;
                }
                $json = $this->asJson(['error' => $error]);
            } else {
                $json = $this->asJson(['error' => 'No navigation model found.']);
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
        $layoutId = $this->_getCurrentLayoutId();

        $navId = Craft::$app->getRequest()->getRequiredParam('id');
        $model = CpNav::$plugin->navigationService->getById($navId);

        if ($model) {
            CpNav::$plugin->navigationService->delete($model);
        }

        try {
            if ($model && !$model->hasErrors()) {
                $navs = CpNav::$plugin->navigationService->getByLayoutId($layoutId);
                $json = $this->asJson(['success' => true, 'navs' => $navs]);
            } elseif ($model->hasErrors()) {
                $error = '';
                foreach ($model->getFirstErrors() as $firstError) {
                    $error = $firstError;
                    break;
                }
                $json = $this->asJson(['error' => $error]);
            } else {
                $json = $this->asJson(['error' => 'No navigation model found.']);
            }

            return $json;
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }


    // Private Methods
    // =========================================================================

    private function _getCurrentLayoutId()
    {
        if (Craft::$app->request->getParam('layoutId')) {
            return Craft::$app->request->getParam('layoutId');
        }

        return 1;
    }
}
