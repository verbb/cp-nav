<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;

describe('Nav render pipeline', function() {
    it('produces Craft CP nav items from nav sources and customizations and permissions', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$layout) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $registryTree = CpNav::$plugin->getNavSources()->getTree();
        $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        $resolved = CpNav::$plugin->getNavResolver()->resolve($registryTree, $overlay);
        $resolved = CpNav::$plugin->getNavPermissions()->filter($resolved, $registryTree);
        $items = CpNav::$plugin->getNavRenderer()->toCraftNavItems($registryTree, $resolved);

        expect($items)->not->toBeEmpty();

        $urls = array_map(fn(array $item) => $item['url'] ?? null, $items);
        expect($urls)->toContain('dashboard');

        foreach ($items as $item) {
            expect($item)->toHaveKeys(['label', 'url']);
            expect($item['label'])->toBeString();
            expect($item['url'])->toBeString();
        }
    });

    it('matches admin tree keys to resolved nav source keys', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$layout) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $tree = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
        $adminKeys = array_values(array_filter(array_map(
            fn(array $node) => $node['key'] ?? null,
            $tree['nodes'],
        )));

        $registryTree = CpNav::$plugin->getNavSources()->getTree();
        $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        $resolved = CpNav::$plugin->getNavResolver()->resolve($registryTree, $overlay);
        $resolvedKeys = array_map(fn($node) => $node->key, $resolved);

        foreach ($adminKeys as $key) {
            expect($resolvedKeys)->toContain($key);
        }

        expect($adminKeys)->toBe(array_values(array_unique($adminKeys)));
    });
});
