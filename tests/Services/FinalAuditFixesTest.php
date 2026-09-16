<?php

use craft\events\RegisterCpNavItemsEvent;
use verbb\cpnav\CpNav;
use verbb\cpnav\migrations\m260916_120000_provider_node_keys;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\sources\NavSources;
use verbb\cpnav\nav\sources\NodeKey;

it('retains distinct plugin routes and literal submenu handles through rendering and permissions', function() {
    $provider = function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [
            ['label' => 'One', 'url' => 'cp-nav/one', 'subnav' => [
                'contentBlocks' => ['label' => 'Camel case', 'url' => 'cp-nav/one/camel'],
                'content-blocks' => ['label' => 'Kebab case', 'url' => 'cp-nav/one/kebab'],
                'a:b' => ['label' => 'Colon', 'url' => 'cp-nav/one/colon'],
                'a%3Ab' => ['label' => 'Percent', 'url' => 'cp-nav/one/percent'],
            ]],
            ['label' => 'Two', 'url' => 'cp-nav/two'],
        ];
    };
    $this->fixtureProvider($provider);
    $tree = CpNav::$plugin->getNavSources()->getTree(true);
    expect(array_column($tree, 'key'))->toBe(['plugin:cp-nav/one', 'plugin:cp-nav/two']);
    expect(array_column($tree[0]->children, 'key'))->toBe([
        'plugin:cp-nav/one:contentBlocks', 'plugin:cp-nav/one:content-blocks',
        'plugin:cp-nav/one:a%3Ab', 'plugin:cp-nav/one:a%253Ab',
    ]);
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect(array_column($event->navItems, 'label'))->toBe(['One', 'Two']);
    expect(array_keys($event->navItems[0]['subnav']))->toBe(['contentBlocks', 'content-blocks', 'a:b', 'a%3Ab']);
});

it('preserves beta provider customizations and parent references in a repeatable migration', function() {
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [['label' => 'Provider', 'url' => 'cp-nav/one', 'subnav' => [
            'contentBlocks' => ['label' => 'Blocks', 'url' => 'cp-nav/one/blocks'],
        ]]];
    });
    $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
    $path = "cp-nav.layouts.{$layout->uid}.customizations";
    $nodes = [
        new CustomizationNode('plugin:cp-nav', true, 20, '', 'Renamed provider'),
        new CustomizationNode('plugin:cp-nav:content-blocks', false, 10),
        new CustomizationNode('manual:audit-child', true, 30, 'plugin:cp-nav', 'My child', 'manual', 'dashboard'),
    ];
    $payload = [];
    foreach ($nodes as $node) $payload[NodeKey::encodePathKey($node->key)] = $node->toConfig();
    Craft::$app->getProjectConfig()->set($path, [
        'nodes' => $payload, 'migrationVersion' => 1,
        'acknowledgedRegistryKeys' => ['plugin:cp-nav', 'plugin:cp-nav:content-blocks'],
    ]);
    $migration = new m260916_120000_provider_node_keys();
    expect($migration->safeUp())->toBeTrue();
    $stored = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
    expect(array_keys($stored))->toEqualCanonicalizing(['plugin:cp-nav/one', 'plugin:cp-nav/one:contentBlocks', 'manual:audit-child']);
    expect($stored['plugin:cp-nav/one']->label)->toBe('Renamed provider');
    expect($stored['plugin:cp-nav/one:contentBlocks']->enabled)->toBeFalse();
    expect($stored['manual:audit-child']->parent)->toBe('plugin:cp-nav/one');
    expect(CpNav::$plugin->getNavCustomization()->getAcknowledgedRegistryKeys($layout->uid))->toBe(['plugin:cp-nav/one', 'plugin:cp-nav/one:contentBlocks']);
    $after = Craft::$app->getProjectConfig()->get($path);
    expect($migration->safeUp())->toBeTrue();
    expect(Craft::$app->getProjectConfig()->get($path))->toBe($after);
});

it('uses live provider link metadata without sharing administrator attributes', function() {
    $provider = function(RegisterCpNavItemsEvent $event) {
        $owner = (string)Craft::$app->getUser()->id;
        $event->navItems = [['label' => 'Action', 'url' => 'audit-action', 'ariaLabel' => 'Action for ' . $owner,
            'linkAttributes' => ['href' => false, 'role' => 'button', 'data-owner' => $owner],
            'subnav' => ['launch' => ['label' => 'Launch', 'url' => 'audit-action/launch', 'ariaLabel' => 'Launch for ' . $owner,
                'linkAttributes' => ['href' => false, 'data-owner' => $owner]]],
        ]];
    };
    $this->fixtureProvider($provider);
    CpNav::$plugin->getNavSources()->getTree(true);
    $cached = (new NavSources())->getTree();
    expect($cached[0]->linkAttributes)->toBe([]);
    expect($cached[0]->children[0]->ariaLabel)->toBeNull();
    $editor = $this->fixtureEditor();
    Craft::$app->getUser()->setIdentity($editor);
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect($event->navItems[0]['linkAttributes'])->toBe(['href' => false, 'role' => 'button', 'data-owner' => (string)$editor->id]);
    expect($event->navItems[0]['ariaLabel'])->toBe('Action for ' . $editor->id);
    expect($event->navItems[0]['subnav']['launch']['ariaLabel'])->toBe('Launch for ' . $editor->id);
    expect($event->navItems[0]['subnav']['launch']['linkAttributes']['href'])->toBeFalse();
});

it('blocks unsafe provider hrefs and lets a saved URL override a provider href', function() {
    $provider = function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [['label' => 'Action', 'url' => 'audit-action', 'linkAttributes' => ['href' => 'javascript:alert(1)', 'data-hook' => 'action']]];
    };
    $this->fixtureProvider($provider);
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect($event->navItems[0]['linkAttributes']['href'])->toBeFalse();
    $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
    CpNav::$plugin->getNavCustomization()->saveNode($layout->uid, new CustomizationNode('craft:audit-action', true, 10, '', url: 'dashboard'));
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect($event->navItems[0]['url'])->toBe('dashboard');
    expect($event->navItems[0]['linkAttributes'])->toBe(['data-hook' => 'action']);
});

it('migrates an old collided key to the previously surviving provider item', function() {
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [
            ['label' => 'First', 'url' => 'cp-nav/first'],
            ['label' => 'Last', 'url' => 'cp-nav/last', 'subnav' => [
                'content-blocks' => ['label' => 'First child', 'url' => 'cp-nav/last/first'],
                'contentBlocks' => ['label' => 'Last child', 'url' => 'cp-nav/last/last'],
            ]],
        ];
    });
    $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
    $path = "cp-nav.layouts.{$layout->uid}.customizations";
    $nodes = [];
    foreach (['plugin:cp-nav', 'plugin:cp-nav:content-blocks'] as $key) {
        $node = new CustomizationNode($key, false, 10);
        $nodes[NodeKey::encodePathKey($key)] = $node->toConfig();
    }
    Craft::$app->getProjectConfig()->set($path, ['nodes' => $nodes]);
    expect((new m260916_120000_provider_node_keys())->safeUp())->toBeTrue();
    $stored = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
    expect(array_keys($stored))->toEqualCanonicalizing(['plugin:cp-nav/last', 'plugin:cp-nav/last:contentBlocks']);
    expect($stored['plugin:cp-nav/last']->enabled)->toBeFalse();
    expect($stored['plugin:cp-nav/last:contentBlocks']->enabled)->toBeFalse();
});

it('marks nested manual CP links for native sidebar selection', function() {
    $resolved = [
        new \verbb\cpnav\nav\resolve\ResolvedNavNode('manual:parent', 'Parent', 'dashboard', 10, null, 'manual', true),
        new \verbb\cpnav\nav\resolve\ResolvedNavNode('manual:child', 'Users', 'users', 10, 'manual:parent', 'manual', true),
    ];
    $items = CpNav::$plugin->getNavRenderer()->toCraftNavItems([], $resolved);
    expect($items[0]['subnav']['manual-child']['linkAttributes']['data-cpnav-relocated'])->toBeTrue();
    expect($items[0]['subnav']['manual-child']['url'])->toBe('users');
});

it('retains plugin identities when old snapshots lack their original root URL', function() {
    $parent = new \verbb\cpnav\models\LayoutNavItem(['type' => 'plugin', 'handle' => 'cp-nav', 'prevUrl' => '', 'url' => 'https://example.test/custom']);
    $child = new \verbb\cpnav\models\LayoutNavItem(['type' => 'plugin', 'handle' => 'contentBlocks', 'prevLevel' => 2]);
    expect(\verbb\cpnav\upgrade\V5KeyMap::resolveKey($parent))->toBe('plugin:cp-nav');
    expect(\verbb\cpnav\upgrade\V5KeyMap::resolveKey($child, $parent))->toBe('plugin:cp-nav:contentBlocks');
    $parent->prevUrl = 'cp-nav/one';
    expect(\verbb\cpnav\upgrade\V5KeyMap::resolveKey($parent))->toBe('plugin:cp-nav/one');
    expect(\verbb\cpnav\upgrade\V5KeyMap::resolveKey($child, $parent))->toBe('plugin:cp-nav/one:contentBlocks');
});
