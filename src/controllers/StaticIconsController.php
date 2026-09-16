<?php
namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;

use Craft;
use craft\web\Controller;

use yii\web\NotFoundHttpException;
use yii\web\Response;

class StaticIconsController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        $this->requireCpRequest();

        return parent::beforeAction($action);
    }

    public function actionIndex(): Response
    {
        $this->requireAcceptsJson();
        $this->requireAdmin();

        $query = $this->request->getQueryParam('q');

        return $this->asJson([
            'options' => CpNav::$plugin->getStaticIcons()->getOptions(is_string($query) ? $query : null),
            'iconsPath' => CpNav::$plugin->getSettings()->iconsPath,
        ]);
    }

    public function actionView(): Response
    {
        $file = $this->request->getRequiredQueryParam('file');
        if (!is_string($file)) {
            throw new NotFoundHttpException('Icon not found.');
        }
        $path = CpNav::$plugin->getStaticIcons()->resolveAbsolutePath($file);

        if ($path === null) {
            throw new NotFoundHttpException('Icon not found.');
        }

        $response = Craft::$app->getResponse();
        // SVGs are images, even when opened directly as a same-origin document.
        $response->getHeaders()->set('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'");
        $response->getHeaders()->set('X-Content-Type-Options', 'nosniff');

        return $response->sendFile($path, null, [
            'inline' => true,
        ]);
    }
}
