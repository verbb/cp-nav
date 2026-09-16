<?php

use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\sources\NavNode;

it('reports resolver builder and reorder costs for empty and populated nested layouts', function() {
    $layout = $this->fixtureLayout();
    $sources = CpNav::$plugin->getNavSources();
    $treeProperty = new ReflectionProperty($sources, '_tree');
    $previous = $treeProperty->getValue($sources);
    $this->onCleanup(fn() => $treeProperty->setValue($sources, $previous));
    $api = CpNav::$plugin->getNavBuilderApi();
    $customizations = CpNav::$plugin->getNavCustomization();
    $results = [];
    $db = Craft::$app->getDb();
    $commandClass = $db->commandClass;
    $db->commandClass = \Tests\Support\QueryCounter::class;
    $this->onCleanup(function() use ($db, $commandClass) { $db->commandClass = $commandClass; });
    \Tests\Support\QueryCounter::$queries = 0;
    expect((int)$db->createCommand('SELECT 1')->queryScalar())->toBe(1);
    expect(\Tests\Support\QueryCounter::$queries)->toBe(1);
    foreach ([50, 250, 1000] as $count) {
        foreach (['empty', 'partial', 'populated', 'nested'] as $scenario) {
            $registry = [];
            $overlay = [];
            for ($i = 0; $i < $count; $i++) {
                $key = 'craft:benchmark/' . $i;
                $parent = $scenario === 'nested' && $i % 2 === 1 ? 'craft:benchmark/' . ($i - 1) : null;
                $node = new NavNode($key, 'craft', 'Item ' . $i, 'benchmark/' . $i, null, $i + 1, $parent);
                if ($parent) {
                    $index = array_key_last($registry);
                    $root = $registry[$index];
                    $registry[$index] = new NavNode($root->key, 'craft', $root->defaultLabel, $root->defaultUrl, null, $root->defaultOrder, null, [$node]);
                } else {
                    $registry[] = $node;
                }
                if ($scenario !== 'empty' && ($scenario !== 'partial' || $i === 0)) {
                    $overlay[$key] = new CustomizationNode($key, true, ($count - $i) * 10, $parent ?? '', 'Edited ' . $i);
                }
            }
            $treeProperty->setValue($sources, $registry);
            $customizations->setCustomizationNodes($layout->uid, $overlay);
            foreach (['resolve', 'builder', 'reorder'] as $operation) {
                $times = [];
                $queries = [];
                for ($repeat = 0; $repeat < 4; $repeat++) {
                    \Tests\Support\QueryCounter::$queries = 0;
                    $start = hrtime(true);
                    if ($operation === 'resolve') {
                        $result = CpNav::$plugin->getNavResolver()->resolve($registry, $overlay);
                        expect(count($result))->toBe($count);
                    } elseif ($operation === 'builder') {
                        $result = $api->getLayoutTree($layout->id)['nodes'];
                        expect(count(array_unique(array_column($result, 'key'))))->toBe($count);
                    } else {
                        $tree = $api->getLayoutTree($layout->id)['nodes'];
                        $items = array_map(fn($node) => ['key' => $node['key'], 'parentKey' => $node['parentKey']], array_reverse($tree));
                        expect($api->reorderNodes($layout->id, $items))->toBeTrue();
                        $stored = $customizations->getCustomizationForLayout($layout->uid);
                        expect(count($stored))->toBe($count);
                        expect($stored[$items[0]['key']]->parent)->toBe($items[0]['parentKey'] ?? '');
                    }
                    $times[] = (hrtime(true) - $start) / 1e6;
                    $queries[] = \Tests\Support\QueryCounter::$queries;
                }
                array_shift($times);
                array_shift($queries);
                // Warm operations must not introduce a query for every menu item.
                expect(max($queries))->toBeLessThanOrEqual(3);
                $results[$count][$scenario][$operation . '_max_queries'] = max($queries);
                sort($times);
                $results[$count][$scenario][$operation . '_median_ms'] = $times[1];
            }
        }
    }
    file_put_contents(dirname(__DIR__, 2) . '/.cache/cpnav-performance.json', json_encode($results, JSON_PRETTY_PRINT));
})->group('perf');
