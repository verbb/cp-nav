<?php

use craft\events\RegisterCpNavItemsEvent;
use Tests\Support\ConfigWrites;
use verbb\cpnav\CpNav;

it('detects newly available items after creating and editing a layout without a reset', function() {
    $available = false;
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) use (&$available) {
        $event->navItems = [['label' => 'Dashboard', 'url' => 'dashboard']];
        if ($available) {
            $event->navItems[] = ['label' => 'Reports', 'url' => 'cp-nav/reports', 'subnav' => [
                'contentBlocks' => ['label' => 'Blocks', 'url' => 'cp-nav/reports/blocks'],
            ]];
        }
    });
    $layout = $this->fixtureLayout();
    $api = CpNav::$plugin->getNavBuilderApi();
    expect($api->getLayoutTree($layout->id)['meta']['newItemCount'])->toBe(0);
    expect($api->updateNode($layout->id, 'craft:dashboard', ['currLabel' => 'Editorial home']))->toBeTrue();
    $this->nextConfigRequest();
    $available = true;
    CpNav::$plugin->getNavSources()->invalidate();
    $writes = new ConfigWrites();
    try {
        $tree = $api->getLayoutTree($layout->id);
        expect($tree['meta']['newItemCount'])->toBe(2);
        expect(array_column(array_values(array_filter($tree['nodes'], fn($node) => $node['isNew'])), 'key'))
            ->toBe(['plugin:cp-nav/reports', 'plugin:cp-nav/reports:contentBlocks']);
        expect($writes->paths)->toBe([]);
    } finally {
        $writes->close();
    }
    $api->acknowledgeNewItems($layout->id);
    $this->nextConfigRequest();
    expect($api->getLayoutTree($layout->id)['meta']['newItemCount'])->toBe(0);
});

it('initializes upgraded layouts without changing saved nodes or existing acknowledgements', function() {
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [['label' => 'Dashboard', 'url' => 'dashboard']];
    });
    $missing = $this->fixtureLayout();
    $acknowledged = $this->fixtureLayout();
    $customization = CpNav::$plugin->getNavCustomization();
    $api = CpNav::$plugin->getNavBuilderApi();
    expect($api->updateNode($missing->id, 'craft:dashboard', ['currLabel' => 'Editorial home']))->toBeTrue();
    $nodes = $customization->getCustomizationForLayout($missing->uid);
    $customization->setAcknowledgedRegistryKeys($acknowledged->uid, ['craft:entries']);
    Craft::$app->getProjectConfig()->remove($customization->acknowledgedRegistryKeysPath($missing->uid));
    $this->nextConfigRequest();
    $migration = new \verbb\cpnav\migrations\m260916_130000_new_item_baseline();
    expect($migration->safeUp())->toBeTrue();
    expect($customization->getAcknowledgedRegistryKeys($missing->uid))->toBe(['craft:dashboard']);
    expect($customization->getAcknowledgedRegistryKeys($acknowledged->uid))->toBe(['craft:entries']);
    expect($customization->getCustomizationForLayout($missing->uid))->toEqual($nodes);
    $this->nextConfigRequest();
    $writes = new ConfigWrites();
    try {
        expect($migration->safeUp())->toBeTrue();
        expect($writes->paths)->toBe([]);
    } finally {
        $writes->close();
    }
});

it('installs the default layout with new-item tracking initialized', function() {
    $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
    expect($layout)->not->toBeNull();
    expect(CpNav::$plugin->getNavCustomization()->getAcknowledgedRegistryKeys($layout->uid))->not->toBeNull();
});
