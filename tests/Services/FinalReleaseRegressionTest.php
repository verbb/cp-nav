<?php

use craft\events\RegisterCpNavItemsEvent;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\sources\NavSources;

it('uses current-user provider metadata instead of shared admin link details', function() {
    $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
    $adminId = Craft::$app->getUser()->id;
    $editor = $this->fixtureEditor();
    $provider = function(RegisterCpNavItemsEvent $event) {
        $userId = Craft::$app->getUser()->id;
        $event->navItems[] = [
            'label' => 'Reports for ' . $userId, 'url' => 'release-reports', 'icon' => 'gauge',
            'subnav' => ['personal' => [
                'label' => 'Personal report for ' . $userId,
                'url' => 'release-reports/personal?owner=' . $userId,
                'external' => Craft::$app->getUser()->getIsAdmin(),
            ]],
        ];
    };
    $this->fixtureProvider($provider);
    $catalog = CpNav::$plugin->getNavSources()->getTree();
    $report = array_values(array_filter($catalog, fn($node) => $node->key === 'craft:release-reports'))[0];
    expect($report->children[0]->defaultUrl)->toBe('release-reports/personal?owner=' . $adminId);

    CpNav::$plugin->getNavCustomization()->saveNode($layout->uid, new CustomizationNode(
        'craft:release-reports', true, 10, '', 'Our reports',
    ));
    Craft::$app->getUser()->setIdentity($editor);
    $before = Craft::$app->getProjectConfig()->get('cp-nav');
    $event = new RegisterCpNavItemsEvent(['navItems' => []]);
    $provider($event);
    CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
    expect($event->navItems)->toHaveCount(1);
    expect($event->navItems[0]['label'])->toBe('Our reports');
    expect($event->navItems[0]['subnav']['personal'])->toBe([
        'label' => 'Personal report for ' . $editor->id,
        'url' => 'release-reports/personal?owner=' . $editor->id,
        'external' => false,
    ]);
    expect(CpNav::$plugin->getNavSources()->getTree())->toBe($catalog);
    expect(Craft::$app->getProjectConfig()->get('cp-nav'))->toBe($before);
});

it('does not revive an old source catalog when its generation key is evicted', function() {
    $label = 'Original provider';
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) use (&$label) {
        $event->navItems[] = ['label' => $label, 'url' => 'release-eviction'];
    });
    $cache = Craft::$app->getCache();
    $cache->delete('cpnav:sources:generation');
    expect(array_column((new NavSources())->getTree(), 'defaultLabel'))->toContain($label);
    $label = 'Updated provider';
    // Cache backends can evict the small generation record before its catalog entries.
    $cache->delete('cpnav:sources:generation');
    expect(array_column((new NavSources())->getTree(), 'defaultLabel'))
        ->toContain($label)->not->toContain('Original provider');
});
