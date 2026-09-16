<?php

use craft\db\Migration;
use craft\db\Query;
use verbb\cpnav\CpNav;
use verbb\cpnav\migrations\m260711_120000_retire_v5_navigation;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\upgrade\CustomizationUpgradeService;

beforeEach(function() {
    $db = Craft::$app->getDb();
    expect($db->tableExists('{{%cpnav_navigation}}'))->toBeFalse();
    expect($db->tableExists('{{%cpnav_navigation_v5_archive}}'))->toBeFalse();
    $migration = new class extends Migration {};
    $migration->createTable('{{%cpnav_navigation}}', [
        'id' => $migration->primaryKey(), 'layoutId' => $migration->integer(),
        'handle' => $migration->string(), 'prevLabel' => $migration->string(), 'currLabel' => $migration->string(),
        'enabled' => $migration->boolean(), 'sortOrder' => $migration->integer(),
        'prevLevel' => $migration->integer(), 'level' => $migration->integer(),
        'prevParentId' => $migration->integer(), 'parentId' => $migration->integer(),
        'prevUrl' => $migration->text(), 'url' => $migration->text(),
        'icon' => $migration->string(), 'customIcon' => $migration->string(), 'type' => $migration->string(),
        'newWindow' => $migration->boolean(), 'dateCreated' => $migration->dateTime(),
        'dateUpdated' => $migration->dateTime(), 'uid' => $migration->uid(),
    ]);
    $this->onCleanup(function() use ($migration, $db) {
        $migration->dropTableIfExists('{{%cpnav_navigation}}');
        $migration->dropTableIfExists('{{%cpnav_navigation_v5_archive}}');
        $db->getSchema()->refresh();
    });
    $this->legacyInsert = function(int $layoutId, array $data = []) use ($db) {
        $db->createCommand()->insert('{{%cpnav_navigation}}', array_merge([
            'layoutId' => $layoutId, 'type' => 'craft', 'handle' => 'dashboard',
            'prevUrl' => 'dashboard', 'url' => 'dashboard', 'prevLabel' => 'Dashboard', 'currLabel' => 'Legacy home',
            'enabled' => true, 'sortOrder' => 1, 'prevLevel' => 1, 'level' => 1,
            'prevParentId' => null, 'parentId' => null, 'newWindow' => false,
            'uid' => \craft\helpers\StringHelper::UUID(),
        ], $data))->execute();
        return (int)$db->getLastInsertID();
    };
});

it('resumes an interrupted multi-layout import without replacing completed edits', function(string $phase) {
    $first = $this->fixtureLayout(['name' => 'Already imported']);
    $second = $this->fixtureLayout(['name' => 'Not yet imported']);
    ($this->legacyInsert)($first->id);
    ($this->legacyInsert)($second->id, ['currLabel' => 'Second legacy home']);
    $pc = Craft::$app->getProjectConfig();
    $pc->set('cp-nav.navigations.legacy', ['label' => 'Old config']);
    $service = new CustomizationUpgradeService();
    $custom = CpNav::$plugin->getNavCustomization();
    $archive = (new Query())->from('{{%cpnav_navigation}}')->orderBy('id')->all();

    // Leave durable states from the migration boundaries, then resume in a new request.
    $service->upgradeLayouts($first->uid);
    $custom->saveNode($first->uid, new CustomizationNode('craft:dashboard', label: 'Beta edit'));
    if ($phase !== 'after-first-layout') {
        $service->upgradeLayouts($second->uid);
        $pc->remove('cp-nav.navigations');
    }
    if ($phase === 'after-archive-before-history') {
        Craft::$app->getDb()->createCommand()->renameTable('{{%cpnav_navigation}}', '{{%cpnav_navigation_v5_archive}}')->execute();
        Craft::$app->getDb()->getSchema()->refresh();
    }
    $this->nextConfigRequest();
    expect((new m260711_120000_retire_v5_navigation())->up())->toBeTrue();
    $this->nextConfigRequest();
    expect($custom->getCustomizationForLayout($first->uid)['craft:dashboard']->label)->toBe('Beta edit');
    expect($custom->getCustomizationForLayout($second->uid)['craft:dashboard']->label)->toBe('Second legacy home');
    expect($pc->get('cp-nav.navigations'))->toBeNull();
    expect((new Query())->from('{{%cpnav_navigation_v5_archive}}')->orderBy('id')->all())->toBe($archive);
    expect($service->upgradeLayouts($first->uid, true, true)[0]['status'])->toBe('replaced');
    expect($custom->getCustomizationForLayout($first->uid)['craft:dashboard']->label)->toBe('Beta edit');
    expect($service->upgradeLayouts($first->uid, false, true)[0]['status'])->toBe('replaced');
    expect($custom->getCustomizationForLayout($first->uid)['craft:dashboard']->label)->toBe('Legacy home');
})->with(['after-first-layout', 'after-config-removal', 'after-archive-before-history']);

it('preserves an existing beta overlay while importing another untouched layout', function() {
    $curated = $this->fixtureLayout();
    $untouched = $this->fixtureLayout();
    ($this->legacyInsert)($curated->id);
    ($this->legacyInsert)($untouched->id);
    $custom = CpNav::$plugin->getNavCustomization();
    $custom->saveNode($curated->uid, new CustomizationNode('craft:dashboard', label: 'Existing beta curation'));
    $before = Craft::$app->getProjectConfig()->get('cp-nav.layouts.' . $curated->uid);
    expect((new m260711_120000_retire_v5_navigation())->up())->toBeTrue();
    expect(Craft::$app->getProjectConfig()->get('cp-nav.layouts.' . $curated->uid))->toBe($before);
    expect($custom->getCustomizationForLayout($untouched->uid)['craft:dashboard']->label)->toBe('Legacy home');
});

it('deduplicates legacy identities and recovers missing parents without conflating manual links', function() {
    $layout = $this->fixtureLayout();
    $parent = ($this->legacyInsert)($layout->id);
    ($this->legacyInsert)($layout->id, ['currLabel' => 'Duplicate loses', 'sortOrder' => 99]);
    $graphql = ($this->legacyInsert)($layout->id, ['handle'=>'graphql','prevUrl'=>'graphql','url'=>'graphql','sortOrder'=>2]);
    ($this->legacyInsert)($layout->id, ['handle'=>'tokens','prevUrl'=>'graphql/tokens','url'=>'graphql/tokens','prevLevel'=>2,'level'=>2,'prevParentId'=>$graphql,'parentId'=>999999,'sortOrder'=>3]);
    $manuals = [];
    foreach (['https://one.example.test/help', 'https://two.example.test/help'] as $url) {
        $uid = \craft\helpers\StringHelper::UUID();
        $manuals[] = 'manual:' . $uid;
        ($this->legacyInsert)($layout->id, ['type'=>'manual','uid'=>$uid,'url'=>$url,'prevUrl'=>$url,'parentId'=>999998,'prevParentId'=>null,'level'=>2,'sortOrder'=>4]);
    }
    ($this->legacyInsert)($layout->id, ['type'=>'plugin','handle'=>'manageSettings','prevUrl'=>'cp-nav/settings','url'=>'cp-nav/settings','prevLevel'=>2,'prevParentId'=>999997,'sortOrder'=>5]);
    expect((new m260711_120000_retire_v5_navigation())->up())->toBeTrue();
    $nodes = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
    expect($nodes)->toHaveCount(6);
    expect($nodes['craft:dashboard']->label)->toBe('Legacy home');
    expect($nodes['craft:graphql/tokens']->parent)->toBe('craft:graphql');
    expect($nodes['plugin:cp-nav:manageSettings']->parent)->toBe('');
    foreach ($manuals as $key) expect($nodes[$key]->parent)->toBe('');
    expect($nodes[$manuals[0]]->url)->not->toBe($nodes[$manuals[1]]->url);
    expect((int)(new Query())->from('{{%cpnav_navigation_v5_archive}}')->count())->toBe(7);
});

it('keeps legacy asset icons replaceable without changing migrated link identity', function() {
    $layout = $this->fixtureLayout();
    $uid = \craft\helpers\StringHelper::UUID();
    $key = 'manual:' . $uid;
    ($this->legacyInsert)($layout->id, ['type'=>'manual','uid'=>$uid,'customIcon'=>'[12345]','icon'=>'fontIcon:envelope','prevUrl'=>'mailto:help@example.test','url'=>'mailto:help@example.test']);
    expect((new m260711_120000_retire_v5_navigation())->up())->toBeTrue();
    expect(\verbb\cpnav\helpers\CustomIcon::resolveSvgSource('[12345]'))->toBeNull();
    $icons = $this->fixtureIcons();
    CpNav::$plugin->getNavBuilderApi()->updateNode($layout->id, $key, ['customIcon'=>'brand/mark.svg']);
    $this->nextConfigRequest();
    $node = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid)[$key];
    expect($node->customIcon)->toBe('brand/mark.svg');
    expect($node->url)->toBe('mailto:help@example.test');
    expect(\verbb\cpnav\helpers\CustomIcon::resolveSvgSource($node->customIcon))->toBe($icons . '/brand/mark.svg');
    expect((new CustomizationUpgradeService())->upgradeLayouts($layout->uid)[0]['status'])->toBe('skipped_completed');
    expect(CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid)[$key]->customIcon)->toBe('brand/mark.svg');
});
