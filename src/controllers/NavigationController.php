<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Navigation;
use verbb\cpnav\models\Settings;

use Craft;
use craft\elements\Asset;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\web\Controller;

use yii\web\Response;

class NavigationController extends Controller
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
        $request = Craft::$app->getRequest();
        $layoutId = $request->getParam('layoutId');

        $layouts = CpNav::$plugin->getLayouts()->getAllLayouts();
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId, true);
        $navItems = CpNav::$plugin->getNavigations()->getAllNavigationsByLayoutId($layout->id);

        return $this->renderTemplate('cp-nav/index', [
            'layouts' => $layouts,
            'layout' => $layout,
            'navItems' => $navItems,
        ]);
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $layoutId = $request->getRequiredParam('layoutId');
        $navItems = Json::decodeIfJson($request->getRequiredBodyParam('items'));

        // Fetch all navigations here for performance
        $navigationService = CpNav::$plugin->getNavigations();
        $navigations = $navigationService->getAllNavigationsByLayoutId($layoutId);

        foreach ($navItems as $navOrder => $navItem) {
            $navigation = ArrayHelper::firstWhere($navigations, 'id', $navItem['id']);

            if ($navigation) {
                // Only update if the level, order or parentId has changed
                if ($navOrder != $navigation->sortOrder || $navItem['level'] != $navigation->level || $navItem['parentId'] != $navigation->parentId) {
                    $navigation->parentId = $navItem['parentId'];
                    $navigation->level = $navItem['level'];
                    $navigation->sortOrder = $navOrder;

                    $navigationService->saveNavigation($navigation);
                }
            }
        }

        return $this->asSuccess(Craft::t('cp-nav', 'New position saved.'), [
            'navHtml' => $this->_getNavHtml(),
        ]);
    }

    public function actionToggle(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $toggle = $request->getRequiredBodyParam('value');
        $navId = $request->getRequiredBodyParam('id');

        $navigationService = CpNav::$plugin->getNavigations();
        $navigation = $navigationService->getNavigationById($navId);

        if (!$navigation) {
            return $this->asFailure(Craft::t('cp-nav', 'No navigation model found.'));
        }

        $navigation->enabled = StringHelper::toBoolean($toggle);

        if (!$navigationService->saveNavigation($navigation)) {
            return $this->asModelFailure($navigation, Craft::t('cp-nav', 'Couldn’t save navigation.'), 'navigation');
        }

        return $this->asModelSuccess($navigation, Craft::t('cp-nav', 'Visibility updated.'), 'navigation', [
            'navHtml' => $this->_getNavHtml(),
        ]);
    }

    public function actionGetHudHtml(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $view = Craft::$app->getView();

        $layoutId = $request->getRequiredBodyParam('layoutId');
        $navId = $request->getParam('id');
        $template = $request->getParam('template', 'cp-nav/_includes/navigation-hud');

        if ($navId) {
            $navigation = CpNav::$plugin->getNavigations()->getNavigationById($navId);
        } else {
            $navigation = new Navigation();
            $navigation->layoutId = $layoutId;
            $navigation->type = $request->getParam('type', Navigation::TYPE_MANUAL);
        }

        $sources = [];

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $sources[] = 'volume:' . $volume->uid;
        }

        $variables = [
            'nav' => $navigation,
            'sources' => $sources,
            'elementType' => Asset::class,
        ];

        if ($navigation->customIcon) {
            $customIconId = Json::decode($navigation->customIcon)[0];

            $entry = Asset::find()
                ->id($customIconId)
                ->status(null);

            $variables['icons'] = $entry->all();
        }

        /* @var Settings $settings */
        $settings = CpNav::$plugin->getSettings();
        $variables['settings'] = $settings;

        $view->startJsBuffer();
        $bodyHtml = $view->renderTemplate($template, $variables);
        $footHtml = $view->clearJsBuffer();

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
        $customIcon = $request->getParam('customIcon') ? Json::encode($request->getParam('customIcon')) : null;

        $navigation = new Navigation();
        $navigation->layoutId = $request->getRequiredParam('layoutId');
        $navigation->handle = $request->getParam('handle');
        $navigation->currLabel = $request->getParam('currLabel');
        $navigation->prevLabel = $request->getParam('currLabel');
        $navigation->enabled = true;
        $navigation->level = 1;
        $navigation->url = $request->getParam('url');
        $navigation->prevUrl = $request->getParam('url');
        $navigation->icon = $request->getParam('icon');
        $navigation->customIcon = $customIcon;
        $navigation->type = $request->getParam('type');
        $navigation->newWindow = (bool)$request->getParam('newWindow');

        if (!CpNav::$plugin->getNavigations()->saveNavigation($navigation)) {
            return $this->asModelFailure($navigation, Craft::t('cp-nav', 'Couldn’t create navigation.'), 'navigation');
        }

        return $this->asModelSuccess($navigation, Craft::t('cp-nav', 'Navigation created.'), 'navigation', [
            'navHtml' => $this->_getNavHtml(),
        ]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $navId = $request->getRequiredParam('id');

        $navigationService = CpNav::$plugin->getNavigations();
        $navigation = $navigationService->getNavigationById($navId);

        if (!$navigation) {
            return $this->asFailure(Craft::t('cp-nav', 'No navigation model found.'));
        }

        $navigation->currLabel = $request->getParam('currLabel');
        $navigation->url = $request->getParam('url');
        $navigation->newWindow = (bool)$request->getParam('newWindow');
        $navigation->icon = $request->getParam('icon') ?: $navigation->icon;
        $navigation->subnavBehaviour = $request->getParam('subnavBehaviour');

        $customIcon = $request->getParam('customIcon') ? Json::encode($request->getParam('customIcon')) : null;
        $navigation->customIcon = $customIcon;

        if (!$navigationService->saveNavigation($navigation)) {
            return $this->asModelFailure($navigation, Craft::t('cp-nav', 'Couldn’t save navigation.'), 'navigation');
        }

        return $this->asModelSuccess($navigation, Craft::t('cp-nav', 'Navigation updated.'), 'navigation', [
            'navHtml' => $this->_getNavHtml(),
        ]);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $navId = $request->getRequiredParam('id');

        $navigationService = CpNav::$plugin->getNavigations();
        $navigation = $navigationService->getNavigationById($navId);

        if (!$navigation) {
            return $this->asFailure(Craft::t('cp-nav', 'No navigation model found.'));
        }

        if (!$navigationService->deleteNavigation($navigation)) {
            return $this->asModelFailure($navigation, Craft::t('cp-nav', 'Couldn’t delete navigation.'), 'navigation');
        }

        return $this->asSuccess(Craft::t('cp-nav', 'Navigation deleted.'), [
            'navHtml' => $this->_getNavHtml(),
        ]);
    }

    public function actionReset(): Response
    {
        $request = Craft::$app->getRequest();
        $layoutId = $request->getRequiredParam('layoutId');

        CpNav::$plugin->getService()->resetLayout($layoutId);

        Craft::$app->getSession()->setNotice(Craft::t('cp-nav', 'Navigation reset.'));

        return $this->redirect('cp-nav/settings');
    }


    // Private Methods
    // =========================================================================

    private function _getNavHtml(): ?string
    {
        return CpNav::$plugin->getService()->getNavigationHtml();
    }
}
