<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\Response;

class ApiController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        $this->requireAdmin();

        return parent::beforeAction($action);
    }

    public function actionLayoutTree(): Response
    {
        $this->requireAcceptsJson();

        $layoutId = $this->_layoutId();

        return $this->asJson(CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId));
    }

    public function actionUpdateNode(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_layoutId();
        $nodeKey = $this->_nodeKey();
        $data = $this->_arrayBodyParam('data');

        if (!CpNav::$plugin->getNavBuilderApi()->updateNode($layoutId, $nodeKey, $data)) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t save navigation.'));
        }

        return $this->asSuccess(Craft::t('cp-nav', 'Navigation updated.'), [
            'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
        ]);
    }

    public function actionCreateNode(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_layoutId();
        $data = $this->_arrayBodyParam('data');
        $node = CpNav::$plugin->getNavBuilderApi()->createNode($layoutId, $data);

        if (!$node) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t create navigation.'));
        }

        return $this->asSuccess(Craft::t('cp-nav', 'Navigation created.'), [
            'node' => $node,
            'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
        ]);
    }

    public function actionDeleteNode(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_layoutId();
        $nodeKey = $this->_nodeKey();

        if (!CpNav::$plugin->getNavBuilderApi()->deleteNode($layoutId, $nodeKey)) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t delete navigation.'));
        }

        return $this->asSuccess(Craft::t('cp-nav', 'Navigation deleted.'), [
            'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
        ]);
    }

    public function actionReorderNodes(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_layoutId();
        $items = $this->_arrayBodyParam('items');

        if (!CpNav::$plugin->getNavBuilderApi()->reorderNodes($layoutId, $items)) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t reorder navigation.'));
        }

        return $this->asSuccess(Craft::t('cp-nav', 'New position saved.'), [
            'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
        ]);
    }

    public function actionIndentNode(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_layoutId();
        $nodeKey = $this->_nodeKey();

        if (!CpNav::$plugin->getNavBuilderApi()->indentNode($layoutId, $nodeKey)) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t indent navigation item.'));
        }

        return $this->asSuccess(Craft::t('cp-nav', 'New position saved.'), [
            'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
        ]);
    }

    public function actionOutdentNode(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_layoutId();
        $nodeKey = $this->_nodeKey();

        if (!CpNav::$plugin->getNavBuilderApi()->outdentNode($layoutId, $nodeKey)) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t outdent navigation item.'));
        }

        return $this->asSuccess(Craft::t('cp-nav', 'New position saved.'), [
            'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
        ]);
    }

    public function actionReparentNode(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_layoutId();
        $nodeKey = $this->_nodeKey();
        // Explicit null = promote to root; omit vs empty string handled by request.
        $parentKey = $this->request->getBodyParam('parentKey');

        if ($parentKey !== null && !is_string($parentKey)) {
            throw new BadRequestHttpException('Invalid navigation parent.');
        }
        $parentKey = $parentKey === '' || $parentKey === null ? null : (string)$parentKey;

        if (!CpNav::$plugin->getNavBuilderApi()->reparentNode($layoutId, $nodeKey, $parentKey)) {
            return $this->asFailure(Craft::t('cp-nav', 'Couldn’t reparent navigation item.'));
        }

        return $this->asSuccess(Craft::t('cp-nav', 'New position saved.'), [
            'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
        ]);
    }

    public function actionRefreshSources(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        CpNav::$plugin->getNavBuilderApi()->refreshSources();

        return $this->asSuccess(Craft::t('cp-nav', 'Menu items refreshed.'));
    }

    public function actionAcknowledgeNewItems(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = $this->_layoutId();

        CpNav::$plugin->getNavBuilderApi()->acknowledgeNewItems($layoutId);

        return $this->asSuccess(Craft::t('cp-nav', 'New menu items acknowledged.'), [
            'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
        ]);
    }

    public function actionResetLayout(): Response
    {
        $this->requirePostRequest();

        $layoutId = $this->_layoutId();

        CpNav::$plugin->getNavBuilderApi()->resetLayout($layoutId);
        // Clear request-local registry memo so the returned/redirected tree is fresh.
        CpNav::$plugin->getNavSources()->invalidate();

        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess(Craft::t('cp-nav', 'Navigation reset.'), [
                'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('cp-nav', 'Navigation reset.'));

        // Non-JSON (legacy form) posts land on the builder — Reset lives in the header only.
        return $this->redirect('cp-nav');
    }


    // Private Methods
    // =========================================================================

    private function _layoutId(): int
    {
        $id = filter_var($this->request->getRequiredParam('layoutId'), FILTER_VALIDATE_INT);

        if (!$id || !CpNav::$plugin->getLayouts()->getLayoutById($id)) {
            throw new BadRequestHttpException('Invalid navigation layout.');
        }

        return $id;
    }

    private function _nodeKey(): string
    {
        $key = $this->request->getRequiredParam('key');

        if (!is_string($key) || $key === '') {
            throw new BadRequestHttpException('Invalid navigation key.');
        }

        return $key;
    }

    private function _arrayBodyParam(string $name): array
    {
        $value = Json::decodeIfJson($this->request->getRequiredBodyParam($name));

        if (!is_array($value)) {
            throw new BadRequestHttpException('Invalid navigation payload.');
        }

        return $value;
    }
}
