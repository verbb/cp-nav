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
        if (count($topLevel) < 2) {
            $this->markTestSkipped('Need at least two top-level nav items.');
        }

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
