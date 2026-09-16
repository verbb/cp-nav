<?php

use craft\events\RegisterCpNavItemsEvent;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\sources\NavSources;

it('renders current-user provider items that are absent from the shared admin catalog', function() {
    $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
    $editor = $this->fixtureEditor();
    $provider = function(RegisterCpNavItemsEvent $event) {
        $isAdmin = Craft::$app->getUser()->getIsAdmin();
        $event->navItems = [[
            'label' => 'Reports', 'url' => 'audit-reports',
            'subnav' => $isAdmin
                ? ['admin' => ['label' => 'Administration', 'url' => 'audit-reports/admin']]
                : ['personal' => ['label' => 'My reports', 'url' => 'audit-reports/personal']],
        ]];
        if (!$isAdmin) {
            $event->navItems[] = ['label' => 'My approvals', 'url' => 'audit-approvals'];
        }
    };
    $this->fixtureProvider($provider);
    $catalog = CpNav::$plugin->getNavSources()->getTree();
    CpNav::$plugin->getNavCustomization()->saveNode($layout->uid, new CustomizationNode(
        'craft:audit-reports', true, 10, '', 'Team reports',
    ));
    $before = Craft::$app->getProjectConfig()->get('cp-nav');
    Craft::$app->getUser()->setIdentity($editor);
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect(array_column($event->navItems, 'label'))->toBe(['Team reports', 'My approvals']);
    expect(array_keys($event->navItems[0]['subnav']))->toBe(['personal']);
    expect($event->navItems[0]['subnav']['personal']['url'])->toBe('audit-reports/personal');
    expect(CpNav::$plugin->getNavSources()->getTree())->toBe($catalog);
    expect(Craft::$app->getProjectConfig()->get('cp-nav'))->toBe($before);
});

it('keeps a current-user-only item between its live default neighbours', function() {
    $editor = $this->fixtureEditor();
    $provider = function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [['label' => 'First', 'url' => 'audit-first']];
        if (!Craft::$app->getUser()->getIsAdmin()) {
            $event->navItems[] = ['label' => 'Personal', 'url' => 'zz-personal'];
        }
        $event->navItems[] = ['label' => 'Last', 'url' => 'audit-last'];
    };
    $this->fixtureProvider($provider);
    $catalog = CpNav::$plugin->getNavSources()->getTree();
    Craft::$app->getUser()->setIdentity($editor);
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect(array_column($event->navItems, 'label'))->toBe(['First', 'Personal', 'Last']);
    expect(CpNav::$plugin->getNavSources()->getTree())->toBe($catalog);
});

it('keeps numeric provider subnav handles distinct and visible with their badge counts', function(string $url, string $key, string $childPrefix) {
    $provider = function(RegisterCpNavItemsEvent $event) use ($url) {
        $event->navItems = [[
            'label' => 'Provider', 'url' => $url,
            'subnav' => [
                0 => ['label' => 'First', 'url' => $url . '/first', 'badgeCount' => 3],
                2 => ['label' => 'Second', 'url' => $url . '/second', 'badgeCount' => 4],
            ],
        ]];
    };
    $this->fixtureProvider($provider);
    $catalog = CpNav::$plugin->getNavSources()->getTree();
    expect($catalog[0]->key)->toBe($key);
    expect(array_column($catalog[0]->children, 'key'))->toBe([$childPrefix . '0', $childPrefix . '2']);
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect($event->navItems)->toHaveCount(1);
    expect(array_keys($event->navItems[0]['subnav']))->toBe([0, 2]);
    expect(array_column($event->navItems[0]['subnav'], 'badgeCount'))->toBe([3, 4]);
    expect(array_column($event->navItems[0]['subnav'], 'url'))->toBe([$url . '/first', $url . '/second']);
})->with([
    'plugin provider' => ['cp-nav', 'plugin:cp-nav', 'plugin:cp-nav:'],
    'event provider' => ['audit-numeric', 'craft:audit-numeric', 'craft:audit-numeric/'],
]);

it('preserves provider font icons and child SVG icons without overriding a saved icon choice', function() {
    $provider = function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [[
            'label' => 'Provider', 'url' => 'audit-icons', 'fontIcon' => 'world',
            'subnav' => [
                'font' => ['label' => 'Font child', 'url' => 'audit-icons/font', 'fontIcon' => 'world'],
                'svg' => ['label' => 'SVG child', 'url' => 'audit-icons/svg', 'icon' => 'gauge'],
            ],
        ]];
    };
    $this->fixtureProvider($provider);
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect($event->navItems[0]['fontIcon'] ?? null)->toBe('world');
    expect($event->navItems[0]['subnav']['font']['fontIcon'] ?? null)->toBe('world');
    expect($event->navItems[0]['subnav']['svg']['icon'] ?? null)->toBe('gauge');

    $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
    CpNav::$plugin->getNavCustomization()->saveNode($layout->uid, new CustomizationNode(
        key: 'craft:audit-icons', icon: 'title',
    ));
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect($event->navItems[0])->not->toHaveKey('fontIcon')->not->toHaveKey('icon');
});

it('retains provider HTML IDs from the current request after warming the shared catalog', function() {
    $editor = $this->fixtureEditor();
    $provider = function(RegisterCpNavItemsEvent $event) {
        $event->navItems = [[
            'label' => 'Provider', 'url' => 'audit-id',
            'id' => 'nav-provider-' . Craft::$app->getUser()->id,
        ]];
    };
    $this->fixtureProvider($provider);
    $catalog = CpNav::$plugin->getNavSources()->getTree();
    $adminHtmlId = 'nav-provider-' . Craft::$app->getUser()->id;
    expect((new NavSources())->getTree()[0]->htmlId)->toBe($adminHtmlId);
    Craft::$app->getUser()->setIdentity($editor);
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect($event->navItems[0]['id'] ?? null)->toBe('nav-provider-' . $editor->id);
    expect(CpNav::$plugin->getNavSources()->getTree())->toBe($catalog);
    expect($catalog[0]->htmlId)->toBe($adminHtmlId);
});
