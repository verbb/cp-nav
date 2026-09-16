<?php

use craft\events\RegisterCpNavItemsEvent;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;

it('keeps provider window behavior live after ordinary builder changes', function(string $operation) {
    $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
    $editor = $this->fixtureEditor();
    $provider = function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [[
            'label' => 'Reports', 'url' => 'cp-nav/reports',
            'external' => Craft::$app->getUser()->getIsAdmin(),
            'subnav' => ['personal' => [
                'label' => 'Personal report', 'url' => 'cp-nav/reports/personal',
                'external' => Craft::$app->getUser()->getIsAdmin(),
            ]],
        ]];
    };
    $this->fixtureProvider($provider);
    $builder = CpNav::$plugin->getNavBuilderApi();
    $rootKey = 'plugin:cp-nav/reports';
    $childKey = $rootKey . ':personal';

    if ($operation === 'reorder') {
        expect($builder->reorderNodes($layout->id, [
            ['key' => $childKey, 'parentKey' => null],
            ['key' => $rootKey, 'parentKey' => null],
        ]))->toBeTrue();
    } else {
        foreach ([$rootKey, $childKey] as $key) {
            if ($operation === 'rename') {
                expect($builder->updateNode($layout->id, $key, ['currLabel' => 'Renamed ' . $key]))->toBeTrue();
            } else {
                expect($builder->updateNode($layout->id, $key, ['enabled' => false]))->toBeTrue();
                $this->nextConfigRequest();
                expect($builder->updateNode($layout->id, $key, ['enabled' => true]))->toBeTrue();
            }
            $this->nextConfigRequest();
        }
    }

    $this->nextConfigRequest();
    Craft::$app->getUser()->setIdentity($editor);
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    $items = $operation === 'reorder'
        ? $event->navItems
        : [$event->navItems[0], $event->navItems[0]['subnav']['personal']];
    expect(array_column($items, 'external'))->toBe([false, false]);
    foreach ([$rootKey, $childKey] as $key) {
        expect(CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid)[$key]->newWindow)->toBeFalse();
    }
})->with(['rename', 'visibility', 'reorder']);

it('retains explicit provider overrides and editable manual window choices', function() {
    $layout = $this->fixtureLayout();
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [['label' => 'Reports', 'url' => 'cp-nav/reports', 'external' => false]];
    });
    $customizations = CpNav::$plugin->getNavCustomization();
    $customizations->saveNode($layout->uid, new CustomizationNode('plugin:cp-nav/reports', newWindow: true));
    $builder = CpNav::$plugin->getNavBuilderApi();
    expect($builder->updateNode($layout->id, 'plugin:cp-nav/reports', ['currLabel' => 'Renamed reports']))->toBeTrue();
    expect($customizations->getCustomizationForLayout($layout->uid)['plugin:cp-nav/reports']->newWindow)->toBeTrue();
    $manual = $builder->createNode($layout->id, ['type' => 'manual', 'currLabel' => 'Manual', 'url' => 'dashboard', 'newWindow' => true]);
    expect($manual)->not->toBeNull();
    expect($customizations->getCustomizationForLayout($layout->uid)[$manual['key']]->newWindow)->toBeTrue();
    $this->nextConfigRequest();
    expect($builder->updateNode($layout->id, $manual['key'], ['newWindow' => false]))->toBeTrue();
    expect($customizations->getCustomizationForLayout($layout->uid)[$manual['key']]->newWindow)->toBeFalse();
});
