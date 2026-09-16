<?php

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout;

beforeEach(function() {
    AdminUser::login();
    CpRequestContext::activate();
    $this->pc = Craft::$app->getProjectConfig();
    $this->readOnly = $this->pc->readOnly;
    $this->pc->readOnly = false;
    $this->layout = new Layout(['name' => 'Release workflow fixture', 'permissions' => []]);
    CpNav::$plugin->getLayouts()->saveLayout($this->layout);
});

afterEach(function() {
    Craft::$app->getRequest()->setQueryParams([]);
    CpNav::$plugin->getLayouts()->deleteLayout($this->layout);
    $this->pc->readOnly = $this->readOnly;
});

it('keeps resets empty across migration reruns and project config rebuilds', function() {
    $service = CpNav::$plugin->getNavCustomization();
    $service->clearCustomization($this->layout->uid);
    $results = (new \verbb\cpnav\upgrade\CustomizationUpgradeService())->upgradeLayouts($this->layout->uid);
    expect($results[0]['status'])->toBe('skipped_completed');
    $rebuilt = \verbb\cpnav\helpers\ProjectConfigData::rebuildProjectConfig();
    expect($rebuilt['layouts'][$this->layout->uid]['customizations']['migrationVersion'])->toBe(1);
    expect($service->getCustomizationForLayout($this->layout->uid))->toBe([]);
});

