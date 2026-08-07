<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;

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

        $layoutId = (int)$this->request->getRequiredParam('layoutId');

        return $this->asJson(CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId));
    }

    public function actionUpdateNode(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $layoutId = (int)$this->request->getRequiredParam('layoutId');
        $nodeKey = (string)$this->request->getRequiredParam('key');
        $data = Json::decodeIfJson($this->request->getRequiredBodyParam('data'));

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

        $layoutId = (int)$this->request->getRequiredParam('layoutId');
        $data = Json::decodeIfJson($this->request->getRequiredBodyParam('data'));
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

        $layoutId = (int)$this->request->getRequiredParam('layoutId');
        $nodeKey = (string)$this->request->getRequiredParam('key');

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

        $layoutId = (int)$this->request->getRequiredParam('layoutId');
        $items = Json::decodeIfJson($this->request->getRequiredBodyParam('items'));

        if (!CpNav::$plugin->getNavBuilderApi()->reorderNodes($layoutId, is_array($items) ? $items : [])) {
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

        $layoutId = (int)$this->request->getRequiredParam('layoutId');
        $nodeKey = (string)$this->request->getRequiredParam('key');

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

        $layoutId = (int)$this->request->getRequiredParam('layoutId');
        $nodeKey = (string)$this->request->getRequiredParam('key');

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

        $layoutId = (int)$this->request->getRequiredParam('layoutId');
        $nodeKey = (string)$this->request->getRequiredParam('key');
        // Explicit null = promote to root; omit vs empty string handled by request.
        $parentKey = $this->request->getBodyParam('parentKey');
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

        $layoutId = (int)$this->request->getRequiredParam('layoutId');

        CpNav::$plugin->getNavBuilderApi()->acknowledgeNewItems($layoutId);

        return $this->asSuccess(Craft::t('cp-nav', 'New menu items acknowledged.'), [
            'tree' => CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layoutId),
        ]);
    }

    public function actionResetLayout(): Response
    {
        $this->requirePostRequest();

        $layoutId = (int)$this->request->getRequiredParam('layoutId');

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
}
