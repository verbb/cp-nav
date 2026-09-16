<?php

use craft\elements\User;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\ManualUrl;
use verbb\cpnav\models\Layout;
use verbb\cpnav\models\LayoutNavItem;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\resolve\ResolvedNavNode;
use verbb\cpnav\nav\sources\NavNode;
use verbb\cpnav\nav\sources\NavSourceBuilder;
use verbb\cpnav\nav\sources\NodeKey;
use verbb\cpnav\upgrade\CustomizationUpgrader;
use yii\base\Event;

beforeEach(function() {
    AdminUser::login();
    CpRequestContext::activate();
});

it('invalidates cached layout lookups immediately after deletion', function() {
    $pc = Craft::$app->getProjectConfig();
    $readOnly = $pc->readOnly;
    $pc->readOnly = false;
    $layouts = CpNav::$plugin->getLayouts();
    $layout = new Layout(['name' => 'Deleted layout cache fixture', 'permissions' => []]);
    $layouts->saveLayout($layout);
    try {
        expect($layouts->getLayoutById($layout->id))->not->toBeNull();
        $layouts->deleteLayout($layout);
        expect($layouts->getLayoutById($layout->id))->toBeNull();
        expect($layouts->getLayoutByUid($layout->uid))->toBeNull();
        expect(array_column($layouts->getAllLayouts(), 'id'))->not->toContain($layout->id);
    } finally {
        $pc->remove('cp-nav.layouts.' . $layout->uid);
        $pc->readOnly = $readOnly;
    }
});

it('preserves external provider URLs through source capture and rendering', function(string $url) {
    $handler = function(RegisterCpNavItemsEvent $event) use ($url) {
        $event->navItems[] = ['label' => 'External provider', 'url' => $url,
            'subnav' => ['docs' => ['label' => 'External child', 'url' => $url]],
        ];
    };
    Event::on(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler);
    try {
        $registry = (new NavSourceBuilder())->build();
        $provider = array_values(array_filter($registry, fn($node) => $node->defaultLabel === 'External provider'))[0];
        expect($provider->defaultUrl)->toBe($url);
        expect($provider->children[0]->defaultUrl)->toBe($url);
        $resolved = CpNav::$plugin->getNavResolver()->resolve([$provider]);
        $items = CpNav::$plugin->getNavRenderer()->toCraftNavItems([$provider], $resolved);
        expect($items[0]['url'])->toBe($url);
        expect($items[0]['subnav']['docs']['url'])->toBe($url);
    } finally {
        Event::off(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler);
    }
})->with(['https://example.test/docs/', '//example.test/docs/', 'HTTPS://example.test/docs/']);

it('keeps colliding display IDs separate during parent attachment and deletion', function() {
    $a = 'manual:682d7409-a6da-5d66-87de-bfc9920d98ee';
    $b = 'manual:b1a70f03-ae0e-5765-9a3a-c7722486f145';
    expect(crc32($a))->toBe(crc32($b));
    $pc = Craft::$app->getProjectConfig();
    $readOnly = $pc->readOnly;
    $pc->readOnly = false;
    $layouts = CpNav::$plugin->getLayouts();
    $layout = new Layout(['name' => 'Canonical identity fixture', 'permissions' => []]);
    $layouts->saveLayout($layout);
    $service = CpNav::$plugin->getNavCustomization();
    try {
        $nodes = [];
        foreach ([$a, $b, 'manual:child'] as $i => $key) {
            $nodes[$key] = new CustomizationNode(
                key: $key, enabled: true, sort: ($i + 1) * 10,
                parent: $key === 'manual:child' ? $b : CustomizationNode::PARENT_ROOT,
                label: $key, type: 'manual', url: 'dashboard',
            );
        }
        $service->setCustomizationNodes($layout->uid, $nodes);
        $api = CpNav::$plugin->getNavBuilderApi();
        $tree = $api->getLayoutTree($layout->id);
        $child = array_values(array_filter($tree['nodes'], fn($node) => $node['key'] === 'manual:child'))[0];
        expect($child['parentKey'])->toBe($b);
        expect(CpNav::$plugin->getNavBuilder()->getLayoutNavItemByBuilderId($layout->id, (int)sprintf('%u', crc32($a))))->toBeNull();
        expect($api->deleteNode($layout->id, $b))->toBeTrue();
        $after = $service->getCustomizationForLayout($layout->uid);
        expect($after)->toHaveKey($a);
        expect($after)->not->toHaveKey($b);
        $tree = $api->getLayoutTree($layout->id);
        $child = array_values(array_filter($tree['nodes'], fn($node) => $node['key'] === 'manual:child'))[0];
        expect($child['parentKey'])->toBeNull();
    } finally {
        $layouts->deleteLayout($layout);
        $pc->readOnly = $readOnly;
    }
});

it('validates reordered parents against the resulting complete tree', function(bool $partial) {
    $pc = Craft::$app->getProjectConfig();
    $readOnly = $pc->readOnly;
    $pc->readOnly = false;
    $layouts = CpNav::$plugin->getLayouts();
    $layout = new Layout(['name' => 'Reorder validation fixture', 'permissions' => []]);
    $layouts->saveLayout($layout);
    $service = CpNav::$plugin->getNavCustomization();
    try {
        $nodes = [];
        foreach (['a', 'b', 'c'] as $letter) {
            $key = 'manual:' . $letter;
            $nodes[$key] = new CustomizationNode(
                key: $key, enabled: true, sort: 10,
                parent: $letter === 'b' ? 'manual:a' : CustomizationNode::PARENT_ROOT,
                label: $letter, type: 'manual', url: 'dashboard',
            );
        }
        $service->setCustomizationNodes($layout->uid, $nodes);
        $before = $service->getCustomizationForLayout($layout->uid);
        $items = $partial
            ? [['key' => 'manual:a', 'parentKey' => 'manual:c']]
            : [
                ['key' => 'manual:a', 'parentKey' => null],
                ['key' => 'manual:b', 'parentKey' => null],
                ['key' => 'manual:c', 'parentKey' => 'manual:b'],
            ];
        expect(CpNav::$plugin->getNavBuilderApi()->reorderNodes($layout->id, $items))->toBe(!$partial);
        $after = $service->getCustomizationForLayout($layout->uid);
        if ($partial) {
            expect($after)->toEqual($before);
        } else {
            expect($after['manual:b']->parent)->toBe(CustomizationNode::PARENT_ROOT);
            expect($after['manual:c']->parent)->toBe('manual:b');
        }
    } finally {
        $layouts->deleteLayout($layout);
        $pc->readOnly = $readOnly;
    }
})->with([true, false]);

it('migrates identity independently of reparented core and plugin placement', function() {
    $rows = [];
    foreach ([
        ['id' => 1, 'handle' => 'dashboard', 'prevUrl' => 'dashboard'],
        ['id' => 2, 'handle' => 'users', 'prevUrl' => 'users', 'level' => 2, 'parentId' => 1, 'enabled' => false],
        ['id' => 3, 'handle' => 'graphql', 'prevUrl' => 'graphql'],
        ['id' => 4, 'handle' => 'graphiql', 'prevUrl' => 'graphiql', 'prevLevel' => 2, 'prevParentId' => 3],
        ['id' => 5, 'handle' => 'tokens', 'prevUrl' => 'graphql/tokens', 'prevLevel' => 2, 'prevParentId' => 3, 'level' => 2, 'parentId' => 1],
        ['id' => 6, 'type' => 'plugin', 'handle' => 'cp-nav', 'prevUrl' => 'cp-nav', 'level' => 2, 'parentId' => 1],
        ['id' => 7, 'type' => 'plugin', 'handle' => 'manageSettings', 'prevUrl' => 'cp-nav/settings', 'prevLevel' => 2, 'prevParentId' => 6],
        ['id' => 8, 'type' => 'plugin', 'handle' => 'layouts', 'prevUrl' => 'cp-nav/layouts', 'prevLevel' => 2, 'prevParentId' => 6, 'level' => 2, 'parentId' => 1],
        // A promoted native child can itself become a parent; reference its canonical key.
        ['id' => 9, 'type' => 'manual', 'uid' => '11111111-2222-3333-4444-555555555555', 'prevUrl' => 'https://example.test', 'level' => 2, 'parentId' => 4],
    ] as $data) {
        $rows[] = new LayoutNavItem(array_merge([
            'type' => 'craft', 'prevLevel' => 1, 'level' => 1, 'enabled' => true,
            'prevLabel' => 'Original', 'currLabel' => 'Renamed', 'sortOrder' => count($rows) + 1,
            'url' => $data['prevUrl'],
        ], $data));
    }
    $nodes = (new CustomizationUpgrader())->upgradeNavigations($rows);
    expect($nodes['craft:users']->parent)->toBe('craft:dashboard');
    expect($nodes['craft:users']->enabled)->toBeFalse();
    expect($nodes['craft:graphql/graphiql']->parent)->toBe(CustomizationNode::PARENT_ROOT);
    expect($nodes['craft:graphql/tokens']->parent)->toBe('craft:dashboard');
    expect($nodes['plugin:cp-nav']->parent)->toBe('craft:dashboard');
    expect($nodes['plugin:cp-nav:manageSettings']->parent)->toBe(CustomizationNode::PARENT_ROOT);
    expect($nodes['plugin:cp-nav:layouts']->parent)->toBe('craft:dashboard');
    expect($nodes['manual:11111111-2222-3333-4444-555555555555']->parent)->toBe('craft:graphql/graphiql');

    $registry = [
        new NavNode('craft:dashboard', 'craft', 'Dashboard', 'dashboard', null, 1, null),
        new NavNode('craft:users', 'craft', 'Users', 'users', null, 2, null),
    ];
    $reloaded = array_map(fn($node) => CustomizationNode::fromConfig(NodeKey::encodePathKey($node->key), $node->toConfig()), $nodes);
    $resolved = CpNav::$plugin->getNavResolver()->resolve($registry, $reloaded);
    $users = array_values(array_filter($resolved, fn($node) => $node->key === 'craft:users'))[0];
    expect($users->enabled)->toBeFalse();
    expect($users->parentKey)->toBe('craft:dashboard');
    expect($users->label)->toBe('Renamed');
});

it('honours current-user event visibility despite an admin source catalog', function() {
    $handler = function(RegisterCpNavItemsEvent $event) {
        if (Craft::$app->getUser()->getIsAdmin()) {
            $event->navItems[] = ['label' => 'Private report', 'url' => 'release-private-report'];
        }
    };
    Event::on(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler);
    try {
        $registry = (new NavSourceBuilder())->build();
        $resolved = CpNav::$plugin->getNavResolver()->resolve($registry);
        expect(array_column($resolved, 'key'))->toContain('craft:release-private-report');
        Craft::$app->getUser()->setIdentity(new User(['id' => 999999, 'admin' => false]));
        $filtered = CpNav::$plugin->getNavPermissions()->filter($resolved, $registry);
        expect(array_column($filtered, 'key'))->not->toContain('craft:release-private-report');
        expect(array_column($filtered, 'key'))->toContain('craft:dashboard');

        // The renderer must use the supplied live event, without substituting the admin catalog.
        $event = new RegisterCpNavItemsEvent(['navItems' => [['label' => 'Dashboard', 'url' => 'dashboard']]]);
        CpNav::$plugin->getNavSources()->invalidate();
        CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
        expect(array_column($event->navItems, 'url'))->not->toContain('release-private-report');
    } finally {
        Event::off(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler);
        AdminUser::login();
        CpNav::$plugin->getNavSources()->invalidate();
    }
});

it('uses provider subnav keys and preserves manual links when filtering live menus', function() {
    $resolved = [
        new ResolvedNavNode('plugin:cp-nav:manageSettings', 'Settings', 'cp-nav/settings', 10, null, 'plugin', true),
        new ResolvedNavNode('plugin:cp-nav:hidden', 'Hidden', 'cp-nav/hidden', 20, null, 'plugin', true),
        new ResolvedNavNode('manual:test', 'Docs', 'https://example.test', 30, null, 'manual', true),
    ];
    $live = [['url' => 'cp-nav', 'subnav' => ['manageSettings' => ['url' => 'cp-nav/settings']]]];
    $filtered = CpNav::$plugin->getNavPermissions()->filter($resolved, [], $live);
    expect(array_column($filtered, 'key'))->toBe(['plugin:cp-nav:manageSettings', 'manual:test']);
});

it('disables unsafe expanded and migrated links while retaining valid children', function(string $url) {
    $previous = getenv('CPNAV_RELEASE_URL');
    putenv('CPNAV_RELEASE_URL=javascript:alert(1)');
    Craft::setAlias('@cpnavReleaseUrl', 'javascript:alert(1)');
    try {
        $nodes = [
            new ResolvedNavNode('manual:parent', 'Unsafe', $url, 10, null, 'manual', true),
            new ResolvedNavNode('manual:child', 'Safe', 'https://example.test', 10, 'manual:parent', 'manual', true),
        ];
        $item = CpNav::$plugin->getNavRenderer()->toCraftNavItems([], $nodes)[0];
        expect($item['url'])->toBe('#');
        expect($item['linkAttributes']['href'])->toBeFalse();
        expect(array_values($item['subnav'])[0]['url'])->toBe('https://example.test');
        expect(ManualUrl::isAllowed($item['url']))->toBeTrue();
    } finally {
        putenv($previous === false ? 'CPNAV_RELEASE_URL' : 'CPNAV_RELEASE_URL=' . $previous);
        Craft::setAlias('@cpnavReleaseUrl', null);
    }
})->with(['$CPNAV_RELEASE_URL', '@cpnavReleaseUrl', 'javascript:alert(1)', '/javascript:alert(1)', "java\nscript:alert(1)"]);

it('renders allowed expanded URLs and site tokens', function() {
    $previous = getenv('CPNAV_RELEASE_URL');
    putenv('CPNAV_RELEASE_URL=https://example.test/{siteHandle}');
    try {
        foreach (['$CPNAV_RELEASE_URL', 'mailto:hello@example.test', 'tel:+15551212', 'dashboard'] as $url) {
            $node = new ResolvedNavNode('manual:safe', 'Safe', $url, 10, null, 'manual', true);
            $item = CpNav::$plugin->getNavRenderer()->toCraftNavItems([], [$node])[0];
            expect($item)->not->toHaveKey('linkAttributes');
            expect($item['url'])->toBe($url === '$CPNAV_RELEASE_URL' ? 'https://example.test/' . Craft::$app->getSites()->getCurrentSite()->handle : $url);
        }
    } finally {
        putenv($previous === false ? 'CPNAV_RELEASE_URL' : 'CPNAV_RELEASE_URL=' . $previous);
    }
});

it('removes both encodings without deleting another legacy identity on collision', function() {
    $pc = Craft::$app->getProjectConfig();
    $readOnly = $pc->readOnly;
    $pc->readOnly = false;
    $layouts = CpNav::$plugin->getLayouts();
    $layout = new Layout(['name' => 'Legacy cleanup fixture', 'permissions' => []]);
    $layouts->saveLayout($layout);
    $service = CpNav::$plugin->getNavCustomization();
    $a = 'craft:reports/team_one';
    $b = 'craft:reports_team/one';
    $legacyPath = $service->nodesPath($layout->uid) . '.' . NodeKey::encodePathKeyLegacy($a);
    try {
        $pc->set($legacyPath, (new CustomizationNode($a, false, 10))->toConfig());
        $pc->set($service->nodePath($layout->uid, $a), (new CustomizationNode($a, true, 20))->toConfig());
        expect($service->removeStaleNodes($layout->uid, [$a]))->toBe([$a]);
        expect($service->getCustomizationForLayout($layout->uid))->toBe([]);

        $pc->set($legacyPath, (new CustomizationNode($b, false, 10))->toConfig());
        $service->saveNode($layout->uid, new CustomizationNode($a, true, 20));
        expect($service->getCustomizationForLayout($layout->uid))->toHaveKeys([$a, $b]);
        $service->removeNode($layout->uid, $a);
        expect($service->getCustomizationForLayout($layout->uid))->toHaveKey($b);
        $service->removeNode($layout->uid, $b);
        expect($service->getCustomizationForLayout($layout->uid))->toBe([]);
    } finally {
        $layouts->deleteLayout($layout);
        $pc->readOnly = $readOnly;
    }
});

it('keeps children of a zero display ID nested exactly once', function() {
    $a = 'manual:other';
    $b = 'manual:23230003-2123-4231-8110-000000000000';
    expect(crc32($b))->toBe(0);
    $pc = Craft::$app->getProjectConfig();
    $readOnly = $pc->readOnly;
    $pc->readOnly = false;
    $layouts = CpNav::$plugin->getLayouts();
    $layout = new Layout(['name' => 'Canonical identity fixture', 'permissions' => []]);
    $layouts->saveLayout($layout);
    $service = CpNav::$plugin->getNavCustomization();
    try {
        $nodes = [];
        foreach ([$a, $b, 'manual:child'] as $i => $key) {
            $nodes[$key] = new CustomizationNode(
                key: $key, enabled: true, sort: ($i + 1) * 10,
                parent: $key === 'manual:child' ? $b : CustomizationNode::PARENT_ROOT,
                label: $key, type: 'manual', url: 'dashboard',
            );
        }
        $service->setCustomizationNodes($layout->uid, $nodes);
        $api = CpNav::$plugin->getNavBuilderApi();
        $tree = $api->getLayoutTree($layout->id);
        $children = array_values(array_filter($tree['nodes'], fn($node) => $node['key'] === 'manual:child'));
        expect(count($children))->toBe(1);
        expect($children[0]['parentKey'])->toBe($b);
    } finally {
        $layouts->deleteLayout($layout);
        $pc->readOnly = $readOnly;
    }
});
