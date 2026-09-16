<?php

use craft\events\RegisterCpNavItemsEvent;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;

it('renders exact customization order visibility children and live badges from a warm catalog', function() {
    $layout = $this->fixtureLayout();
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) {
        $event->navItems[] = ['label' => 'Reports', 'url' => 'quality-reports', 'icon' => 'gauge', 'subnav' => ['child' => ['label' => 'Child', 'url' => 'quality-reports/child']]];
    });
    $sources = CpNav::$plugin->getNavSources()->getTree();
    $nodes = [
        'craft:quality-reports' => new CustomizationNode('craft:quality-reports', true, 10, '', 'Renamed reports'),
        'craft:quality-reports/child' => new CustomizationNode('craft:quality-reports/child', true, 10, 'craft:quality-reports', 'Renamed child'),
        'craft:dashboard' => new CustomizationNode('craft:dashboard', false, 30),
        'manual:docs' => new CustomizationNode('manual:docs', true, 20, '', 'Documentation', 'manual', 'https://example.test/docs', newWindow: true),
        'divider:resources' => new CustomizationNode('divider:resources', true, 15, '', 'Resources', 'divider'),
    ];
    CpNav::$plugin->getNavCustomization()->setCustomizationNodes($layout->uid, $nodes);
    Craft::$app->getRequest()->setQueryParams(['layoutId' => $layout->id]);
    foreach ([2, 8] as $badge) {
        $event = new RegisterCpNavItemsEvent(['navItems' => [
            ['label' => 'Dashboard', 'url' => 'dashboard'],
            ['label' => 'Reports', 'url' => 'quality-reports', 'badgeCount' => $badge, 'subnav' => ['child' => ['label' => 'Child', 'url' => 'quality-reports/child', 'badgeCount' => $badge + 1]]],
        ]]);
        CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
        expect(array_column($event->navItems, 'label'))->toBe(['Renamed reports', 'Resources', 'Documentation']);
        expect(array_column($event->navItems, 'url'))->toBe(['quality-reports', '__cpnav-divider', 'https://example.test/docs']);
        expect($event->navItems[0]['badgeCount'])->toBe($badge);
        expect($event->navItems[0]['subnav'])->toBe(['child' => ['label' => 'Renamed child', 'url' => 'quality-reports/child', 'external' => false, 'badgeCount' => $badge + 1]]);
        expect($event->navItems[1]['linkAttributes']['href'])->toBeFalse();
        expect($event->navItems[2]['external'])->toBeTrue();
    }
    expect(CpNav::$plugin->getNavSources()->getTree())->toBe($sources);
});
