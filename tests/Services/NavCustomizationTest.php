<?php

declare(strict_types=1);

use verbb\cpnav\CpNav;
use verbb\cpnav\upgrade\V5KeyMap;
use verbb\cpnav\upgrade\CustomizationUpgrader;
use verbb\cpnav\models\LayoutNavItem;
use verbb\cpnav\nav\customization\NavCustomization;
use verbb\cpnav\nav\sources\NodeKey;
use verbb\cpnav\nav\customization\CustomizationNode;

describe('NavCustomization', function() {
    it('round-trips customization nodes through project config with encoded path keys', function() {
        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$layout) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $overlay = CpNav::$plugin->getNavCustomization();
        $layoutUid = $layout->uid;
        $key = NodeKey::craft('dashboard');

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        try {
            $node = new CustomizationNode(
                key: $key,
                enabled: true,
                sort: 15,
                parent: null,
                label: 'Home',
            );

            $overlay->saveNode($layoutUid, $node);

            $path = $overlay->nodePath($layoutUid, $key);
            expect($path)->toContain('__b64_');
            expect($projectConfig->get($path)['key'])->toBe($key);

            $loaded = $overlay->getCustomizationForLayout($layoutUid);
            expect($loaded[$key]->label)->toBe('Home');
            expect($loaded[$key]->sort)->toBe(15);
        } finally {
            $overlay->removeNode($layoutUid, $key);
            $projectConfig->readOnly = $readOnly;
        }
    });
});

describe('V5KeyMap', function() {
    it('remaps legacy craft entries url to content/entries', function() {
        expect(V5KeyMap::remapCraftUrl('entries'))->toBe('content/entries');
        expect(V5KeyMap::resolveKey(layoutNavItemFixture([
            'type' => LayoutNavItem::TYPE_CRAFT,
            'prevUrl' => 'entries',
            'url' => 'entries',
            'handle' => 'entries',
        ])))->toBe(NodeKey::craft('content/entries'));
    });

    it('resolves manual and divider keys from uid', function() {
        expect(V5KeyMap::resolveKey(layoutNavItemFixture([
            'type' => LayoutNavItem::TYPE_MANUAL,
            'uid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ])))->toBe(NodeKey::manual('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'));

        expect(V5KeyMap::resolveKey(layoutNavItemFixture([
            'type' => LayoutNavItem::TYPE_DIVIDER,
            'uid' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
        ])))->toBe(NodeKey::divider('bbbbbbbb-cccc-dddd-eeee-ffffffffffff'));
    });
});

describe('CustomizationUpgrader', function() {
    it('reconstructs sibling sort groups and label overrides', function() {
        $dashboard = layoutNavItemFixture([
            'id' => 1,
            'type' => LayoutNavItem::TYPE_CRAFT,
            'prevUrl' => 'dashboard',
            'url' => 'dashboard',
            'handle' => 'dashboard',
            'prevLabel' => 'Dashboard',
            'currLabel' => 'Home',
            'sortOrder' => 2,
            'level' => 1,
        ]);

        $entries = layoutNavItemFixture([
            'id' => 2,
            'type' => LayoutNavItem::TYPE_CRAFT,
            'prevUrl' => 'entries',
            'url' => 'entries',
            'handle' => 'entries',
            'prevLabel' => 'Entries',
            'currLabel' => 'Entries',
            'sortOrder' => 1,
            'level' => 1,
        ]);

        $overlay = (new CustomizationUpgrader())->upgradeNavigations([$dashboard, $entries]);

        expect($overlay[NodeKey::craft('content/entries')]->sort)->toBe(10);
        expect($overlay[NodeKey::craft('dashboard')]->sort)->toBe(20);
        expect($overlay[NodeKey::craft('dashboard')]->label)->toBe('Home');
        expect($overlay[NodeKey::craft('content/entries')]->label)->toBeNull();
    });
});

/**
 * @param array<string, mixed> $attributes
 */
function layoutNavItemFixture(array $attributes): LayoutNavItem
{
    $navigation = new LayoutNavItem();
    $navigation->enabled = true;
    $navigation->setAttributes($attributes, false);

    return $navigation;
}
