<?php
namespace Craft;

class CpNav_LayoutController extends BaseController
{
    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        $layouts = craft()->cpNav_layout->getAll();

        $this->renderTemplate('cpNav/layouts', array(
            'layouts' => $layouts,
        ));
    }

    public function actionGetHudHtml()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $layoutId = craft()->request->getPost('id');

        if ($layoutId) {
            $layout = craft()->cpNav_layout->getById($layoutId);
        } else {
            $layout = new CpNav_LayoutModel();
        }

        $variables = array(
            'layout' => $layout,
        );

        if (craft()->getEdition() == Craft::Client) {
            $variables['clientAccount'] = craft()->users->getClient();
        } else if (craft()->getEdition() == Craft::Pro) {
            $variables['allGroups'] = craft()->userGroups->getAllGroups();
        }

        $template = craft()->request->getPost('template', 'cpnav/_includes/layout-hud');

        $returnData['html'] = $this->renderTemplate($template, $variables, true);

        $this->returnJson($returnData);
    }

    public function actionNew()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $layout = new CpNav_LayoutModel();
        $layout->name = craft()->request->getRequiredPost('name');
        $layout->permissions = craft()->request->getPost('permissions');

        craft()->cpNav_layout->save($layout);

        // Make sure we setup default nav items for this new layout
        craft()->cpNav->setupDefaults($layout->id);

        $this->returnJson(array('success' => true, 'layouts' => $layout));
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $layoutId = craft()->request->getRequiredPost('id');
        $layout = craft()->cpNav_layout->getById($layoutId);

        $layout->name = craft()->request->getRequiredPost('name');
        $layout->permissions = craft()->request->getPost('permissions');

        $layout = craft()->cpNav_layout->save($layout);

        $this->returnJson(array('success' => true, 'layout' => $layout));
    }

    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $layoutId = craft()->request->getRequiredPost('id');
        $layout = craft()->cpNav_layout->getById($layoutId);

        craft()->cpNav_layout->delete($layout);

        $layouts = craft()->cpNav_layout->getAll();

        $this->returnJson(array('success' => true, 'layouts' => $layouts));
    }


    // Private Methods
    // =========================================================================

    private function _getAllUsers()
    {
        $records = UserRecord::model()->findAll();

        if ($records) {
            return UserModel::populateModels($records);
        }

        return null;
    }
}