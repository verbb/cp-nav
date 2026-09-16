<?php

use craft\events\RegisterCpNavItemsEvent;
use craft\services\ProjectConfig;
use Tests\Support\ConfigWrites;
use verbb\cpnav\CpNav;

it('observes actual project-config additions updates and removals', function() {
    $writes = new ConfigWrites();
    $this->onCleanup(fn() => $writes->close());
    $pc = Craft::$app->getProjectConfig();
    $pc->set('cp-nav.testObserver', 'before');
    $pc->set('cp-nav.testObserver', 'after');
    $pc->remove('cp-nav.testObserver');
    expect($writes->paths)->toBe([
        [ProjectConfig::EVENT_ADD_ITEM, 'cp-nav.testObserver'],
        [ProjectConfig::EVENT_UPDATE_ITEM, 'cp-nav.testObserver'],
        [ProjectConfig::EVENT_REMOVE_ITEM, 'cp-nav.testObserver'],
    ]);
});

it('keeps cold and warm source resolution and rendering read-only for each identity', function(bool $editor) {
    if ($editor) {
        Craft::$app->getUser()->setIdentity($this->fixtureEditor());
    }
    $pc = Craft::$app->getProjectConfig();
    $before = $pc->get('cp-nav');
    $writes = new ConfigWrites();
    $this->onCleanup(fn() => $writes->close());
    $pc->readOnly = true;
    $sources = CpNav::$plugin->getNavSources();
    foreach ([true, false] as $cold) {
        $registry = $sources->getTree($cold);
        $resolved = CpNav::$plugin->getNavResolver()->resolve($registry);
        expect(array_column($resolved, 'key'))->toContain('craft:dashboard');
        $event = new RegisterCpNavItemsEvent(['navItems' => [['label' => 'Dashboard', 'url' => 'dashboard']]]);
        CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);
        expect(array_column($event->navItems, 'url'))->toContain('dashboard');
    }
    expect($writes->paths)->toBe([]);
    expect($pc->get('cp-nav'))->toBe($before);
})->with([false, true]);
