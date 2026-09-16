<?php

use verbb\cpnav\CpNav;
use verbb\cpnav\nav\resolve\ResolvedNavNode;

it('filters an installed plugin using the current editor permissions despite an admin catalog', function() {
    expect(Craft::$app->getPlugins()->isPluginInstalled('cp-nav'))->toBeTrue();
    // CP Nav normally exposes settings only; enable a section on the installed
    // plugin fixture so Craft exercises its real accessPlugin permission gate.
    $plugin = CpNav::$plugin;
    $previous = $plugin->hasCpSection;
    $plugin->hasCpSection = true;
    $this->onCleanup(function() use ($plugin, $previous) { $plugin->hasCpSection = $previous; });
    $permissions = Craft::$app->getUserPermissions();
    Craft::$app->set('userPermissions', new \craft\services\UserPermissions());
    $this->onCleanup(fn() => Craft::$app->set('userPermissions', $permissions));
    $registry = CpNav::$plugin->getNavSources()->getTree();
    expect(array_column($registry, 'key'))->toContain('plugin:cp-nav');
    $resolved = [
        new ResolvedNavNode('craft:dashboard', 'Home', 'dashboard', 10, null, 'craft', true),
        new ResolvedNavNode('plugin:cp-nav', 'Navigation', 'cp-nav', 20, null, 'plugin', true),
    ];
    $denied = $this->fixtureEditor();
    $allowed = $this->fixtureEditor(['accessCp', 'accessPlugin-cp-nav']);
    foreach ([[$denied, ['craft:dashboard']], [$allowed, ['craft:dashboard', 'plugin:cp-nav']], [$denied, ['craft:dashboard']]] as [$user, $expected]) {
        Craft::$app->getUser()->setIdentity($user);
        $filtered = CpNav::$plugin->getNavPermissions()->filter($resolved, $registry);
        expect(array_column($filtered, 'key'))->toBe($expected);
    }
});

