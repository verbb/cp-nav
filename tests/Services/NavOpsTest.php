<?php

declare(strict_types=1);

use craft\events\ConfigEvent;
use craft\events\RegisterCpNavItemsEvent;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout;
use yii\base\Event;

describe('CP render project-config safety', function() {
    it('does not write project config when NavRenderer replaces CP nav items', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$layout) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $writes = 0;
        $handler = function(ConfigEvent $event) use (&$writes) {
            if (str_starts_with((string)($event->path ?? ''), 'cp-nav')) {
                $writes++;
            }
        };

        Event::on(get_class($projectConfig), 'update', $handler);
        Event::on(get_class($projectConfig), 'add', $handler);
        Event::on(get_class($projectConfig), 'remove', $handler);

        try {
            // Warm sources, then run the live render path Craft uses for CP nav.
            CpNav::$plugin->getNavSources()->getTree();

            $event = new RegisterCpNavItemsEvent([
                'navItems' => [
                    ['label' => 'Dashboard', 'url' => 'dashboard'],
                ],
            ]);

            CpNav::$plugin->getNavRenderer()->onRegisterCpNavItems($event);

            expect($event->navItems)->not->toBeEmpty();
            expect($writes)->toBe(0);
        } finally {
            Event::off(get_class($projectConfig), 'update', $handler);
            Event::off(get_class($projectConfig), 'add', $handler);
            Event::off(get_class($projectConfig), 'remove', $handler);
        }
    });
});

describe('Layouts priority', function() {
    it('matches by ascending sortOrder even when a later group would match first', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        $layoutsService = CpNav::$plugin->getLayouts();
        $created = [];

        try {
            // Early sortOrder + group B; Late sortOrder + group A.
            // Probe lists A before B — the old groups-outer loop would return Late.
            $early = new Layout([
                'name' => 'Priority Early ' . uniqid(),
                'isDefault' => false,
                'permissions' => ['group-uid-b'],
            ]);
            expect($layoutsService->saveLayout($early))->toBeTrue();
            $created[] = $layoutsService->getLayoutByUid($early->uid);

            $late = new Layout([
                'name' => 'Priority Late ' . uniqid(),
                'isDefault' => false,
                'permissions' => ['group-uid-a'],
            ]);
            expect($layoutsService->saveLayout($late))->toBeTrue();
            $created[] = $layoutsService->getLayoutByUid($late->uid);

            $match = $layoutsService->getLayoutMatchingPermissions(['group-uid-a', 'group-uid-b']);
            expect($match)->not->toBeNull();
            expect($match->uid)->toBe($created[0]->uid);
            expect($match->sortOrder)->toBeLessThan($created[1]->sortOrder);
        } finally {
            foreach (array_reverse($created) as $layout) {
                if ($layout) {
                    $layoutsService->deleteLayout($layout);
                }
            }
            $projectConfig->readOnly = $readOnly;
        }
    });
});

describe('Layouts duplicate', function() {
    it('copies customization nodes onto a new layout', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $source = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$source) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        try {
            $nodes = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($source->uid);
            if ($nodes === []) {
                // Seed one customization so the copy assertion is meaningful.
                CpNav::$plugin->getNavBuilderApi()->updateNode($source->id, 'craft:dashboard', [
                    'currLabel' => 'Dashboard copy-seed',
                ]);
            }

            $copy = CpNav::$plugin->getLayouts()->duplicateLayout($source, 'Duplicate Test Layout');
            expect($copy)->not->toBeNull();
            expect($copy->uid)->not->toBe($source->uid);

            $sourceNodes = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($source->uid);
            $copyNodes = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($copy->uid);

            expect(array_keys($copyNodes))->toEqualCanonicalizing(array_keys($sourceNodes));

            CpNav::$plugin->getLayouts()->deleteLayout($copy);
        } finally {
            $projectConfig->readOnly = $readOnly;
        }
    });
});
