<?php

use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;

it('duplicates all customization values metadata and acknowledgements independently', function() {
    $source = $this->fixtureLayout(['name' => 'Editors', 'permissions' => ['group-b', 'group-a']]);
    $service = CpNav::$plugin->getNavCustomization();
    $nodes = [
        'craft:dashboard' => new CustomizationNode('craft:dashboard', false, 30, '', 'Home', null, null, 'fontIcon:gauge'),
        'manual:docs' => new CustomizationNode('manual:docs', true, 10, '', 'Documentation', 'manual', 'https://example.test/docs', null, 'brand/mark.svg', true),
        'manual:child' => new CustomizationNode('manual:child', false, 20, 'manual:docs', 'Child', 'manual', 'users'),
        'divider:section' => new CustomizationNode('divider:section', true, 40, '', 'Resources', 'divider'),
    ];
    $service->setCustomizationNodes($source->uid, $nodes);
    $service->setAcknowledgedRegistryKeys($source->uid, ['craft:dashboard', 'plugin:cp-nav']);
    $copy = CpNav::$plugin->getLayouts()->duplicateLayout($source, 'Editors copy');
    expect($copy)->not->toBeNull();
    $this->onCleanup(fn() => CpNav::$plugin->getLayouts()->deleteLayout($copy));
    expect($copy->uid)->not->toBe($source->uid);
    expect($copy->name)->toBe('Editors copy');
    expect($copy->permissions)->toBe(['group-b', 'group-a']);
    expect($copy->isDefault)->toBeFalse();
    expect($service->getCustomizationForLayout($copy->uid))->toEqual($nodes);
    expect($service->getAcknowledgedRegistryKeys($copy->uid))->toBe(['craft:dashboard', 'plugin:cp-nav']);
    $service->saveNode($copy->uid, new CustomizationNode('manual:docs', label: 'Changed copy', type: 'manual', url: 'dashboard'));
    $service->removeNode($copy->uid, 'manual:child');
    expect($service->getCustomizationForLayout($source->uid))->toEqual($nodes);
});
