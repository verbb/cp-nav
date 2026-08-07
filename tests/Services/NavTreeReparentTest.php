<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\builder\NavTreeReparent;

describe('NavTreeReparent', function() {
    it('indents a root under the previous root sibling', function() {
        $nodes = [
            ['builderId' => 1, 'key' => 'craft:dashboard', 'parentId' => null, 'level' => 1],
            ['builderId' => 2, 'key' => 'craft:users', 'parentId' => null, 'level' => 1],
            ['builderId' => 3, 'key' => 'craft:settings', 'parentId' => null, 'level' => 1],
        ];

        $next = NavTreeReparent::indent($nodes, 'craft:users');

        expect($next)->not->toBeNull();
        expect($next[0]['key'])->toBe('craft:dashboard');
        expect($next[1]['key'])->toBe('craft:users');
        expect($next[1]['parentId'])->toBe(1);
        expect($next[1]['level'])->toBe(2);
        expect($next[2]['key'])->toBe('craft:settings');
    });

    it('rejects indent when the previous root already has the node nested or node has children', function() {
        $withChildren = [
            ['builderId' => 1, 'key' => 'craft:graphql', 'parentId' => null, 'level' => 1],
            ['builderId' => 2, 'key' => 'craft:graphql/schemas', 'parentId' => 1, 'level' => 2],
            ['builderId' => 3, 'key' => 'craft:settings', 'parentId' => null, 'level' => 1],
        ];

        expect(NavTreeReparent::indent($withChildren, 'craft:graphql'))->toBeNull();
        expect(NavTreeReparent::canIndent($withChildren, 'craft:settings'))->toBeTrue();
        expect(NavTreeReparent::canOutdent($withChildren, 'craft:graphql/schemas'))->toBeTrue();
        expect(NavTreeReparent::canOutdent($withChildren, 'craft:graphql'))->toBeFalse();
    });

    it('outdents a child after its parent block', function() {
        $nodes = [
            ['builderId' => 1, 'key' => 'craft:dashboard', 'parentId' => null, 'level' => 1],
            ['builderId' => 2, 'key' => 'craft:users', 'parentId' => 1, 'level' => 2],
            ['builderId' => 3, 'key' => 'craft:settings', 'parentId' => null, 'level' => 1],
        ];

        $next = NavTreeReparent::outdent($nodes, 'craft:users');

        expect($next)->not->toBeNull();
        expect(array_column($next, 'key'))->toBe(['craft:dashboard', 'craft:users', 'craft:settings']);
        expect($next[1]['parentId'])->toBeNull();
        expect($next[1]['level'])->toBe(1);
    });

    it('rejects reorder payloads that exceed max depth', function() {
        $nodesById = [
            1 => ['builderId' => 1],
            2 => ['builderId' => 2],
            3 => ['builderId' => 3],
        ];

        $errors = NavTreeReparent::validateReorderItems([
            ['id' => 1, 'parentId' => null],
            ['id' => 2, 'parentId' => 1],
            ['id' => 3, 'parentId' => 2],
        ], $nodesById);

        expect($errors)->not->toBeEmpty();
    });

    it('rejects nesting dividers or nesting under dividers', function() {
        $nodes = [
            ['builderId' => 1, 'key' => 'craft:dashboard', 'parentId' => null, 'level' => 1],
            ['builderId' => 2, 'key' => 'divider:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'parentId' => null, 'level' => 1],
            ['builderId' => 3, 'key' => 'craft:settings', 'parentId' => null, 'level' => 1],
        ];

        expect(NavTreeReparent::indent($nodes, 'divider:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'))->toBeNull();
        // settings would indent under the previous root (divider) — blocked.
        expect(NavTreeReparent::canIndent($nodes, 'craft:settings'))->toBeFalse();

        $errors = NavTreeReparent::validateReorderItems([
            ['id' => 1, 'parentId' => null],
            ['id' => 2, 'parentId' => 1],
            ['id' => 3, 'parentId' => null],
        ], [
            1 => ['builderId' => 1, 'key' => 'craft:dashboard'],
            2 => ['builderId' => 2, 'key' => 'divider:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
            3 => ['builderId' => 3, 'key' => 'craft:settings'],
        ]);

        expect($errors)->not->toBeEmpty();
    });
});

describe('NavBuilder indent/outdent', function() {
    it('persists indent and outdent via customization parent', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        if (!$layout) {
            $this->markTestSkipped('No default layout in test install.');
        }

        $tree = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
        $roots = array_values(array_filter($tree['nodes'], fn(array $n) => empty($n['parentId'])));

        if (count($roots) < 2) {
            $this->markTestSkipped('Need at least two root nav items.');
        }

        // Prefer indenting a root that has no children and canIndent.
        $candidate = null;
        foreach ($roots as $i => $root) {
            if ($i === 0) {
                continue;
            }
            if (!empty($root['canIndent'])) {
                $candidate = $root;
                break;
            }
        }

        if (!$candidate) {
            $this->markTestSkipped('No indentable root item in test install.');
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        try {
            expect(CpNav::$plugin->getNavBuilderApi()->indentNode($layout->id, $candidate['key']))->toBeTrue();

            $afterIndent = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
            $indented = array_values(array_filter(
                $afterIndent['nodes'],
                fn(array $n) => ($n['key'] ?? null) === $candidate['key'],
            ))[0] ?? null;

            expect($indented)->not->toBeNull();
            expect($indented['parentId'])->not->toBeNull();
            expect($indented['canOutdent'])->toBeTrue();

            expect(CpNav::$plugin->getNavBuilderApi()->outdentNode($layout->id, $candidate['key']))->toBeTrue();

            $afterOutdent = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
            $restored = array_values(array_filter(
                $afterOutdent['nodes'],
                fn(array $n) => ($n['key'] ?? null) === $candidate['key'],
            ))[0] ?? null;

            expect($restored['parentId'])->toBeNull();
        } finally {
            CpNav::$plugin->getNavBuilderApi()->resetLayout($layout->id);
            $projectConfig->readOnly = $readOnly;
        }
    });
});
