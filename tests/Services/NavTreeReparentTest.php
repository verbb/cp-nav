<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\builder\NavTreeReparent;

describe('NavTreeReparent', function() {
    it('bulk capabilities agree with allowed tree operations', function() {
        $nodes = [
            ['key' => 'craft:first', 'parentKey' => null],
            ['key' => 'craft:parent', 'parentKey' => null],
            ['key' => 'craft:child', 'parentKey' => 'craft:parent'],
            ['key' => 'craft:next', 'parentKey' => null],
            ['key' => 'divider:section', 'parentKey' => null],
            ['key' => 'manual:after-divider', 'parentKey' => null],
            ['key' => 'manual:last', 'parentKey' => null],
        ];
        $capabilities = NavTreeReparent::getCapabilities($nodes);
        expect($capabilities)->toBe([
            'craft:first' => ['canIndent' => false, 'canOutdent' => false],
            'craft:parent' => ['canIndent' => false, 'canOutdent' => false],
            'craft:child' => ['canIndent' => false, 'canOutdent' => true],
            'craft:next' => ['canIndent' => true, 'canOutdent' => false],
            'divider:section' => ['canIndent' => false, 'canOutdent' => false],
            'manual:after-divider' => ['canIndent' => false, 'canOutdent' => false],
            'manual:last' => ['canIndent' => true, 'canOutdent' => false],
        ]);
        foreach ($nodes as $node) {
            expect($capabilities[$node['key']]['canIndent'])->toBe(NavTreeReparent::indent($nodes, $node['key']) !== null);
            expect($capabilities[$node['key']]['canOutdent'])->toBe(NavTreeReparent::outdent($nodes, $node['key']) !== null);
        }
    });
    it('indents a root under the previous root sibling', function() {
        $nodes = [
            ['builderId' => 1, 'key' => 'craft:dashboard', 'parentKey' => null, 'level' => 1],
            ['builderId' => 2, 'key' => 'craft:users', 'parentKey' => null, 'level' => 1],
            ['builderId' => 3, 'key' => 'craft:settings', 'parentKey' => null, 'level' => 1],
        ];

        $next = NavTreeReparent::indent($nodes, 'craft:users');

        expect($next)->not->toBeNull();
        expect($next[0]['key'])->toBe('craft:dashboard');
        expect($next[1]['key'])->toBe('craft:users');
        expect($next[1]['parentKey'])->toBe('craft:dashboard');
        expect($next[1]['level'])->toBe(2);
        expect($next[2]['key'])->toBe('craft:settings');
    });

    it('rejects indent when the previous root already has the node nested or node has children', function() {
        $withChildren = [
            ['builderId' => 1, 'key' => 'craft:graphql', 'parentKey' => null, 'level' => 1],
            ['builderId' => 2, 'key' => 'craft:graphql/schemas', 'parentKey' => 'craft:graphql', 'level' => 2],
            ['builderId' => 3, 'key' => 'craft:settings', 'parentKey' => null, 'level' => 1],
        ];

        expect(NavTreeReparent::indent($withChildren, 'craft:graphql'))->toBeNull();
        expect(NavTreeReparent::canIndent($withChildren, 'craft:settings'))->toBeTrue();
        expect(NavTreeReparent::canOutdent($withChildren, 'craft:graphql/schemas'))->toBeTrue();
        expect(NavTreeReparent::canOutdent($withChildren, 'craft:graphql'))->toBeFalse();
    });

    it('outdents a child after its parent block', function() {
        $nodes = [
            ['builderId' => 1, 'key' => 'craft:dashboard', 'parentKey' => null, 'level' => 1],
            ['builderId' => 2, 'key' => 'craft:users', 'parentKey' => 'craft:dashboard', 'level' => 2],
            ['builderId' => 3, 'key' => 'craft:settings', 'parentKey' => null, 'level' => 1],
        ];

        $next = NavTreeReparent::outdent($nodes, 'craft:users');

        expect($next)->not->toBeNull();
        expect(array_column($next, 'key'))->toBe(['craft:dashboard', 'craft:users', 'craft:settings']);
        expect($next[1]['parentKey'])->toBeNull();
        expect($next[1]['level'])->toBe(1);
    });

    it('rejects reorder payloads that exceed max depth', function() {
        $nodesByKey = [
            'craft:a' => ['key' => 'craft:a'],
            'craft:b' => ['key' => 'craft:b'],
            'craft:c' => ['key' => 'craft:c'],
        ];

        $errors = NavTreeReparent::validateReorderItems([
            ['key' => 'craft:a', 'parentKey' => null],
            ['key' => 'craft:b', 'parentKey' => 'craft:a'],
            ['key' => 'craft:c', 'parentKey' => 'craft:b'],
        ], $nodesByKey);

        expect($errors)->not->toBeEmpty();
    });

    it('rejects nesting dividers or nesting under dividers', function() {
        $nodes = [
            ['builderId' => 1, 'key' => 'craft:dashboard', 'parentKey' => null, 'level' => 1],
            ['builderId' => 2, 'key' => 'divider:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'parentKey' => null, 'level' => 1],
            ['builderId' => 3, 'key' => 'craft:settings', 'parentKey' => null, 'level' => 1],
        ];

        expect(NavTreeReparent::indent($nodes, 'divider:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'))->toBeNull();
        // settings would indent under the previous root (divider) — blocked.
        expect(NavTreeReparent::canIndent($nodes, 'craft:settings'))->toBeFalse();

        $errors = NavTreeReparent::validateReorderItems([
            ['key' => 'craft:dashboard', 'parentKey' => null],
            ['key' => 'divider:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'parentKey' => 'craft:dashboard'],
            ['key' => 'craft:settings', 'parentKey' => null],
        ], [
            'craft:dashboard' => ['key' => 'craft:dashboard'],
            'divider:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' => ['key' => 'divider:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
            'craft:settings' => ['key' => 'craft:settings'],
        ]);

        expect($errors)->not->toBeEmpty();
    });
});

describe('NavBuilder indent/outdent', function() {
    it('persists indent and outdent via customization parent', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $layout = CpNav::$plugin->getLayouts()->getDefaultLayout();
        expect($layout)->not->toBeNull();

        $tree = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
        $roots = array_values(array_filter($tree['nodes'], fn(array $n) => empty($n['parentKey'])));

        expect(count($roots))->toBeGreaterThanOrEqual(2);

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

        expect($candidate)->not->toBeNull();

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
            expect($indented['parentKey'])->not->toBeNull();
            expect($indented['canOutdent'])->toBeTrue();

            expect(CpNav::$plugin->getNavBuilderApi()->outdentNode($layout->id, $candidate['key']))->toBeTrue();

            $afterOutdent = CpNav::$plugin->getNavBuilderApi()->getLayoutTree($layout->id);
            $restored = array_values(array_filter(
                $afterOutdent['nodes'],
                fn(array $n) => ($n['key'] ?? null) === $candidate['key'],
            ))[0] ?? null;

            expect($restored['parentKey'])->toBeNull();
        } finally {
            CpNav::$plugin->getNavBuilderApi()->resetLayout($layout->id);
            $projectConfig->readOnly = $readOnly;
        }
    });
});
