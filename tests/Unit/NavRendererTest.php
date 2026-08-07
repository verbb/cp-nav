<?php

declare(strict_types=1);

use verbb\cpnav\nav\sources\NavNode;
use verbb\cpnav\nav\render\NavRenderer;
use verbb\cpnav\nav\resolve\ResolvedNavNode;

describe('NavRenderer', function() {
    it('converts resolved nodes into Craft pre-normalized nav item shape', function() {
        $registryTree = [
            new NavNode(
                key: 'craft:dashboard',
                source: 'craft',
                defaultLabel: 'Dashboard',
                defaultUrl: 'dashboard',
                // Modern Craft nav: bare system SVG icon names (not font ligatures).
                icon: 'gauge',
                defaultOrder: 1,
                parentKey: null,
            ),
            new NavNode(
                key: 'craft:graphql',
                source: 'craft',
                defaultLabel: 'GraphQL',
                defaultUrl: 'graphql',
                // graphql is a custom SVG — there is no Craft font glyph for it.
                icon: 'graphql',
                defaultOrder: 2,
                parentKey: null,
                children: [
                    new NavNode(
                        key: 'craft:graphql/schemas',
                        source: 'craft',
                        defaultLabel: 'Schemas',
                        defaultUrl: 'graphql/schemas',
                        icon: null,
                        defaultOrder: 1,
                        parentKey: 'craft:graphql',
                        subHandle: 'schemas',
                    ),
                ],
            ),
        ];

        $resolved = [
            new ResolvedNavNode('craft:dashboard', 'Home', 'dashboard', 10, null, 'craft', true),
            new ResolvedNavNode('craft:graphql', 'GraphQL', 'graphql', 20, null, 'craft', true),
            new ResolvedNavNode('craft:graphql/schemas', 'Schemas', 'graphql/schemas', 10, 'craft:graphql', 'craft', true),
        ];

        $items = (new NavRenderer())->toCraftNavItems($registryTree, $resolved);

        expect($items)->toHaveCount(2);
        expect($items[0]['label'])->toBe('Home');
        expect($items[0]['url'])->toBe('dashboard');
        expect($items[0]['icon'])->toBe('gauge');
        expect($items[0])->not->toHaveKey('fontIcon');
        expect($items[1]['icon'])->toBe('graphql');
        expect($items[1])->not->toHaveKey('fontIcon');
        expect($items[1]['subnav']['schemas']['label'])->toBe('Schemas');
        expect($items[1]['subnav']['schemas']['url'])->toBe('graphql/schemas');
    });

    it('maps fontIcon: overrides and prefers custom SVG paths', function() {
        $registryTree = [
            new NavNode(
                key: 'craft:dashboard',
                source: 'craft',
                defaultLabel: 'Dashboard',
                defaultUrl: 'dashboard',
                icon: 'gauge',
                defaultOrder: 1,
                parentKey: null,
            ),
        ];

        $fontOverride = [
            new ResolvedNavNode(
                key: 'craft:dashboard',
                label: 'Home',
                url: 'dashboard',
                sort: 10,
                parentKey: null,
                source: 'craft',
                enabled: true,
                icon: 'fontIcon:gauge',
            ),
        ];

        $fontItems = (new NavRenderer())->toCraftNavItems($registryTree, $fontOverride);
        expect($fontItems[0]['fontIcon'] ?? null)->toBe('gauge');
        expect($fontItems[0])->not->toHaveKey('icon');

        $customOverride = [
            new ResolvedNavNode(
                key: 'craft:dashboard',
                label: 'Home',
                url: 'dashboard',
                sort: 10,
                parentKey: null,
                source: 'craft',
                enabled: true,
                icon: 'fontIcon:gauge',
                // Non-existent asset id — path resolves null and falls back to icon.
                customIcon: null,
            ),
        ];

        $fallbackItems = (new NavRenderer())->toCraftNavItems($registryTree, $customOverride);
        expect($fallbackItems[0]['fontIcon'] ?? null)->toBe('gauge');
    });

    it('emits inert divider items with a stable nav-divider id', function() {
        $registryTree = [];
        $resolved = [
            new ResolvedNavNode(
                key: 'divider:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                label: 'TEST',
                url: '',
                sort: 10,
                parentKey: null,
                source: 'divider',
                enabled: true,
            ),
            new ResolvedNavNode('craft:dashboard', 'Dashboard', 'dashboard', 20, null, 'craft', true),
        ];

        $items = (new NavRenderer())->toCraftNavItems($registryTree, $resolved);

        expect($items[0]['id'])->toBe('nav-divider-aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        expect($items[0]['label'])->toBe('TEST');
        expect($items[0]['url'])->toBe('__cpnav-divider');
        expect($items[0]['linkAttributes']['href'])->toBeFalse();
        expect($items[0])->not->toHaveKey('icon');
        expect($items[0])->not->toHaveKey('subnav');
    });
});
