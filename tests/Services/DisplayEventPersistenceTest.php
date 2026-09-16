<?php

use verbb\cpnav\CpNav;
use verbb\cpnav\events\ModifyResolvedNavEvent;
use verbb\cpnav\nav\resolve\NavResolver;
use verbb\cpnav\nav\resolve\ResolvedNavNode;

it('keeps display labels out of saved visibility and label changes', function() {
    $layout = $this->fixtureLayout();
    $api = CpNav::$plugin->getNavBuilderApi();
    $customizations = CpNav::$plugin->getNavCustomization();
    $resolver = CpNav::$plugin->getNavResolver();
    expect($api->updateNode($layout->id, 'craft:dashboard', ['currLabel' => 'Original saved label']))->toBeTrue();
    $this->nextConfigRequest();
    $handler = function(ModifyResolvedNavEvent $event) use ($layout) {
        if ($event->layoutUid !== $layout->uid) return;
        foreach ($event->resolvedNodes as $index => $node) {
            if ($node->key === 'craft:dashboard') {
                $event->resolvedNodes[$index] = new ResolvedNavNode(...array_replace(get_object_vars($node), ['label' => 'Workspace']));
            }
        }
    };
    $resolver->on(NavResolver::EVENT_MODIFY_RESOLVED_NAV, $handler);
    $this->onCleanup(fn() => $resolver->off(NavResolver::EVENT_MODIFY_RESOLVED_NAV, $handler));
    $registry = CpNav::$plugin->getNavSources()->getTree();
    $resolved = $resolver->resolve($registry, $customizations->getCustomizationForLayout($layout->uid), $layout->uid);
    $rendered = CpNav::$plugin->getNavRenderer()->toCraftNavItems($registry, $resolved);
    expect($rendered[0]['label'])->toBe('Workspace');
    expect($api->updateNode($layout->id, 'craft:dashboard', ['enabled' => false]))->toBeTrue();
    $this->nextConfigRequest();
    $stored = $customizations->getCustomizationForLayout($layout->uid)['craft:dashboard'];
    expect($stored->label)->toBe('Original saved label');
    expect($stored->enabled)->toBeFalse();
    $tree = $api->getLayoutTree($layout->id);
    $dashboard = array_values(array_filter($tree['nodes'], fn($node) => $node['key'] === 'craft:dashboard'))[0];
    expect($dashboard['label'])->toBe('Original saved label');
    expect($api->updateNode($layout->id, 'craft:dashboard', ['currLabel' => 'Administrator edit']))->toBeTrue();
    $this->nextConfigRequest();
    $resolver->off(NavResolver::EVENT_MODIFY_RESOLVED_NAV, $handler);
    expect($customizations->getCustomizationForLayout($layout->uid)['craft:dashboard']->label)->toBe('Administrator edit');
    $dashboard = CpNav::$plugin->getNavBuilder()->getLayoutNavItemByKey($layout->id, 'craft:dashboard');
    expect($dashboard->currLabel)->toBe('Administrator edit');
});

it('edits configured manual fields without adopting transient event values', function() {
    $layout = $this->fixtureLayout();
    $api = CpNav::$plugin->getNavBuilderApi();
    $customizations = CpNav::$plugin->getNavCustomization();
    $created = $api->createNode($layout->id, ['type' => 'manual', 'currLabel' => 'Saved link', 'url' => 'https://example.test/saved', 'icon' => 'folder']);
    expect($created)->not->toBeNull();
    $key = $created['key'];
    $this->nextConfigRequest();
    $before = $customizations->getCustomizationForLayout($layout->uid)[$key];
    $resolver = CpNav::$plugin->getNavResolver();
    $handler = function(ModifyResolvedNavEvent $event) use ($layout, $key) {
        if ($event->layoutUid !== $layout->uid) return;
        foreach ($event->resolvedNodes as $index => $node) {
            if ($node->key === $key) {
                $event->resolvedNodes[$index] = new ResolvedNavNode(...array_replace(get_object_vars($node), [
                    'label' => 'Transient link', 'url' => 'https://example.test/transient', 'icon' => 'globe',
                    'customIcon' => 'transient.svg', 'newWindow' => true, 'enabled' => false,
                    'parentKey' => 'craft:dashboard', 'sort' => -1000,
                ]));
            }
        }
    };
    $resolver->on(NavResolver::EVENT_MODIFY_RESOLVED_NAV, $handler);
    $this->onCleanup(fn() => $resolver->off(NavResolver::EVENT_MODIFY_RESOLVED_NAV, $handler));
    expect($api->updateNode($layout->id, $key, ['currLabel' => 'Edited saved link']))->toBeTrue();
    $this->nextConfigRequest();
    $after = $customizations->getCustomizationForLayout($layout->uid)[$key];
    expect($after->label)->toBe('Edited saved link');
    foreach (['url', 'icon', 'customIcon', 'newWindow', 'enabled', 'parent', 'sort'] as $attribute) {
        expect($after->$attribute)->toBe($before->$attribute);
    }
    $tree = $api->getLayoutTree($layout->id);
    $manual = array_values(array_filter($tree['nodes'], fn($node) => $node['key'] === $key))[0];
    expect($manual['label'])->toBe('Edited saved link');
    expect($manual['url'])->toBe('https://example.test/saved');
    expect($manual['parentKey'])->toBeNull();
});
