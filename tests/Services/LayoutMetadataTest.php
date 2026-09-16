<?php

use craft\helpers\Json;
use craft\models\UserGroup;
use verbb\cpnav\CpNav;
use verbb\cpnav\records\Layout;

it('persists every changed layout field together while retaining navigation customizations', function() {
    $group = new UserGroup(['name' => 'Metadata editors', 'handle' => 'metadataEditors']);
    expect(Craft::$app->getUserGroups()->saveGroup($group))->toBeTrue();
    $this->onCleanup(fn() => Craft::$app->getUserGroups()->deleteGroup($group));
    $layout = $this->fixtureLayout();
    $api = CpNav::$plugin->getNavBuilderApi();
    expect($api->updateNode($layout->id, 'craft:dashboard', ['currLabel' => 'Editorial dashboard']))->toBeTrue();
    $this->nextConfigRequest();
    $before = Craft::$app->getProjectConfig()->get("cp-nav.layouts.{$layout->uid}.customizations");
    $layout->name = 'Renamed editorial layout';
    $layout->permissions = [$group->uid];
    $layout->sortOrder = 8;
    expect(CpNav::$plugin->getLayouts()->saveLayout($layout))->toBeTrue();
    $this->nextConfigRequest();
    $stored = Layout::findOne($layout->id);
    expect($stored->name)->toBe('Renamed editorial layout');
    expect(Json::decodeIfJson($stored->permissions))->toBe([$group->uid]);
    expect((int)$stored->sortOrder)->toBe(8);
    expect(CpNav::$plugin->getLayouts()->getLayoutMatchingPermissions([$group->uid])?->id)->toBe($layout->id);
    expect(Craft::$app->getProjectConfig()->get("cp-nav.layouts.{$layout->uid}.customizations"))->toBe($before);
});
