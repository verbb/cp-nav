<?php

use verbb\cpnav\CpNav;

it('only offers icon values that survive saving and resolve for preview', function() {
    $dir = $this->fixtureIcons();
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"/>';
    file_put_contents($dir . '/logo alternate.svg', $svg);
    file_put_contents($dir . '/brand/mark alternate.svg', $svg);
    $icons = CpNav::$plugin->getStaticIcons();
    $options = $icons->getOptions();
    expect(array_column($options, 'value'))->toContain('brand/mark.svg');
    $layout = $this->fixtureLayout();
    $api = CpNav::$plugin->getNavBuilderApi();

    foreach ($options as $option) {
        expect($api->updateNode($layout->id, 'craft:dashboard', ['customIcon' => $option['value']]))->toBeTrue();
        $this->nextConfigRequest();
        $saved = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid)['craft:dashboard'];
        expect($saved->customIcon)->toBe($option['value']);
        expect($icons->resolveAbsolutePath($option['value']))->not->toBeNull();
        expect($icons->resolveUrl($option['value']))->not->toBeNull();
    }
});
