<?php

declare(strict_types=1);

use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\resolve\ResolvedNavNode;
use verbb\cpnav\nav\sources\NavNode;
use verbb\cpnav\nav\sources\NodeKey;
use yii\base\Event;

describe('Astra Wave 2 — event capture', function() {
    it('includes RegisterCpNavItems additions in the source catalog (ASTRA-06)', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $handler = function(RegisterCpNavItemsEvent $event) {
            $event->navItems[] = [
                'label' => 'Astra Audit Item',
                'url' => 'astra-audit-item',
                'icon' => 'gauge',
            ];
        };

        Event::on(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler);

        try {
            CpNav::$plugin->getNavSources()->invalidate();
            $keys = CpNav::$plugin->getNavSourceBuilder()->buildKeys();

            expect($keys)->toContain(NodeKey::craft('astra-audit-item'));
        } finally {
            Event::off(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler);
            CpNav::$plugin->getNavSources()->invalidate();
        }
    });
});

describe('Astra Wave 2 — layout preview auth', function() {
    it('ignores layoutId for non-admin users (ASTRA-08)', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        $layouts = CpNav::$plugin->getLayouts();
        $layout = null;

        try {
            $layout = new Layout([
                'name' => 'Astra Preview ' . uniqid(),
                'isDefault' => false,
                'permissions' => ['some-other-group'],
            ]);
            expect($layouts->saveLayout($layout))->toBeTrue();
            $layout = $layouts->getLayoutByUid($layout->uid);

            Craft::$app->getRequest()->setQueryParams(['layoutId' => (string)$layout->id]);

            // Admin may preview.
            expect($layouts->getLayoutForCurrentUser()?->uid)->toBe($layout->uid);

            // Synthetic non-admin identity.
            $user = new \craft\elements\User();
            $user->id = 999999;
            $user->admin = false;
            Craft::$app->getUser()->setIdentity($user);

            $selected = $layouts->getLayoutForCurrentUser();
            expect($selected?->uid)->not->toBe($layout->uid);
        } finally {
            Craft::$app->getRequest()->setQueryParams([]);
            AdminUser::login();
            if ($layout) {
                $layouts->deleteLayout($layout);
            }
            $projectConfig->readOnly = $readOnly;
        }
    });
});

describe('Astra Wave 2/3 — identity and encoding', function() {
    it('uses collision-free PC path encoding (ASTRA-10)', function() {
        $a = NodeKey::craft('reports/team_one');
        $b = NodeKey::craft('reports_team/one');

        expect(NodeKey::encodePathKey($a))->not->toBe(NodeKey::encodePathKey($b));
        expect(NodeKey::decodePathKey(NodeKey::encodePathKey($a)))->toBe($a);
        expect(NodeKey::decodePathKey(NodeKey::encodePathKey($b)))->toBe($b);

        // Legacy paths still decode best-effort when no canonical key is stored.
        expect(NodeKey::encodePathKeyLegacy($a))->toBe(NodeKey::encodePathKeyLegacy($b));
    });

    it('keeps same-basename manual siblings as distinct subnav handles (ASTRA-10)', function() {
        $parent = new ResolvedNavNode('craft:dashboard', 'Dashboard', 'dashboard', 10, null, 'craft', true);
        $a = new ResolvedNavNode(
            NodeKey::manual('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
            'A',
            'https://a.example/settings',
            20,
            'craft:dashboard',
            'manual',
            true,
        );
        $b = new ResolvedNavNode(
            NodeKey::manual('bbbbbbbb-cccc-dddd-eeee-ffffffffffff'),
            'B',
            'https://b.example/settings',
            30,
            'craft:dashboard',
            'manual',
            true,
        );

        $registry = [
            new NavNode('craft:dashboard', 'craft', 'Dashboard', 'dashboard', null, 1, null),
        ];

        $items = CpNav::$plugin->getNavRenderer()->toCraftNavItems($registry, [$parent, $a, $b]);
        $subnav = $items[0]['subnav'] ?? [];

        expect($subnav)->toHaveCount(2);
        expect(array_column($subnav, 'label'))->toEqualCanonicalizing(['A', 'B']);
    });
});

describe('Astra Wave 2 — Settings visibility', function() {
    it('allows Settings for admins even when allowAdminChanges is false (ASTRA-09)', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $general = Craft::$app->getConfig()->getGeneral();
        $original = (bool)$general->allowAdminChanges;

        try {
            $general->allowAdminChanges = false;
            $resolved = [
                new ResolvedNavNode(NodeKey::craft('settings'), 'Settings', 'settings', 10, null, 'craft', true),
            ];
            $filtered = CpNav::$plugin->getNavPermissions()->filter($resolved, []);
            expect($filtered)->toHaveCount(1);
        } finally {
            $general->allowAdminChanges = $original;
        }
    });
});
