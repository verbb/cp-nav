<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\upgrade\CustomizationUpgradeService;
use verbb\cpnav\nav\render\NavPermissions;
use verbb\cpnav\nav\sources\NodeKey;
use verbb\cpnav\nav\resolve\ResolvedNavNode;

describe('NavPermissions', function() {
    it('allows dashboard for authenticated admin', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSourceBuilder()->build();
        $resolved = [
            new ResolvedNavNode(NodeKey::craft('dashboard'), 'Dashboard', 'dashboard', 10, null, 'craft', true),
        ];

        $filtered = CpNav::$plugin->getNavPermissions()->filter($resolved, $registry);

        expect($filtered)->toHaveCount(1);
    });

    it('filters plugin nodes the user cannot access', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = [];
        $resolved = [
            new ResolvedNavNode('plugin:nonexistent-plugin', 'Ghost', 'ghost', 10, null, 'plugin', true),
        ];

        $filtered = (new NavPermissions())->filter($resolved, $registry);

        expect($filtered)->toBeEmpty();
    });
});

describe('CustomizationUpgradeService', function() {
    it('dry-run migrates layouts without writing customization project config', function() {
        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$layout) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $overlayPath = CpNav::$plugin->getNavCustomization()->nodesPath($layout->uid);
        $before = Craft::$app->getProjectConfig()->get($overlayPath);

        $results = (new CustomizationUpgradeService())->upgradeLayouts($layout->uid, true);

        expect($results)->not->toBeEmpty();
        expect(Craft::$app->getProjectConfig()->get($overlayPath))->toBe($before);
    });

    it('audits customization keys against nav sources', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$layout) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $results = (new CustomizationUpgradeService())->auditLayouts($layout->uid);

        expect($results[0]['layoutUid'])->toBe($layout->uid);
        expect($results[0])->toHaveKeys(['stale', 'missing']);
    });
});
