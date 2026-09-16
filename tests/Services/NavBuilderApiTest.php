<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\models\LayoutNavItem;

describe('NavBuilderApi', function() {
    it('returns a layout tree with nodes and default-insert meta', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        expect($layout)->not->toBeNull();

        $tree = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);

        expect($tree)->toHaveKeys(['layout', 'nodes', 'meta']);
        expect($tree['layout']['id'])->toBe($layout->id);
        expect($tree['layout']['uid'])->toBe($layout->uid);
        expect($tree['nodes'])->toBeArray();
        expect($tree['meta'])->toHaveKey('newItemCount');
    });

    it('does not treat stock items as new after reset acknowledges the registry', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        expect($layout)->not->toBeNull();

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        try {
            CpNav::$plugin->getNavBuilderApi()->resetLayout($layout->id);

            $tree = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);

            expect($tree['meta']['newItemCount'])->toBe(0);
            expect(array_column($tree['nodes'], 'isNew'))->not->toContain(true);
        } finally {
            $projectConfig->readOnly = $readOnly;
        }
    });

    it('creates and deletes a manual customization node', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        expect($layout)->not->toBeNull();

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        try {
            $before = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
            $topLevelBefore = array_values(array_filter(
                $before['nodes'],
                fn(array $node) => ($node['parentKey'] ?? null) === null,
            ));

            $node = CpNav::$plugin->getNavBuilderApi()->createNode($layout->id, [
                'type' => LayoutNavItem::TYPE_MANUAL,
                'currLabel' => 'Test link',
                'url' => 'https://example.com',
            ]);

            expect($node)->not->toBeNull();
            expect($node['label'])->toBe('Test link');
            expect($node['key'])->toStartWith('manual:');

            $after = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
            $topLevelAfter = array_values(array_filter(
                $after['nodes'],
                fn(array $row) => ($row['parentKey'] ?? null) === null,
            ));

            // Admin-created manuals append after resolved top-level items (not sort=10 / 2nd slot).
            expect($topLevelAfter)->not->toBeEmpty();
            expect($topLevelAfter[array_key_last($topLevelAfter)]['key'])->toBe($node['key']);
            expect(count($topLevelAfter))->toBe(count($topLevelBefore) + 1);

            $deleted = CpNav::$plugin->getNavBuilderApi()->deleteNode($layout->id, $node['key']);
            expect($deleted)->toBeTrue();
        } finally {
            $projectConfig->readOnly = $readOnly;
        }
    });

    it('disables a canonical nav item via updateNode', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        expect($layout)->not->toBeNull();

        $tree = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
        $categories = array_values(array_filter(
            $tree['nodes'],
            fn(array $node) => ($node['key'] ?? '') === 'craft:dashboard',
        ));

        expect($categories)->toHaveCount(1);

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        try {
            expect(CpNav::$plugin->getNavBuilderApi()->updateNode($layout->id, 'craft:dashboard', [
                // Simulate jQuery.param() string booleans from legacy clients.
                'enabled' => 'false',
            ]))->toBeTrue();

            $after = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
            $afterCategories = array_values(array_filter(
                $after['nodes'],
                fn(array $node) => ($node['key'] ?? '') === 'craft:dashboard',
            ));

            expect($afterCategories[0]['enabled'])->toBeFalse();

            CpNav::$plugin->getNavBuilderApi()->updateNode($layout->id, 'craft:dashboard', [
                'enabled' => true,
            ]);
        } finally {
            $projectConfig->readOnly = $readOnly;
        }
    });

    it('reorders sibling nodes via customization sort', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        expect($layout)->not->toBeNull();

        $tree = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
        $roots = array_values(array_filter($tree['nodes'], fn(array $node) => empty($node['parentKey'])));

        expect(count($roots))->toBeGreaterThanOrEqual(2);

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        try {
            $reordered = array_reverse($roots);
            $items = array_map(fn(array $node) => [
                'key' => $node['key'],
                'parentKey' => $node['parentKey'],
            ], $reordered);

            foreach ($tree['nodes'] as $node) {
                if (!empty($node['parentKey'])) {
                    $items[] = [
                        'key' => $node['key'],
                        'parentKey' => $node['parentKey'],
                    ];
                }
            }

            expect(CpNav::$plugin->getNavBuilderApi()->reorderNodes($layout->id, $items))->toBeTrue();

            $after = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
            $afterRoots = array_values(array_filter($after['nodes'], fn(array $node) => empty($node['parentKey'])));

            expect(array_column($afterRoots, 'key'))->toBe(array_column($reordered, 'key'));
        } finally {
            CpNav::$plugin->getNavBuilderApi()->resetLayout($layout->id);
            $projectConfig->readOnly = $readOnly;
        }
    });
});

it('tracks exact newly added roots and children independently for each layout', function() {
    $first = $this->fixtureLayout();
    $second = $this->fixtureLayout();
    $api = CpNav::$plugin->getNavBuilderApi();
    $api->acknowledgeNewItems($first->id);
    $api->acknowledgeNewItems($second->id);
    $this->fixtureProvider(function(\craft\events\RegisterCpNavItemsEvent $event) {
        $event->navItems[] = ['label' => 'New provider', 'url' => 'new-provider', 'subnav' => ['reports' => ['label' => 'Reports', 'url' => 'new-provider/reports']]];
    });
    $tree = $api->getLayoutTree($first->id);
    expect($tree['meta']['newItemCount'])->toBe(2);
    expect(array_column(array_values(array_filter($tree['nodes'], fn($node) => $node['isNew'])), 'key'))
        ->toBe(['craft:new-provider', 'craft:new-provider/reports']);
    $api->acknowledgeNewItems($first->id);
    expect($api->getLayoutTree($first->id)['meta']['newItemCount'])->toBe(0);
    expect($api->getLayoutTree($second->id)['meta']['newItemCount'])->toBe(2);
    $manual = $api->createNode($first->id, ['type' => 'manual', 'currLabel' => 'Manual', 'url' => 'dashboard']);
    expect($api->getLayoutTree($first->id)['meta']['newItemCount'])->toBe(0);
    $api->resetLayout($second->id);
    expect($api->getLayoutTree($second->id)['meta']['newItemCount'])->toBe(0);
    expect(array_column($api->getLayoutTree($first->id)['nodes'], 'key'))->toContain($manual['key']);
});
