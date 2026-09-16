<?php

use Tests\Support\ActionRequest;
use verbb\cpnav\CpNav;

it('rejects structurally invalid node data without changing stored configuration', function(array $data) {
    $layout = $this->fixtureLayout();
    $before = Craft::$app->getProjectConfig()->get('cp-nav');
    $response = ActionRequest::dispatch('api/create-node', ['layoutId' => $layout->id, 'data' => array_merge([
        'type' => 'manual', 'currLabel' => 'Audit item', 'url' => 'dashboard',
    ], $data)]);
    expect($response->statusCode)->toBe(400);
    expect(Craft::$app->getProjectConfig()->get('cp-nav'))->toBe($before);
})->with([
    'nested label' => [['currLabel' => ['bad']]],
    'nested icon' => [['icon' => ['bad']]],
    'nested URL' => [['url' => ['bad']]],
    'unsupported type' => [['type' => 'craft']],
    'unknown type with unsafe URL' => [['type' => 'unknown', 'url' => 'javascript:alert(1)']],
    'invalid boolean' => [['newWindow' => 'not-a-boolean']],
]);

it('rejects duplicate unknown and malformed reorder rows without writes', function(array $items) {
    $layout = $this->fixtureLayout();
    $before = Craft::$app->getProjectConfig()->get('cp-nav');
    $response = ActionRequest::dispatch('api/reorder-nodes', ['layoutId' => $layout->id, 'items' => $items]);
    expect($response->statusCode)->toBe(400);
    expect(Craft::$app->getProjectConfig()->get('cp-nav'))->toBe($before);
})->with([
    'duplicate' => [[['key' => 'craft:dashboard'], ['key' => 'craft:dashboard']]],
    'unknown' => [[['key' => 'manual:does-not-exist']]],
    'nested key' => [[['key' => ['craft:dashboard']]]],
    'nested parent' => [[['key' => 'craft:dashboard', 'parentKey' => ['craft:entries']]]],
    'scalar row' => [[42]],
]);

it('interprets a false boolean consistently and refuses a blank manual URL on update', function() {
    $layout = $this->fixtureLayout();
    $api = CpNav::$plugin->getNavBuilderApi();
    $node = $api->createNode($layout->id, ['type' => 'manual', 'currLabel' => 'Manual', 'url' => 'dashboard', 'newWindow' => 'false']);
    expect($node['newWindow'])->toBeFalse();
    expect($api->updateNode($layout->id, $node['key'], ['url' => '']))->toBeFalse();
    expect(CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid)[$node['key']]->url)->toBe('dashboard');
});

it('rejects malformed controller parameters before persistence', function(string $route, array $body) {
    $layout = $this->fixtureLayout();
    $before = Craft::$app->getProjectConfig()->get('cp-nav');
    expect(fn() => ActionRequest::dispatch($route, array_merge(['layoutId' => $layout->id, 'id' => $layout->id], $body)))
        ->toThrow(\yii\web\BadRequestHttpException::class);
    expect(Craft::$app->getProjectConfig()->get('cp-nav'))->toBe($before);
})->with([
    ['api/delete-node', ['key' => ['craft:dashboard']]],
    ['admin/index', ['layoutId' => ['bad']]],
    ['api/reparent-node', ['key' => 'craft:dashboard', 'parentKey' => ['craft:entries']]],
    ['layout/save', ['name' => ['bad']]],
    ['layout/save', ['name' => 'Name', 'permissions' => [['bad']]]],
    ['layout/delete', ['id' => ['bad']]],
    ['layout/reorder', ['ids' => '{broken']],
    ['layout/reorder', ['ids' => [999999]]],
    ['layout/get-hud-html', ['id' => 999999]],
]);

it('renders only the layout HUD and rejects blank or overlong layout names', function() {
    $layout = $this->fixtureLayout();
    $response = ActionRequest::dispatch('layout/get-hud-html', ['id' => $layout->id, 'template' => 'does-not-exist']);
    expect($response->statusCode)->toBe(200);
    expect($response->data['html'])->toContain('name="name"');
    foreach (['', '   ', str_repeat('a', 256)] as $name) {
        $response = ActionRequest::dispatch('layout/new', ['name' => $name]);
        expect($response->statusCode)->toBe(400);
    }
});
