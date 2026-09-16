<?php

use craft\events\RegisterCpNavItemsEvent;
use craft\services\Plugins;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\sources\NavSourceBuilder;
use verbb\cpnav\nav\sources\NavSources;
use yii\base\Event;

it('reuses both request and shared caches without losing provider metadata', function() {
    $calls = 0;
    $label = 'First label';
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) use (&$calls, &$label) {
        $calls++;
        $event->navItems[] = ['label' => $label, 'url' => 'quality-provider', 'icon' => 'gauge', 'external' => true,
            'subnav' => ['childHandle' => ['label' => 'Child label', 'url' => 'https://example.test/child/', 'external' => true]],
        ];
    });
    $sources = CpNav::$plugin->getNavSources();
    $cold = $sources->getTree();
    expect($calls)->toBe(1);
    $memo = $sources->getTree();
    expect($calls)->toBe(1);
    expect($memo)->toBe($cold);
    $warm = (new NavSources())->getTree();
    expect($calls)->toBe(1);
    expect($warm)->toEqual($cold);
    $provider = array_values(array_filter($warm, fn($node) => $node->key === 'craft:quality-provider'))[0];
    expect([$provider->defaultLabel, $provider->defaultUrl, $provider->icon, $provider->defaultExternal])->toBe(['First label', 'quality-provider', 'gauge', true]);
    $child = $provider->children[0];
    expect([$child->key, $child->parentKey, $child->subHandle, $child->defaultLabel, $child->defaultUrl, $child->defaultExternal])
        ->toBe(['craft:quality-provider/childHandle', 'craft:quality-provider', 'childHandle', 'Child label', 'https://example.test/child/', true]);

    $label = 'Updated provider';
    // Exercise the actual registered lifecycle callback, not a test-only convenience method.
    Event::trigger(Plugins::class, Plugins::EVENT_AFTER_ENABLE_PLUGIN);
    $fresh = (new NavSources())->getTree();
    expect($calls)->toBe(2);
    expect(array_column($fresh, 'defaultLabel'))->toContain('Updated provider')->not->toContain('First label');
});

it('hydrates separate language payloads without rebuilding the first language', function() {
    $calls = 0;
    $this->fixtureProvider(function(RegisterCpNavItemsEvent $event) use (&$calls) {
        $calls++;
        $event->navItems[] = ['label' => Craft::$app->language, 'url' => 'language-provider'];
    });
    Craft::$app->language = 'en-US';
    $english = (new NavSources())->getTree();
    Craft::$app->language = 'fr';
    $french = (new NavSources())->getTree();
    Craft::$app->language = 'en-US';
    $again = (new NavSources())->getTree();
    expect($calls)->toBe(2);
    expect($again)->toEqual($english);
    expect(array_column($english, 'defaultLabel'))->toContain('en-US');
    expect(array_column($french, 'defaultLabel'))->toContain('fr')->not->toContain('en-US');
});

it('restores identity request and capture state when a provider throws', function() {
    $editor = $this->fixtureEditor();
    Craft::$app->getUser()->setIdentity($editor);
    $request = Craft::$app->getRequest();
    $this->fixtureProvider(function() { throw new RuntimeException('Provider failed'); });
    expect(fn() => (new NavSourceBuilder())->build())->toThrow(RuntimeException::class, 'Provider failed');
    expect(Craft::$app->getUser()->getIdentity())->toBe($editor);
    expect(Craft::$app->getRequest())->toBe($request);
    expect(NavSourceBuilder::isCapturing())->toBeFalse();
});
