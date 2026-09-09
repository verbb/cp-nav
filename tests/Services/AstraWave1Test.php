<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout;
use verbb\cpnav\models\LayoutNavItem;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\sources\NavNode;
use verbb\cpnav\nav\sources\NodeKey;
use verbb\cpnav\upgrade\CustomizationUpgradeService;
use verbb\cpnav\upgrade\CustomizationUpgrader;
use verbb\cpnav\upgrade\V5KeyMap;

describe('Astra Wave 1 — layout metadata preserve customizations', function() {
    it('keeps customization nodes when renaming a layout (ASTRA-01)', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        $layouts = CpNav::$plugin->getLayouts();
        $layout = null;
        $manualKey = null;

        try {
            $layout = new Layout([
                'name' => 'Astra Rename ' . uniqid(),
                'isDefault' => false,
                'permissions' => [],
            ]);
            expect($layouts->saveLayout($layout))->toBeTrue();
            $layout = $layouts->getLayoutByUid($layout->uid);
            expect($layout)->not->toBeNull();

            $created = CpNav::$plugin->getNavBuilderApi()->createNode($layout->id, [
                'type' => LayoutNavItem::TYPE_MANUAL,
                'currLabel' => 'Docs',
                'url' => 'https://example.test/docs',
            ]);
            expect($created)->not->toBeNull();
            $manualKey = $created['key'];

            $before = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
            expect($before)->toHaveKey($manualKey);

            $layout->name = 'Astra Renamed ' . uniqid();
            expect($layouts->saveLayout($layout))->toBeTrue();

            $after = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
            expect($after)->toHaveKey($manualKey);
            expect($after[$manualKey]->url)->toBe('https://example.test/docs');
            expect($after[$manualKey]->label)->toBe('Docs');
        } finally {
            if ($layout) {
                $layouts->deleteLayout($layout);
            }
            $projectConfig->readOnly = $readOnly;
        }
    });
});

describe('Astra Wave 1 — manual URL persistence', function() {
    it('persists a changed manual URL through save/reload (ASTRA-02)', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$layout) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;
        $manualKey = null;

        try {
            $created = CpNav::$plugin->getNavBuilderApi()->createNode($layout->id, [
                'type' => LayoutNavItem::TYPE_MANUAL,
                'currLabel' => 'Old Link',
                'url' => 'https://example.test/old',
            ]);
            expect($created)->not->toBeNull();
            $manualKey = $created['key'];

            expect(CpNav::$plugin->getNavBuilderApi()->updateNode($layout->id, $manualKey, [
                'url' => 'https://example.test/new',
                'currLabel' => 'Old Link',
            ]))->toBeTrue();

            $stored = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
            expect($stored[$manualKey]->url)->toBe('https://example.test/new');
        } finally {
            if ($manualKey) {
                CpNav::$plugin->getNavCustomization()->removeNode($layout->uid, $manualKey);
            }
            $projectConfig->readOnly = $readOnly;
        }
    });

    it('does not snapshot canonical URL when first-toggling a registry node (ASTRA-02)', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$layout) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;
        $key = 'craft:dashboard';

        try {
            CpNav::$plugin->getNavCustomization()->removeNode($layout->uid, $key);

            expect(CpNav::$plugin->getNavBuilderApi()->updateNode($layout->id, $key, [
                'enabled' => false,
            ]))->toBeTrue();

            $stored = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
            expect($stored)->toHaveKey($key);
            expect($stored[$key]->url)->toBeNull();
            expect($stored[$key]->type)->toBeNull();
            expect($stored[$key]->enabled)->toBeFalse();
        } finally {
            CpNav::$plugin->getNavCustomization()->removeNode($layout->uid, $key);
            $projectConfig->readOnly = $readOnly;
        }
    });
});

describe('Astra Wave 1 — explicit root parent', function() {
    it('round-trips native subnav outdent to root through PC (ASTRA-03)', function() {
        $childKey = NodeKey::craftSubnav('graphql', 'tokens');
        $node = new CustomizationNode(
            key: $childKey,
            enabled: true,
            sort: 20,
            parent: CustomizationNode::PARENT_ROOT,
            label: null,
        );

        $config = $node->toConfig();
        expect($config)->toHaveKey('parent');
        expect($config['parent'])->toBe('');

        $reloaded = CustomizationNode::fromConfig(NodeKey::encodePathKey($childKey), $config);
        expect($reloaded->parent)->toBe(CustomizationNode::PARENT_ROOT);
        expect($reloaded->resolvedParent('craft:graphql'))->toBeNull();

        $inherited = CustomizationNode::fromConfig(NodeKey::encodePathKey($childKey), [
            'key' => $childKey,
            'enabled' => true,
            'sort' => 20,
        ]);
        expect($inherited->parent)->toBeNull();
        expect($inherited->resolvedParent('craft:graphql'))->toBe('craft:graphql');

        $registry = [
            new NavNode(
                key: 'craft:graphql',
                source: 'craft',
                defaultLabel: 'GraphQL',
                defaultUrl: 'graphql',
                icon: null,
                defaultOrder: 1,
                parentKey: null,
                children: [
                    new NavNode(
                        key: $childKey,
                        source: 'craft',
                        defaultLabel: 'Tokens',
                        defaultUrl: 'graphql/tokens',
                        icon: null,
                        defaultOrder: 1,
                        parentKey: 'craft:graphql',
                        children: [],
                        subHandle: 'tokens',
                    ),
                ],
            ),
        ];

        $resolved = CpNav::$plugin->getNavResolver()->resolve($registry, [
            'craft:graphql' => new CustomizationNode('craft:graphql', true, 10, CustomizationNode::PARENT_ROOT),
            $childKey => $reloaded,
        ]);

        $byKey = [];
        foreach ($resolved as $item) {
            $byKey[$item->key] = $item;
        }

        expect($byKey[$childKey]->parentKey)->toBeNull();
    });
});

describe('Astra Wave 1 — upgrade mapping', function() {
    it('keeps equal curr/prev manual labels (ASTRA-04)', function() {
        $manual = astraLayoutNavItem([
            'id' => 10,
            'type' => LayoutNavItem::TYPE_MANUAL,
            'uid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'prevLabel' => 'Docs',
            'currLabel' => 'Docs',
            'url' => 'https://example.test/docs',
            'sortOrder' => 1,
            'level' => 1,
            'parentId' => null,
        ]);

        $overlay = (new CustomizationUpgrader())->upgradeNavigations([$manual]);
        $key = NodeKey::manual('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        expect($overlay[$key]->label)->toBe('Docs');
        expect($overlay[$key]->url)->toBe('https://example.test/docs');
    });

    it('maps GraphiQL-style craft children with parent context (ASTRA-04)', function() {
        $graphql = astraLayoutNavItem([
            'id' => 1,
            'type' => LayoutNavItem::TYPE_CRAFT,
            'prevUrl' => 'graphql',
            'url' => 'graphql',
            'handle' => 'graphql',
            'prevLabel' => 'GraphQL',
            'currLabel' => 'GraphQL',
            'sortOrder' => 1,
            'level' => 1,
        ]);

        $graphiql = astraLayoutNavItem([
            'id' => 2,
            'type' => LayoutNavItem::TYPE_CRAFT,
            'prevUrl' => 'graphiql',
            'url' => 'graphiql',
            'handle' => 'graphiql',
            'prevLabel' => 'GraphiQL',
            'currLabel' => 'GraphiQL',
            'sortOrder' => 1,
            'level' => 2,
            'parentId' => 1,
        ]);

        expect(V5KeyMap::resolveKey($graphiql, $graphql))->toBe(NodeKey::craftSubnav('graphql', 'graphiql'));

        $overlay = (new CustomizationUpgrader())->upgradeNavigations([$graphql, $graphiql]);
        expect($overlay)->toHaveKey(NodeKey::craftSubnav('graphql', 'graphiql'));
        expect($overlay[NodeKey::craftSubnav('graphql', 'graphiql')]->parent)->toBe(NodeKey::craft('graphql'));
    });

    it('does not restore prevParentId when parentId is intentionally null (ASTRA-04)', function() {
        $parent = astraLayoutNavItem([
            'id' => 1,
            'type' => LayoutNavItem::TYPE_CRAFT,
            'prevUrl' => 'dashboard',
            'url' => 'dashboard',
            'handle' => 'dashboard',
            'prevLabel' => 'Dashboard',
            'currLabel' => 'Dashboard',
            'sortOrder' => 1,
            'level' => 1,
        ]);

        $manual = astraLayoutNavItem([
            'id' => 2,
            'type' => LayoutNavItem::TYPE_MANUAL,
            'uid' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
            'prevLabel' => 'Outdent',
            'currLabel' => 'Outdent',
            'url' => 'https://example.test/out',
            'sortOrder' => 2,
            'level' => 1,
            'parentId' => null,
            'prevParentId' => 1,
        ]);

        $overlay = (new CustomizationUpgrader())->upgradeNavigations([$parent, $manual]);
        $key = NodeKey::manual('bbbbbbbb-cccc-dddd-eeee-ffffffffffff');

        expect($overlay[$key]->parent)->toBe(CustomizationNode::PARENT_ROOT);
        expect($overlay[$key]->resolvedParent(null))->toBeNull();
    });
});

describe('Astra Wave 1 — migrate CLI safety', function() {
    it('skips nonempty v6 layouts and does not clear without legacy rows (ASTRA-05)', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        $layouts = CpNav::$plugin->getLayouts();
        $layout = null;

        try {
            $layout = new Layout([
                'name' => 'Astra Migrate ' . uniqid(),
                'isDefault' => false,
                'permissions' => [],
            ]);
            expect($layouts->saveLayout($layout))->toBeTrue();
            $layout = $layouts->getLayoutByUid($layout->uid);

            $created = CpNav::$plugin->getNavBuilderApi()->createNode($layout->id, [
                'type' => LayoutNavItem::TYPE_MANUAL,
                'currLabel' => 'Keep Me',
                'url' => 'https://example.test/keep',
            ]);
            $manualKey = $created['key'];

            $results = (new CustomizationUpgradeService())->upgradeLayouts($layout->uid, false, false);
            expect($results[0]['status'])->toBe('skipped_nonempty');

            $afterSkip = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
            expect($afterSkip)->toHaveKey($manualKey);

            // No legacy table rows for this layout — still must not clear (even with --force).
            $noLegacy = (new CustomizationUpgradeService())->upgradeLayouts($layout->uid, false, true);
            expect($noLegacy[0]['status'])->toBe('skipped_no_legacy');
            expect(CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid))
                ->toHaveKey($manualKey);
        } finally {
            if ($layout) {
                $layouts->deleteLayout($layout);
            }
            $projectConfig->readOnly = $readOnly;
        }
    });
});

/**
 * @param array<string, mixed> $attributes
 */
function astraLayoutNavItem(array $attributes): LayoutNavItem
{
    $navigation = new LayoutNavItem();
    $navigation->enabled = true;
    $navigation->setAttributes($attributes, false);

    return $navigation;
}
