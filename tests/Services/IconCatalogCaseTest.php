<?php

use verbb\cpnav\CpNav;

it('offers supported SVG extension casing and preserves the selected icon', function(string $filename) {
    $dir = $this->fixtureIcons();
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M0 0h16v16z"/></svg>';
    file_put_contents($dir . '/' . $filename, $svg);
    $icons = CpNav::$plugin->getStaticIcons();
    expect($icons->resolveAbsolutePath($filename))->toBe(realpath($dir . '/' . $filename));
    expect(array_column($icons->getOptions(), 'value'))->toContain($filename);

    $layout = $this->fixtureLayout();
    $api = CpNav::$plugin->getNavBuilderApi();
    expect($api->updateNode($layout->id, 'craft:dashboard', ['customIcon' => $filename]))->toBeTrue();
    $this->nextConfigRequest();
    $node = array_values(array_filter($api->getLayoutTree($layout->id)['nodes'], fn($node) => $node['key'] === 'craft:dashboard'))[0];
    expect($node['customIcon'])->toBe($filename);
    expect($node['customIconPreview']['url'])->not->toBeNull();

    Craft::$app->getRequest()->setQueryParams(['file' => $filename]);
    $controller = new \verbb\cpnav\controllers\StaticIconsController('static-icons', CpNav::$plugin);
    $response = $controller->runAction('view');
    rewind($response->stream[0]);
    expect(stream_get_contents($response->stream[0]))->toBe($svg);
    fclose($response->stream[0]);
})->with(['logo.SVG', 'brand/mark.SvG']);
