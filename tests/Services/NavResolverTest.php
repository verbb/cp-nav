<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\resolve\NavResolver;
use verbb\cpnav\nav\sources\NavNode;
use verbb\cpnav\nav\sources\NodeKey;

describe('NavResolver', function() {
    it('inserts new nav source nodes at default positions when customization is empty', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSourceBuilder()->build();
        $resolved = (new NavResolver())->resolve($registry, []);

        $keys = array_map(fn($node) => $node->key, $resolved);

        expect($keys)->toContain(NodeKey::craft('dashboard'));
        expect($resolved[0]->key)->toBe(NodeKey::craft('dashboard'));
    });

    it('respects customization reordering among siblings', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSourceBuilder()->build();
        $topLevel = array_slice($registry, 0, 2);
        expect(count($topLevel))->toBe(2);

        [$second, $first] = [$topLevel[1], $topLevel[0]];

        $overlay = [
            $first->key => new CustomizationNode($first->key, true, 200),
            $second->key => new CustomizationNode($second->key, true, 50),
        ];

        $resolved = (new NavResolver())->resolve($registry, $overlay);
        $topLevelResolved = array_values(array_filter($resolved, fn($n) => $n->parentKey === null));
        $topKeys = array_map(fn($n) => $n->key, $topLevelResolved);

        $posFirst = array_search($first->key, $topKeys, true);
        $posSecond = array_search($second->key, $topKeys, true);

        expect($posSecond)->toBeLessThan($posFirst);
    });

    it('keeps disabled nodes in the resolved tree for the admin builder', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSourceBuilder()->build();
        $utilitiesKey = NodeKey::craft('utilities');

        $overlay = [
            $utilitiesKey => new CustomizationNode($utilitiesKey, false, 500),
        ];

        $resolved = (new NavResolver())->resolve($registry, $overlay);
        $utilities = array_values(array_filter($resolved, fn($node) => $node->key === $utilitiesKey));

        expect($utilities)->toHaveCount(1);
        expect($utilities[0]->enabled)->toBeFalse();
    });

    it('ignores stale customization keys that are no longer in nav sources', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSourceBuilder()->build();
        $overlay = [
            'plugin:removed-plugin' => new CustomizationNode('plugin:removed-plugin', true, 10),
        ];

        $resolved = (new NavResolver())->resolve($registry, $overlay);
        $keys = array_map(fn($node) => $node->key, $resolved);

        expect($keys)->not->toContain('plugin:removed-plugin');
    });

    it('inserts a newly appeared registry key between frozen default-sibling sorts', function() {
        // Overlay frozen before Entries existed: Assets still holds sort 20 (old slot 2).
        // Absolute defaultOrder*10 would also give Entries 20 → assets wins the key tie-break.
        $registry = [
            new NavNode('craft:dashboard', 'craft', 'Dashboard', 'dashboard', 'gauge', 1, null),
            new NavNode('craft:entries', 'craft', 'Entries', 'content/entries', 'newspaper', 2, null),
            new NavNode('craft:assets', 'craft', 'Assets', 'assets', 'image', 3, null),
            new NavNode('craft:users', 'craft', 'Users', 'users', 'user-group', 4, null),
        ];

        $overlay = [
            'craft:dashboard' => new CustomizationNode('craft:dashboard', true, 10),
            'craft:assets' => new CustomizationNode('craft:assets', true, 20),
            'craft:users' => new CustomizationNode('craft:users', true, 30),
        ];

        $resolved = (new NavResolver())->resolve($registry, $overlay);
        $topKeys = array_map(
            fn($n) => $n->key,
            array_values(array_filter($resolved, fn($n) => $n->parentKey === null)),
        );

        expect($topKeys)->toBe([
            'craft:dashboard',
            'craft:entries',
            'craft:assets',
            'craft:users',
        ]);
    });

    it('inserts multiple new registry keys in Craft order between frozen siblings', function() {
        $registry = [
            new NavNode('craft:dashboard', 'craft', 'Dashboard', 'dashboard', 'gauge', 1, null),
            new NavNode('craft:entries', 'craft', 'Entries', 'content/entries', 'newspaper', 2, null),
            new NavNode('craft:categories', 'craft', 'Categories', 'categories', 'sitemap', 3, null),
            new NavNode('craft:assets', 'craft', 'Assets', 'assets', 'image', 4, null),
        ];

        $overlay = [
            'craft:dashboard' => new CustomizationNode('craft:dashboard', true, 10),
            'craft:assets' => new CustomizationNode('craft:assets', true, 20),
        ];

        $resolved = (new NavResolver())->resolve($registry, $overlay);
        $topKeys = array_map(
            fn($n) => $n->key,
            array_values(array_filter($resolved, fn($n) => $n->parentKey === null)),
        );

        expect($topKeys)->toBe([
            'craft:dashboard',
            'craft:entries',
            'craft:categories',
            'craft:assets',
        ]);
    });

    it('opens an integer gap when frozen sibling sorts are adjacent', function() {
        $registry = [
            new NavNode('craft:dashboard', 'craft', 'Dashboard', 'dashboard', 'gauge', 1, null),
            new NavNode('craft:entries', 'craft', 'Entries', 'content/entries', 'newspaper', 2, null),
            new NavNode('craft:assets', 'craft', 'Assets', 'assets', 'image', 3, null),
        ];

        $overlay = [
            'craft:dashboard' => new CustomizationNode('craft:dashboard', true, 10),
            'craft:assets' => new CustomizationNode('craft:assets', true, 11),
        ];

        $resolved = (new NavResolver())->resolve($registry, $overlay);
        $top = array_values(array_filter($resolved, fn($n) => $n->parentKey === null));
        $byKey = [];
        foreach ($top as $node) {
            $byKey[$node->key] = $node->sort;
        }

        expect(array_map(fn($n) => $n->key, $top))->toBe([
            'craft:dashboard',
            'craft:entries',
            'craft:assets',
        ]);
        expect($byKey['craft:entries'])->toBeGreaterThan($byKey['craft:dashboard']);
        expect($byKey['craft:entries'])->toBeLessThan($byKey['craft:assets']);
    });
});

it('preserves native sibling spacing and nesting when resolving an empty overlay', function() {
    $registry = [
        new NavNode('craft:b', 'craft', 'B', 'b', null, 7, null),
        new NavNode('craft:c', 'craft', 'C', 'c', null, 7, null),
        new NavNode('craft:a', 'craft', 'A', 'a', null, 3, null, [
            new NavNode('craft:a/two', 'craft', 'Two', 'a/two', null, 8, 'craft:a'),
            new NavNode('craft:a/one', 'craft', 'One', 'a/one', null, 2, 'craft:a'),
        ]),
    ];
    $resolved = (new NavResolver())->resolve($registry);
    expect(array_map(fn($n) => [$n->key, $n->sort, $n->parentKey, $n->label, $n->url], $resolved))->toBe([
        ['craft:a', 30, null, 'A', 'a'], ['craft:b', 40, null, 'B', 'b'], ['craft:c', 40, null, 'C', 'c'],
        ['craft:a/one', 20, 'craft:a', 'One', 'a/one'], ['craft:a/two', 30, 'craft:a', 'Two', 'a/two'],
    ]);
});

it('keeps equal-order peers separate from insertion anchors and follows shifted future sorts', function() {
    $registry = [];
    foreach (['a' => 1, 'b' => 2, 'c' => 2, 'd' => 3, 'e' => 4] as $key => $order) {
        $registry[] = new NavNode('craft:' . $key, 'craft', strtoupper($key), $key, null, $order, null);
    }
    $overlay = [
        'craft:a' => new CustomizationNode('craft:a', true, 10),
        'craft:c' => new CustomizationNode('craft:c', true, 20),
        'craft:e' => new CustomizationNode('craft:e', true, 21),
    ];
    $resolver = new NavResolver();
    expect(array_map(fn($node) => [$node->key, $node->sort], $resolver->resolve($registry, $overlay)))->toBe([
        ['craft:a', 10], ['craft:b', 15], ['craft:c', 20], ['craft:d', 21], ['craft:e', 22],
    ]);
    $overlay['craft:c'] = new CustomizationNode('craft:c', true, 20, 'craft:e');
    $roots = array_values(array_filter($resolver->resolve($registry, $overlay), fn($node) => $node->parentKey === null));
    expect(array_map(fn($node) => [$node->key, $node->sort], $roots))->toBe([
        ['craft:a', 10], ['craft:b', 15], ['craft:d', 18], ['craft:e', 21],
    ]);
});
