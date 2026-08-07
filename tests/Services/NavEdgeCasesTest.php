<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\events\ModifyResolvedNavEvent;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\resolve\NavResolver;
use verbb\cpnav\nav\resolve\ResolvedNavNode;
use verbb\cpnav\nav\render\NavRenderer;
use verbb\cpnav\nav\sources\NodeKey;

describe('NavResolver edge cases', function() {
    it('promotes orphan manual nodes to top level when parent is missing', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSourceBuilder()->build();
        $manualKey = NodeKey::manual('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        $overlay = [
            $manualKey => new CustomizationNode(
                key: $manualKey,
                enabled: true,
                sort: 999,
                parent: 'plugin:removed-parent',
                label: 'Docs',
                type: 'manual',
                url: 'https://example.com',
            ),
        ];

        $resolved = (new NavResolver())->resolve($registry, $overlay);
        $manual = array_values(array_filter($resolved, fn($node) => $node->key === $manualKey));

        expect($manual)->toHaveCount(1);
        expect($manual[0]->parentKey)->toBeNull();
        expect($manual[0]->isOrphan)->toBeTrue();
    });

    it('fires EVENT_MODIFY_RESOLVED_NAV before returning', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $resolver = new NavResolver();
        $seen = 0;

        $handler = function(ModifyResolvedNavEvent $event) use (&$seen) {
            $seen++;
            $event->resolvedNodes[] = new ResolvedNavNode(
                key: NodeKey::manual('ffffffff-1111-2222-3333-444444444444'),
                label: 'Injected',
                url: 'https://example.com/injected',
                sort: 9999,
                parentKey: null,
                source: 'manual',
                enabled: true,
            );
        };

        $resolver->on(NavResolver::EVENT_MODIFY_RESOLVED_NAV, $handler);

        try {
            $registry = CpNav::$plugin->getNavSourceBuilder()->build();
            $resolved = $resolver->resolve($registry, [], 'test-layout');
            $keys = array_map(fn($node) => $node->key, $resolved);

            expect($seen)->toBe(1);
            expect($keys)->toContain(NodeKey::manual('ffffffff-1111-2222-3333-444444444444'));
        } finally {
            $resolver->off(NavResolver::EVENT_MODIFY_RESOLVED_NAV, $handler);
        }
    });
});

describe('NavRenderer site tokens', function() {
    it('substitutes {site} and {siteHandle} in manual URLs', function() {
        $site = Craft::$app->getSites()->getPrimarySite();
        $renderer = new NavRenderer();

        $url = $renderer->substituteSiteTokens('https://example.com/?site={siteHandle}&id={site}');

        expect($url)->toBe("https://example.com/?site={$site->handle}&id={$site->id}");
    });

    it('passes absolute URLs through toCraftNavItems', function() {
        $resolved = [
            new ResolvedNavNode(
                key: NodeKey::manual('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
                label: 'Docs',
                url: 'https://example.com/docs',
                sort: 10,
                parentKey: null,
                source: 'manual',
                enabled: true,
                newWindow: true,
            ),
        ];

        $items = (new NavRenderer())->toCraftNavItems([], $resolved);

        expect($items[0]['url'])->toBe('https://example.com/docs');
        expect($items[0]['external'])->toBeTrue();
    });

    it('does not treat absolute URLs as external unless newWindow is set', function() {
        $resolved = [
            new ResolvedNavNode(
                key: NodeKey::manual('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
                label: 'New link',
                url: 'https://',
                sort: 10,
                parentKey: null,
                source: 'manual',
                enabled: true,
                newWindow: false,
            ),
        ];

        $items = (new NavRenderer())->toCraftNavItems([], $resolved);

        expect($items[0]['url'])->toBe('https://');
        expect($items[0]['external'])->toBeFalse();
    });

    it('preserves Craft GraphiQL external via registry defaultExternal', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $tree = CpNav::$plugin->getNavSources()->getTree(true);
        $graphiql = null;

        foreach ($tree as $node) {
            foreach ($node->children as $child) {
                if ($child->key === NodeKey::craft('graphql/graphiql')) {
                    $graphiql = $child;
                    break 2;
                }
            }
        }

        if ($graphiql === null) {
            // GraphQL nav is gated on enableGql + admin — skip when absent in this install.
            expect(true)->toBeTrue();
            return;
        }

        expect($graphiql->defaultExternal)->toBeTrue();

        $resolved = CpNav::$plugin->getNavResolver()->resolve($tree, []);
        $match = null;

        foreach ($resolved as $node) {
            if ($node->key === $graphiql->key) {
                $match = $node;
                break;
            }
        }

        expect($match)->not->toBeNull();
        expect($match->newWindow)->toBeTrue();

        $items = (new NavRenderer())->toCraftNavItems($tree, $resolved);
        $graphql = null;

        foreach ($items as $item) {
            if (($item['url'] ?? '') === 'graphql') {
                $graphql = $item;
                break;
            }
        }

        expect($graphql)->not->toBeNull();
        expect($graphql['subnav']['graphiql']['external'] ?? false)->toBeTrue();
    });
});
