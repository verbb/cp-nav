<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\Plugin as CpNavPluginHelper;
use verbb\cpnav\models\Layout;

use Craft;
use craft\elements\User;
use craft\helpers\Json;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
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
        $layoutId = $this->_id(false);

        if ($layoutId) {
            $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);
            if (!$layout) {
                throw new BadRequestHttpException('Invalid navigation layout.');
            }
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

        $view->startJsBuffer();
        $bodyHtml = $view->renderTemplate('cp-nav/_includes/layout-hud', $variables);
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
        $layout->name = $this->_name();
        $layout->isDefault = false;
        $layout->permissions = $this->_permissions();

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

        $layoutId = $this->_id();
        $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$layout) {
            return $this->asFailure(Craft::t('cp-nav', 'No layout model found.'));
        }

        $layout->name = $this->_name();
        $layout->permissions = $this->_permissions();

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

        $layoutIds = Json::decodeIfJson($this->request->getRequiredBodyParam('ids'));
        if (!is_array($layoutIds) || !array_is_list($layoutIds)) {
            throw new BadRequestHttpException('Invalid navigation layouts.');
        }
        $seen = [];
        foreach ($layoutIds as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if (!$id || isset($seen[$id]) || !CpNav::$plugin->getLayouts()->getLayoutById($id)) {
                throw new BadRequestHttpException('Invalid navigation layouts.');
            }
            $seen[$id] = true;
        }
        CpNav::$plugin->getLayouts()->reorderLayouts($layoutIds);

        return $this->asSuccess();
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_id();

        if (!CpNav::$plugin->getLayouts()->deleteLayoutById($layoutId)) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t delete layout.'));
        }

        return $this->asSuccess();
    }

    public function actionDuplicate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_id();
        $source = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);

        if (!$source) {
            return $this->asFailure(Craft::t('cp-nav', 'No layout model found.'));
        }

        // Reserve room for the translated copy label without changing the source name.
        $copySuffix = Craft::t('cp-nav', '{name} copy', ['name' => '']);
        $sourceName = mb_substr((string)$source->name, 0, max(0, Layout::MAX_NAME_LENGTH - mb_strlen($copySuffix)));
        $name = $this->_name(Craft::t('cp-nav', '{name} copy', [
            'name' => $sourceName,
        ]));

        $layout = CpNav::$plugin->getLayouts()->duplicateLayout($source, $name);

        if (!$layout) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t save layout.'));
        }

        return $this->asModelSuccess($layout, Craft::t('cp-nav', '{layout} saved.', [
            'layout' => $layout->name,
        ]), 'layout');
    }


    // Private Methods
    // =========================================================================

    private function _id(bool $required = true): ?int
    {
        $value = $required ? $this->request->getRequiredParam('id') : $this->request->getParam('id');
        if (!$required && ($value === null || $value === '')) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            throw new BadRequestHttpException('Invalid navigation layout.');
        }

        return $id;
    }

    private function _name(?string $default = null): string
    {
        $name = $default === null ? $this->request->getRequiredParam('name') : $this->request->getParam('name', $default);
        if (!is_string($name)) {
            throw new BadRequestHttpException('Invalid layout name.');
        }

        return $name;
    }

    private function _permissions(): array
    {
        $permissions = $this->request->getParam('permissions') ?: [];
        if (!is_array($permissions) || !array_is_list($permissions)) {
            throw new BadRequestHttpException('Invalid layout permissions.');
        }
        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                throw new BadRequestHttpException('Invalid layout permissions.');
            }
        }

        return $permissions;
    }
}
