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
use craft\web\twig\variables\Cp;

use yii\web\Response;

class NavigationController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action)
    {   
        // Are we trying to load the index page? Check we have defaults setup
        if ($action->actionMethod === 'actionIndex') {
            $request = Craft::$app->getRequest();
            $layoutId = $request->getParam('layoutId', 1);

            $navItems = CpNav::$plugin->getNavigations()->getNavigationsByLayoutId($layoutId);

            if (!$navItems) {
                CpNav::$plugin->getService()->populateOriginalNavigationItems($layoutId);
            }
        }

        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $request = Craft::$app->getRequest();

        $layoutId = $request->getParam('layoutId', 1);

        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);
        $layouts = CpNav::$plugin->getLayouts()->getAllLayouts();
        $navItems = CpNav::$plugin->getNavigations()->getNavigationsByLayoutId($layoutId);

        return $this->renderTemplate('cp-nav/index', [
            'layouts'  => $layouts,
            'layout'  => $layout,
            'navItems' => $navItems,
        ]);
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $layoutId = $request->getParam('layoutId', 1);
        $navIds = Json::decodeIfJson($request->getRequiredBodyParam('ids'));

        foreach ($navIds as $navOrder => $navId) {
            $navigation = CpNav::$plugin->getNavigations()->getNavigationById($navId);

            if ($navigation) {
                $navigation->order = $navOrder + 1;

                $navigation = CpNav::$plugin->getNavigations()->saveNavigation($navigation);
            }
        }
        
        return $this->asJson(['success' => true, 'navHtml' => $this->_getNavHtml()]);
    }

    public function actionToggle(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $layoutId = $request->getParam('layoutId', 1);
        $toggle = $request->getRequiredBodyParam('value');
        $navId = $request->getRequiredBodyParam('id');

        $navigation = CpNav::$plugin->getNavigations()->getNavigationById($navId);

        if (!$navigation) {
            return $this->asJson(['error' => 'No navigation model found.']);
        }

        $navigation->enabled = $toggle;

        if (!CpNav::$plugin->getNavigations()->saveNavigation($navigation)) {
            return $this->asJson(['error' => $this->_getErrorString($navigation)]);
        }

        return $this->asJson(['success' => true, 'nav' => $navigation, 'navHtml' => $this->_getNavHtml()]);
    }

    public function actionGetHudHtml(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $layoutId = $request->getParam('layoutId', 1);
        $navId = $request->getParam('id');

        if ($navId) {
            $navigation = CpNav::$plugin->getNavigations()->getNavigationById($navId);
        } else {
            $navigation = new NavigationModel();
            $navigation->layoutId = $layoutId;
            $navigation->type = NavigationModel::TYPE_MANUAL;
        }

        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $sourcesOptions = [];

        foreach ($volumes as $volume) {
            $sourceOptions[] = [
                'label' => Html::encode($volume->name),
                'value' => $volume->id,
            ];
        }
        
        $variables = [
            'nav' => $navigation,
            'sources' => $sourcesOptions,
            'elementType' => Asset::class,
        ];

        if ($navigation->customIcon) {
            // json decode custom icon id
            $customIconId = json_decode($navigation->customIcon)[0];

            $entry = Asset::find()
                ->id($customIconId)
                ->status(null);

            $variables['icons'] = $entry->all();
        }

        $template = $request->getParam('template', 'cp-nav/_includes/navigation-hud');

        Craft::$app->view->startJsBuffer();
        $bodyHtml = Craft::$app->view->renderTemplate($template, $variables);
        $footHtml = Craft::$app->view->clearJsBuffer();

        return $this->asJson([
            'bodyHtml' => $bodyHtml,
            'footHtml' => $footHtml,
        ]);
    }

    public function actionNew(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        // json encode custom icon id
        $customIcon = $request->getParam('customIcon') ? json_encode($request->getParam('customIcon')) : null;

        $navigation = new NavigationModel();
        $navigation->layoutId = $request->getParam('layoutId', 1);
        $navigation->handle = $request->getParam('handle');
        $navigation->currLabel = $request->getParam('currLabel');
        $navigation->prevLabel = $request->getParam('currLabel');
        $navigation->enabled = true;
        $navigation->order = 99;
        $navigation->url = $request->getParam('url');
        $navigation->prevUrl = $request->getParam('url');
        $navigation->icon = $request->getParam('icon', null);
        $navigation->customIcon = $customIcon;
        $navigation->type = $request->getParam('type');
        $navigation->newWindow = (bool)$request->getParam('newWindow');

        if (!CpNav::$plugin->getNavigations()->saveNavigation($navigation)) {
            return $this->asJson(['error' => $this->_getErrorString($navigation)]);
        }

        return $this->asJson(['success' => true, 'nav' => $navigation, 'navHtml' => $this->_getNavHtml()]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $layoutId = $request->getParam('layoutId', 1);
        $navId = $request->getParam('id');

        $navigation = CpNav::$plugin->getNavigations()->getNavigationById($navId);

        if (!$navigation) {
            return $this->asJson(['error' => 'No navigation model found.']);
        }

        $navigation->currLabel = $request->getParam('currLabel');
        $navigation->url = $request->getParam('url');
        $navigation->newWindow = (bool)$request->getParam('newWindow');
        $navigation->icon = $request->getParam('icon', $navigation->icon);

        // json encode custom icon id
        $customIcon = $request->getParam('customIcon') ? json_encode($request->getParam('customIcon')) : null;
        $navigation->customIcon = $customIcon;

        if (!CpNav::$plugin->getNavigations()->saveNavigation($navigation)) {
            return $this->asJson(['error' => $this->_getErrorString($navigation)]);
        }

        return $this->asJson(['success' => true, 'nav' => $navigation, 'navHtml' => $this->_getNavHtml()]);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $layoutId = $request->getParam('layoutId', 1);

        $navId = Craft::$app->getRequest()->getRequiredParam('id');
        $navigation = CpNav::$plugin->getNavigations()->getNavigationById($navId);

        if (!$navigation) {
            return $this->asJson(['error' => 'No navigation model found.']);
        }

        if (!CpNav::$plugin->getNavigations()->deleteNavigation($navigation)) {
            return $this->asJson(['error' => $this->_getErrorString($navigation)]);
        }

        return $this->asJson(['success' => true, 'navHtml' => $this->_getNavHtml()]);
    }

    public function actionReset()
    {
        $request = Craft::$app->getRequest();
        $layoutId = $request->getRequiredParam('layoutId');

        $navigations = CpNav::$plugin->getNavigations()->getNavigationsByLayoutId($layoutId);

        $errors = [];

        foreach ($navigations as $navigation) {
            if (!CpNav::$plugin->getNavigations()->deleteNavigation($navigation)) {
                $errors[] = $this->_getErrorString($navigation);
            }
        }

        if ($errors) {
            Craft::$app->getSession()->setError(Craft::t('cp-nav', 'Couldn’t reset layout - ' . $errors[0]));

            return null;
        }

        CpNav::$plugin->getService()->populateOriginalNavigationItems($layoutId);

        Craft::$app->getSession()->setNotice(Craft::t('cp-nav', 'Reset navigation.'));

        return $this->redirect('cp-nav/settings');
    }


    // Private Methods
    // =========================================================================

    private function _getNavHtml()
    {
        return Craft::$app->view->renderTemplate('cp-nav/_layouts/navs');
    }
    
    private function _getErrorString($object)
    {
        return $object->getErrorSummary(true)[0] ?? '';
    }
}
