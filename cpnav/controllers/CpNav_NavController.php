<?php
namespace Craft;

class CpNav_NavController extends BaseController
{
    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        $layoutId = $this->_getCurrentLayoutId();

        $navs = craft()->cpNav_nav->getByLayoutId($layoutId);
        $layouts = craft()->cpNav_layout->getAll();

        $this->renderTemplate('cpNav', array(
            'navItems' => $navs,
            'layouts' => $layouts,
        ));
    }

    public function actionReorder()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        $layoutId = $this->_getCurrentLayoutId();

        $navIds = JsonHelper::decode(craft()->request->getRequiredPost('ids'));

        foreach ($navIds as $navOrder => $navId) {
            $model = craft()->cpNav_nav->getById($navId);
            $model->order = $navOrder+1;

            craft()->cpNav_nav->save($model);
        }

        $navs = craft()->cpNav_nav->getByLayoutId($layoutId);

        $this->returnJson(array('success' => true, 'navs' => $navs));
    }

    public function actionToggle()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        $layoutId = $this->_getCurrentLayoutId();

        $toggle = craft()->request->getRequiredPost('value');
        $navId = craft()->request->getRequiredPost('id');

        $model = craft()->cpNav_nav->getById($navId);
        $model->enabled = $toggle;

        craft()->cpNav_nav->save($model);

        $navs = craft()->cpNav_nav->getByLayoutId($layoutId);

        $this->returnJson(array('success' => true, 'navs' => $navs));
    }

    public function actionGetHudHtml()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        $layoutId = $this->_getCurrentLayoutId();

        $navId = craft()->request->getPost('id');

        if ($navId) {
            $nav = craft()->cpNav_nav->getById($navId);
        } else {
            $nav = new CpNav_NavModel();
            $nav->layoutId = $layoutId;
            $nav->manualNav = true;
        }

        $variables = array(
            'nav' => $nav,
            'sources' => craft()->assetSources->getAllSources(),
        );

        if ($nav->customIcon) {
            $criteria = craft()->elements->getCriteria(ElementType::Asset);
            $criteria->id = $nav->customIcon;
            $criteria->status = null;
            $criteria->localeEnabled = null;

            $variables['icons'] = $criteria->find();
        }

        $template = craft()->request->getPost('template', 'cpnav/_includes/navigation-hud');

        craft()->templates->startJsBuffer();
        $bodyHtml = $this->renderTemplate($template, $variables, true);
        $footHtml = craft()->templates->clearJsBuffer();

        $this->returnJson(array(
            'html' => $bodyHtml,
            'footerJs' => $footHtml,
        ));
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $navId = craft()->request->getPost('id');
        $nav = craft()->cpNav_nav->getById($navId);
    
        $nav->currLabel = craft()->request->getPost('currLabel');
        $nav->url = craft()->request->getPost('url');
        $nav->newWindow = (bool)craft()->request->getPost('newWindow');
        $nav->customIcon = craft()->request->getPost('customIcon');

        craft()->cpNav_nav->save($nav);

        $this->returnJson(array('success' => true, 'nav' => $nav));
    }

    public function actionNew()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        $layoutId = $this->_getCurrentLayoutId();

        $id = craft()->request->getPost('id');
        $label = craft()->request->getPost('currLabel');
        $url = craft()->request->getPost('url');
        $handle = craft()->request->getPost('handle');
        $newWindow = (bool)craft()->request->getPost('newWindow');

        $nav = new CpNav_NavModel();
        $nav->layoutId = $layoutId;
        $nav->handle = $handle;
        $nav->currLabel = $label;
        $nav->prevLabel = $label;
        $nav->enabled = true;
        $nav->order = 99;
        $nav->url = $url;
        $nav->prevUrl = $url;
        $nav->icon = null;
        $nav->customIcon = null;
        $nav->manualNav = true;
        $nav->newWindow = $newWindow;

        craft()->cpNav_nav->save($nav);

        $navs = craft()->cpNav_nav->getByLayoutId($layoutId);

        $this->returnJson(array('success' => true, 'navs' => $navs));
    }

    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        $layoutId = $this->_getCurrentLayoutId();

        $navId = craft()->request->getRequiredPost('id');
        $nav = craft()->cpNav_nav->getById($navId);

        craft()->cpNav_nav->delete($nav);

        $navs = craft()->cpNav_nav->getByLayoutId($layoutId);

        $this->returnJson(array('success' => true, 'navs' => $navs));
    }



    // Private Methods
    // =========================================================================

    private function _getCurrentLayoutId()
    {
        if (craft()->request->getParam('layoutId')) {
            return craft()->request->getParam('layoutId');
        } else if (craft()->request->getPost('layoutId')) {
            return craft()->request->getPost('layoutId');
        } else {
            return 1;
        }
    }
}