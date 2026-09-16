<?php

use craft\db\Migration;
use craft\db\Query;
use Tests\Support\ConfigWrites;
use verbb\cpnav\CpNav;
use verbb\cpnav\migrations\m260711_120000_retire_v5_navigation;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\upgrade\CustomizationUpgradeService;

it('imports legacy rows persists exact values archives the table and preserves resets through rebuilds', function() {
    $first = $this->fixtureLayout(['name' => 'Legacy editors']);
    $second = $this->fixtureLayout(['name' => 'Legacy managers']);
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
    $manualUid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $dividerUid = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
    $rows = [
        ['id' => 1, 'handle' => 'dashboard', 'prevUrl' => 'dashboard', 'currLabel' => 'Home', 'enabled' => false, 'icon' => 'fontIcon:gauge'],
        ['id' => 2, 'handle' => 'graphql', 'prevUrl' => 'graphql'],
        ['id' => 3, 'handle' => 'graphiql', 'prevUrl' => 'graphiql', 'prevLevel' => 2, 'level' => 2, 'prevParentId' => 2, 'parentId' => 2, 'newWindow' => true],
        ['id' => 4, 'type' => 'plugin', 'handle' => 'cp-nav', 'prevUrl' => 'cp-nav'],
        ['id' => 5, 'type' => 'plugin', 'handle' => 'manageSettings', 'prevUrl' => 'cp-nav/settings', 'prevLevel' => 2, 'level' => 1, 'prevParentId' => 4],
        ['id' => 6, 'type' => 'manual', 'uid' => $manualUid, 'prevUrl' => 'https://example.test/docs', 'currLabel' => 'Docs', 'parentId' => 4, 'level' => 2, 'customIcon' => 'brand/mark.svg', 'newWindow' => true],
        ['id' => 7, 'type' => 'divider', 'uid' => $dividerUid, 'prevUrl' => '', 'currLabel' => 'Resources'],
        ['id' => 8, 'layoutId' => $second->id, 'handle' => 'dashboard', 'prevUrl' => 'dashboard', 'currLabel' => 'Managers home'],
    ];
    foreach ($rows as $row) {
        $db->createCommand()->insert('{{%cpnav_navigation}}', array_merge([
            'layoutId' => $first->id, 'type' => 'craft', 'prevLabel' => 'Original', 'currLabel' => 'Original',
            'enabled' => true, 'sortOrder' => $row['id'], 'prevLevel' => 1, 'level' => 1,
            'prevParentId' => null, 'parentId' => null, 'url' => $row['prevUrl'], 'newWindow' => false,
            'uid' => \craft\helpers\StringHelper::UUID(),
        ], $row))->execute();
    }
    $pc = Craft::$app->getProjectConfig();
    $pc->set('cp-nav.navigations.legacy', ['label' => 'Archived legacy config']);
    $before = $pc->get('cp-nav');
    $writes = new ConfigWrites();
    $this->onCleanup(fn() => $writes->close());
    $upgrade = new CustomizationUpgradeService();
    $dry = $upgrade->upgradeLayouts($first->uid, true);
    expect($dry)->toBe([['layoutUid' => $first->uid, 'layoutName' => 'Legacy editors', 'nodeCount' => 7, 'status' => 'migrated']]);
    expect($writes->paths)->toBe([]);
    expect($pc->get('cp-nav'))->toBe($before);

    expect((new m260711_120000_retire_v5_navigation())->safeUp())->toBeTrue();
    expect($db->tableExists('{{%cpnav_navigation}}'))->toBeFalse();
    expect((int)(new Query())->from('{{%cpnav_navigation_v5_archive}}')->count())->toBe(8);
    expect($pc->get('cp-nav.navigations'))->toBeNull();
    $expected = [
        'craft:dashboard' => new CustomizationNode('craft:dashboard', false, 10, '', 'Home', icon: 'fontIcon:gauge'),
        'craft:graphql' => new CustomizationNode('craft:graphql', true, 20, ''),
        'craft:graphql/graphiql' => new CustomizationNode('craft:graphql/graphiql', true, 10, 'craft:graphql', newWindow: true),
        'plugin:cp-nav' => new CustomizationNode('plugin:cp-nav', true, 30, ''),
        'plugin:cp-nav:manageSettings' => new CustomizationNode('plugin:cp-nav:manageSettings', true, 40, ''),
        'manual:' . $manualUid => new CustomizationNode('manual:' . $manualUid, true, 10, 'plugin:cp-nav', 'Docs', 'manual', 'https://example.test/docs', customIcon: 'brand/mark.svg', newWindow: true),
        'divider:' . $dividerUid => new CustomizationNode('divider:' . $dividerUid, true, 50, '', 'Resources', 'divider', null),
    ];
    $customizations = CpNav::$plugin->getNavCustomization();
    expect($customizations->getCustomizationForLayout($first->uid))->toEqual($expected);
    expect($customizations->getCustomizationForLayout($second->uid)['craft:dashboard']->label)->toBe('Managers home');
    expect($upgrade->upgradeLayouts($first->uid)[0]['status'])->toBe('skipped_completed');
    expect((new m260711_120000_retire_v5_navigation())->safeUp())->toBeTrue();

    // Use Craft's real rebuild and apply paths, not only the plugin's rebuild helper.
    $this->nextConfigRequest();
    $pc->rebuild();
    $pc->reset();
    expect($customizations->getCustomizationForLayout($first->uid))->toEqual($expected);
    $export = $pc->get();
    $customizations->saveNode($first->uid, new CustomizationNode('craft:dashboard', label: 'Temporary'));
    $this->nextConfigRequest();
    $pc->applyConfigChanges($export);
    expect($customizations->getCustomizationForLayout($first->uid))->toEqual($expected);

    CpNav::$plugin->getNavBuilderApi()->resetLayout($first->id);
    $this->nextConfigRequest();
    $pc->rebuild();
    $pc->reset();
    expect($upgrade->upgradeLayouts($first->uid)[0]['status'])->toBe('skipped_completed');
    expect($customizations->getCustomizationForLayout($first->uid))->toBe([]);
    expect($customizations->getCustomizationForLayout($second->uid)['craft:dashboard']->label)->toBe('Managers home');
});
