<?php
namespace Craft;

class CpNavController extends BaseController
{
    public function actionGetNavsForLayout()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $layoutId = craft()->request->getRequiredPost('layoutId');

        $variables = array(
            'navItems' => craft()->cpNav_nav->getNavsByLayoutId($layoutId),
            'namespace' => 'settings',
        );

        $returnData['html'] = $this->renderTemplate('cpnav/settings/table', $variables, true);

        $this->returnJson($returnData);
    }

    public function actionGetNavHtml()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $navId = craft()->request->getRequiredPost('id');
        $nav = craft()->cpNav_nav->getNavById($navId);

        $variables = array(
            'nav' => $nav,
        );

        $returnData['html'] = $this->renderTemplate('cpnav/settings/_editor', $variables, true);

        $this->returnJson($returnData);
    }

    public function actionReorderNav()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $navIds = JsonHelper::decode(craft()->request->getRequiredPost('ids'));
        $navs = craft()->cpNav_nav->reorderNav($navIds);

        $this->returnJson(array('success' => true, 'navs' => $navs));
    }

    public function actionToggleNav()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $toggle = craft()->request->getRequiredPost('value');
        $navId = craft()->request->getRequiredPost('id');
        $navs = craft()->cpNav_nav->toggleNav($navId, $toggle);

        $this->returnJson(array('success' => true, 'navs' => $navs));
    }

    public function actionNew()
    {
        $this->requirePostRequest();

        $settings = craft()->request->getRequiredPost('settings');
        $layoutId = $settings['layoutId'];
        $label = $settings['label'];
        $handle = $settings['handle'];
        $url = $settings['url'];

        $variables = array(
            'layoutId' => $layoutId,
            'handle' => strtolower($label),
            'label' => $label,
            'url' => $url,
        );

        $nav = craft()->cpNav_nav->createNav($variables, true);

        craft()->userSession->setNotice(Craft::t('Menu item added.'));

        $this->redirectToPostedUrl();
    }

    public function actionDeleteNav()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $navId = craft()->request->getRequiredPost('id');
        $nav = craft()->cpNav_nav->getNavById($navId);

        $navs = craft()->cpNav_nav->deleteNav($nav);

        $this->returnJson(array('success' => true, 'navs' => $navs));
    }

    public function actionSaveNav()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $navId = craft()->request->getRequiredPost('id');
        $nav = craft()->cpNav_nav->getNavById($navId);

        $nav->currLabel = craft()->request->getRequiredPost('currLabel');
        $nav->url = craft()->request->getRequiredPost('url');

        $nav = craft()->cpNav_nav->saveNav($nav);

        $this->returnJson(array('success' => true, 'nav' => $nav));
    }

    public function actionRestore()
    {
        $this->requirePostRequest();

        $settings = craft()->request->getRequiredPost('settings');
        $layoutId = $settings['layoutId'];
        
        craft()->cpNav_nav->restoreDefaults($layoutId);

        $this->redirectToPostedUrl();
    }

}