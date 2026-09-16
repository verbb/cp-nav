<?php

use craft\events\RegisterCpNavItemsEvent;
use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\ProjectConfigData;
use verbb\cpnav\migrations\m260916_120000_provider_node_keys;
use verbb\cpnav\nav\customization\CustomizationNode;

it('preserves current provider identities when rebuilt config reaches a database with the key migration pending', function() {
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [
            ['label' => 'Root', 'url' => 'cp-nav'],
            ['label' => 'Reports', 'url' => 'cp-nav/reports'],
        ];
    });
    $layout = $this->fixtureLayout();
    $service = CpNav::$plugin->getNavCustomization();
    $service->setCustomizationNodes($layout->uid, [
        new CustomizationNode('plugin:cp-nav', true, 10, '', 'My root'),
        new CustomizationNode('manual:child', true, 20, 'plugin:cp-nav', 'Child', 'manual', 'dashboard'),
    ], true);
    $service->setAcknowledgedRegistryKeys($layout->uid, ['plugin:cp-nav']);
    $rebuilt = ProjectConfigData::rebuildProjectConfig();
    $path = "cp-nav.layouts.{$layout->uid}.customizations";
    $this->nextConfigRequest();
    Craft::$app->getProjectConfig()->set($path, $rebuilt['layouts'][$layout->uid]['customizations']);
    $this->nextConfigRequest();

    // Deployment can apply rebuilt config before this database runs its pending migration.
    expect((new m260916_120000_provider_node_keys())->safeUp())->toBeTrue();
    $stored = $service->getCustomizationForLayout($layout->uid);
    expect(array_keys($stored))->toEqualCanonicalizing(['plugin:cp-nav', 'manual:child']);
    expect($stored['plugin:cp-nav']->label)->toBe('My root');
    expect($stored['manual:child']->parent)->toBe('plugin:cp-nav');
    expect($service->getAcknowledgedRegistryKeys($layout->uid))->toBe(['plugin:cp-nav']);
    expect(Craft::$app->getProjectConfig()->get($path . '.providerKeyVersion'))->toBe(2);
});
