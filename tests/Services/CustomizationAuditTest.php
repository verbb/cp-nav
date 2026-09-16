<?php

use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\sources\NavNode;
use verbb\cpnav\upgrade\CustomizationUpgradeService;

it('reports exact stale and missing keys and excludes manual items from stale cleanup', function() {
    $layout = $this->fixtureLayout();
    $sources = CpNav::$plugin->getNavSources();
    $tree = new ReflectionProperty($sources, '_tree');
    $previous = $tree->getValue($sources);
    $this->onCleanup(fn() => $tree->setValue($sources, $previous));
    $tree->setValue($sources, [
        new NavNode('craft:dashboard', 'craft', 'Dashboard', 'dashboard', null, 1, null),
        new NavNode('plugin:cp-nav', 'plugin', 'CP Nav', 'cp-nav', null, 2, null),
    ]);
    $customizations = CpNav::$plugin->getNavCustomization();
    $customizations->setCustomizationNodes($layout->uid, [
        'craft:dashboard' => new CustomizationNode('craft:dashboard', true, 10),
        'plugin:removed' => new CustomizationNode('plugin:removed', true, 20),
        'manual:kept' => new CustomizationNode('manual:kept', true, 30, '', 'Docs', 'manual', 'https://example.test'),
    ]);
    $service = new CustomizationUpgradeService();
    expect($service->auditLayouts($layout->uid))->toBe([[
        'layoutUid' => $layout->uid, 'layoutName' => $layout->name,
        'stale' => ['plugin:removed'], 'missing' => ['plugin:cp-nav'],
    ]]);
    expect($service->purgeStaleKeys($layout->uid, ['plugin:removed']))->toBe(['plugin:removed']);
    expect(array_keys($customizations->getCustomizationForLayout($layout->uid)))->toBe(['craft:dashboard', 'manual:kept']);
});

