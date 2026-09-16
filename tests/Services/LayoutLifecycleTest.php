<?php

use craft\enums\CmsEdition;
use craft\models\UserGroup;
use Tests\Support\ConfigWrites;
use verbb\cpnav\CpNav;

it('refuses default-layout deletion without changing configuration or dispatching delete events', function() {
    $layouts = CpNav::$plugin->getLayouts();
    $default = $layouts->getDefaultLayout();
    expect($default)->not->toBeNull();
    $deleting = 0;
    $handler = function() use (&$deleting) { $deleting++; };
    $layouts->on($layouts::EVENT_BEFORE_DELETE_LAYOUT, $handler);
    $this->onCleanup(fn() => $layouts->off($layouts::EVENT_BEFORE_DELETE_LAYOUT, $handler));
    $before = Craft::$app->getProjectConfig()->get('cp-nav');
    $writes = new ConfigWrites();
    $this->onCleanup(fn() => $writes->close());
    expect($layouts->deleteLayoutById($default->id))->toBeFalse();
    // A caller-supplied model must not bypass the persisted default flag.
    $stale = clone $default;
    $stale->isDefault = false;
    expect($layouts->deleteLayout($stale))->toBeFalse();
    expect($layouts->getDefaultLayout()?->uid)->toBe($default->uid);
    expect(Craft::$app->getProjectConfig()->get('cp-nav'))->toBe($before);
    expect($writes->paths)->toBe([]);
    expect($deleting)->toBe(0);
});

it('selects the first matching layout for real grouped users and follows reorder and deletion', function() {
    $groups = [];
    foreach (['a', 'b'] as $handle) {
        $group = new UserGroup(['name' => 'Layout group ' . $handle, 'handle' => 'layoutGroup' . $handle]);
        expect(Craft::$app->getUserGroups()->saveGroup($group))->toBeTrue();
        $this->onCleanup(fn() => Craft::$app->getUserGroups()->deleteGroup($group));
        $groups[] = $group;
    }
    $editor = $this->fixtureEditor();
    $unassigned = $this->fixtureEditor();
    expect(Craft::$app->getUsers()->assignUserToGroups($editor->id, [$groups[0]->id, $groups[1]->id]))->toBeTrue();
    $early = $this->fixtureLayout(['permissions' => [$groups[1]->uid]]);
    $late = $this->fixtureLayout(['permissions' => [$groups[0]->uid]]);
    $layouts = CpNav::$plugin->getLayouts();
    $defaultUid = $layouts->getDefaultLayout()->uid;
    Craft::$app->getUser()->setIdentity($editor);
    expect($layouts->getLayoutForCurrentUser()?->uid)->toBe($early->uid);
    Craft::$app->getRequest()->setQueryParams(['layoutId' => $late->id]);
    expect($layouts->getLayoutForCurrentUser()?->uid)->toBe($early->uid);
    expect($layouts->reorderLayouts([$late->id, $early->id]))->toBeTrue();
    expect($layouts->getLayoutForCurrentUser()?->uid)->toBe($late->uid);
    expect($layouts->deleteLayout($late))->toBeTrue();
    expect($layouts->getLayoutForCurrentUser()?->uid)->toBe($early->uid);
    Craft::$app->getUser()->setIdentity($unassigned);
    expect($layouts->getLayoutForCurrentUser()?->uid)->toBe($defaultUid);
});

it('uses the solo assignment and falls back to the default when it is removed', function() {
    Craft::$app->setEdition(CmsEdition::Solo);
    $layout = $this->fixtureLayout(['permissions' => ['solo']]);
    $layouts = CpNav::$plugin->getLayouts();
    expect($layouts->getLayoutForCurrentUser()?->uid)->toBe($layout->uid);
    expect($layouts->deleteLayout($layout))->toBeTrue();
    expect($layouts->getLayoutForCurrentUser()?->uid)->toBe($layouts->getDefaultLayout()->uid);
});
