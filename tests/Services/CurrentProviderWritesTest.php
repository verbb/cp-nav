<?php

use craft\events\RegisterCpNavItemsEvent;
use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\ProjectConfigData;
use verbb\cpnav\migrations\m260916_120000_provider_node_keys;

it('preserves current provider identities written before deployment to an older database', function(string $operation) {
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [
            ['label' => 'Root', 'url' => 'cp-nav'],
            ['label' => 'Reports', 'url' => 'cp-nav/reports'],
        ];
    });
    $layout = $this->fixtureLayout();
    $builder = CpNav::$plugin->getNavBuilderApi();
    $customizations = CpNav::$plugin->getNavCustomization();

    if ($operation === 'duplicate') {
        // A source can already carry the correct version; the copy must retain it.
        $builder->resetLayout($layout->id);
    }

    if ($operation === 'acknowledge') {
        $builder->acknowledgeNewItems($layout->id);
    } elseif ($operation === 'reorder') {
        expect($builder->reorderNodes($layout->id, [
            ['key' => 'plugin:cp-nav/reports', 'parentKey' => null],
            ['key' => 'plugin:cp-nav', 'parentKey' => null],
        ]))->toBeTrue();
    } else {
        expect($builder->updateNode($layout->id, 'plugin:cp-nav', ['currLabel' => 'My root']))->toBeTrue();

        if ($operation === 'duplicate') {
            $child = $builder->createNode($layout->id, ['type' => 'manual', 'currLabel' => 'Child', 'url' => 'dashboard']);
            expect($child)->not->toBeNull();
            expect($builder->reparentNode($layout->id, $child['key'], 'plugin:cp-nav'))->toBeTrue();
            $layout = CpNav::$plugin->getLayouts()->duplicateLayout($layout, 'Copied layout');
            expect($layout)->not->toBeNull();
            $this->onCleanup(fn() => CpNav::$plugin->getLayouts()->deleteLayout($layout));
        }
    }

    $before = $customizations->getCustomizationForLayout($layout->uid);
    $acknowledged = $customizations->getAcknowledgedRegistryKeys($layout->uid);
    $rebuilt = ProjectConfigData::rebuildProjectConfig();
    $path = "cp-nav.layouts.{$layout->uid}.customizations";
    $this->nextConfigRequest();
    Craft::$app->getProjectConfig()->set($path, $rebuilt['layouts'][$layout->uid]['customizations']);
    $this->nextConfigRequest();

    // Configuration may be deployed before the target runs its pending migration.
    expect((new m260916_120000_provider_node_keys())->safeUp())->toBeTrue();
    expect($customizations->getCustomizationForLayout($layout->uid))->toEqual($before);
    expect($customizations->getAcknowledgedRegistryKeys($layout->uid))->toBe($acknowledged);
})->with(['save', 'reorder', 'duplicate', 'acknowledge']);
