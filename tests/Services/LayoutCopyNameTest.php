<?php

use craft\helpers\Json;
use Tests\Support\ActionRequest;
use verbb\cpnav\CpNav;

it('generates a valid copy name for every supported source length', function(string $name) {
    $source = $this->fixtureLayout(['name' => $name, 'permissions' => ['solo']]);
    expect(CpNav::$plugin->getNavBuilderApi()->updateNode($source->id, 'craft:dashboard', ['currLabel' => 'Copied dashboard']))->toBeTrue();
    $this->nextConfigRequest();
    $nodes = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($source->uid);
    $response = ActionRequest::dispatch('layout/duplicate', ['id' => $source->id]);
    expect($response->statusCode)->toBe(200);
    $data = Json::decode(Json::encode($response->data));
    $copy = CpNav::$plugin->getLayouts()->getLayoutById($data['layout']['id']);
    $this->onCleanup(fn() => CpNav::$plugin->getLayouts()->deleteLayout($copy));
    expect($copy->name)->toBe(mb_substr($name, 0, 250) . ' copy');
    expect(mb_strlen($copy->name))->toBeLessThanOrEqual(255);
    expect($copy->permissions)->toBe(['solo']);
    expect(CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($copy->uid))->toEqual($nodes);
    expect(CpNav::$plugin->getLayouts()->getLayoutById($source->id)->name)->toBe($name);
})->with([str_repeat('A', 250), str_repeat('A', 251), str_repeat('A', 255), str_repeat('界', 255)]);

it('preserves explicit duplicate names and rejects invalid explicit names', function() {
    $source = $this->fixtureLayout();
    $response = ActionRequest::dispatch('layout/duplicate', ['id' => $source->id, 'name' => 'Chosen copy name']);
    expect($response->statusCode)->toBe(200);
    $data = Json::decode(Json::encode($response->data));
    $copy = CpNav::$plugin->getLayouts()->getLayoutById($data['layout']['id']);
    $this->onCleanup(fn() => CpNav::$plugin->getLayouts()->deleteLayout($copy));
    expect($copy->name)->toBe('Chosen copy name');
    $response = ActionRequest::dispatch('layout/duplicate', ['id' => $source->id, 'name' => str_repeat('A', 256)]);
    expect($response->statusCode)->toBe(400);
});
