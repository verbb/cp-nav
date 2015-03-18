<?php
namespace Craft;

class CpNav_LayoutController extends BaseController
{
    public function actionGetLayoutHtml()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $layoutId = craft()->request->getPost('id');

        $layout = craft()->cpNav_layout->getLayoutById($layoutId);

        $variables = array(
            'layout' => $layout,
        );

        $returnData['html'] = $this->renderTemplate('cpNav/settings/_editorlayout', $variables, true);

        $this->returnJson($returnData);
    }

    public function actionNewLayout()
    {
        $this->requirePostRequest();

        $name = craft()->request->getRequiredPost('name');

        $variables = array(
            'name' => $name,
        );

        $layout = craft()->cpNav_layout->createLayout($variables, true);

        craft()->userSession->setNotice(Craft::t('Layout created.'));

        $this->returnJson(array('success' => true, 'layouts' => $layout));
    }

    public function actionDeleteLayout()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $layoutId = craft()->request->getRequiredPost('id');
        $layout = craft()->cpNav_layout->getLayoutById($layoutId);

        $layouts = craft()->cpNav_layout->deleteLayout($layout);

        $this->returnJson(array('success' => true, 'layouts' => $layouts));
    }

    public function actionSaveLayout()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $layoutId = craft()->request->getRequiredPost('id');
        $layout = craft()->cpNav_layout->getLayoutById($layoutId);

        $layout->name = craft()->request->getRequiredPost('name');

        $layout = craft()->cpNav_layout->saveLayout($layout);

        $this->returnJson(array('success' => true, 'layout' => $layout));
    }

}