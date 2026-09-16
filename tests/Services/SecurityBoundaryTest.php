<?php

use craft\elements\User;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\controllers\AdminController;
use verbb\cpnav\controllers\ApiController;
use verbb\cpnav\controllers\LayoutController;
use yii\base\Action;
use yii\web\ForbiddenHttpException;

it('rejects non-admin access before executing management actions', function(string $class) {
    CpRequestContext::activate();
    $previous = Craft::$app->getUser()->getIdentity();
    Craft::$app->getUser()->setIdentity(new User(['id' => 999999, 'admin' => false]));
    try {
        $controller = new $class('audit', CpNav::$plugin);
        expect(fn() => $controller->beforeAction(new Action('index', $controller)))->toThrow(ForbiddenHttpException::class);
        expect($controller->enableCsrfValidation)->toBeTrue();
    } finally {
        Craft::$app->getUser()->setIdentity($previous);
    }
})->with([AdminController::class, ApiController::class, LayoutController::class]);

it('honours disabled administrative changes even for admins', function(string $class) {
    AdminUser::login();
    CpRequestContext::activate();
    $general = Craft::$app->getConfig()->getGeneral();
    $previous = $general->allowAdminChanges;
    $general->allowAdminChanges = false;
    try {
        $controller = new $class('audit', CpNav::$plugin);
        expect(fn() => $controller->beforeAction(new Action('index', $controller)))->toThrow(ForbiddenHttpException::class);
    } finally {
        $general->allowAdminChanges = $previous;
    }
})->with([AdminController::class, ApiController::class, LayoutController::class]);

it('enforces real mutation request authentication CSRF verbs and persistence', function() {
    $layout = $this->fixtureLayout();
    $body = ['layoutId' => $layout->id, 'key' => 'craft:dashboard', 'data' => ['currLabel' => 'Saved home']];
    $pc = Craft::$app->getProjectConfig();
    $before = $pc->get('cp-nav');
    $editor = $this->fixtureEditor();
    Craft::$app->getUser()->setIdentity($editor);
    expect(fn() => \Tests\Support\ActionRequest::dispatch('api/update-node', $body))->toThrow(ForbiddenHttpException::class);
    AdminUser::login();
    expect(fn() => \Tests\Support\ActionRequest::dispatch('api/update-node', $body, 'POST', false))->toThrow(\yii\web\BadRequestHttpException::class);
    expect(fn() => \Tests\Support\ActionRequest::dispatch('api/update-node', $body, 'GET'))->toThrow(\yii\web\MethodNotAllowedHttpException::class);
    expect($pc->get('cp-nav'))->toBe($before);
    $response = \Tests\Support\ActionRequest::dispatch('api/update-node', $body);
    expect($response->statusCode)->toBe(200);
    expect(CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid)['craft:dashboard']->label)->toBe('Saved home');
});

it('rejects malformed mutation payloads and unknown layouts without writing', function(string $action, array $body) {
    $layout = $this->fixtureLayout();
    $before = Craft::$app->getProjectConfig()->get('cp-nav');
    expect(fn() => \Tests\Support\ActionRequest::dispatch($action, array_merge(['layoutId' => $layout->id, 'key' => 'craft:dashboard'], $body)))
        ->toThrow(\yii\web\BadRequestHttpException::class);
    expect(Craft::$app->getProjectConfig()->get('cp-nav'))->toBe($before);
})->with([
    ['api/update-node', ['data' => 'not-json']],
    ['api/create-node', ['data' => 42]],
    ['api/reorder-nodes', ['items' => '{broken']],
    ['api/create-node', ['layoutId' => 999999, 'data' => ['type' => 'manual', 'url' => 'dashboard']]],
]);

it('keeps default metadata and reports a rejected default deletion through the controller', function() {
    $default = CpNav::$plugin->getLayouts()->getDefaultLayout();
    $response = \Tests\Support\ActionRequest::dispatch('layout/save', ['id' => $default->id, 'name' => 'Default renamed']);
    expect($response->statusCode)->toBe(200);
    expect(CpNav::$plugin->getLayouts()->getDefaultLayout()?->uid)->toBe($default->uid);
    expect(CpNav::$plugin->getLayouts()->getDefaultLayout()?->name)->toBe('Default renamed');
    $stale = clone $default;
    $stale->isDefault = false;
    expect(CpNav::$plugin->getLayouts()->saveLayout($stale))->toBeTrue();
    expect($stale->isDefault)->toBeTrue();
    $response = \Tests\Support\ActionRequest::dispatch('layout/delete', ['id' => $default->id]);
    expect($response->statusCode)->toBe(400);
    expect(CpNav::$plugin->getLayouts()->getLayoutById($default->id))->not->toBeNull();
});

it('requires an admin for the actual settings action', function() {
    Craft::$app->getUser()->setIdentity($this->fixtureEditor());
    expect(fn() => \Tests\Support\ActionRequest::dispatch('settings/index', [], 'GET'))->toThrow(\yii\web\ForbiddenHttpException::class);
});
