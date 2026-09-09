<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\sources\NavFingerprint;
use verbb\cpnav\nav\sources\NavSourcesInvalidator;

describe('NavSources cache', function() {
    it('stores the built tree in Craft cache keyed by fingerprint', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSources();
        $registry->invalidate();

        $fingerprint = (new NavFingerprint())->compute();
        $tree = $registry->getTree(true);
        $generation = (int)Craft::$app->getCache()->get('cpnav:sources:generation');
        $cached = Craft::$app->getCache()->get('cpnav:sources:v3:' . $generation . ':' . $fingerprint);

        expect($tree)->not->toBeEmpty();
        expect($cached)->toBeArray();
        expect($cached[0]['key'] ?? null)->toBe($tree[0]->key);
    });

    it('reuses cached tree on subsequent getTree calls', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSources();
        $registry->invalidate();

        $firstKeys = array_map(fn($n) => $n->key, $registry->getTree(true));
        $secondKeys = array_map(fn($n) => $n->key, $registry->getTree());

        expect($secondKeys)->toEqual($firstKeys);
    });

    it('clears in-memory cache when invalidated and bumps shared generation', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSources();
        $registry->getTree(true);
        $generationBefore = (int)Craft::$app->getCache()->get('cpnav:sources:generation');

        (new NavSourcesInvalidator())->invalidateSources();

        $generationAfter = (int)Craft::$app->getCache()->get('cpnav:sources:generation');
        expect($generationAfter)->toBe($generationBefore + 1);

        // Should still resolve after invalidation (rebuild under new generation).
        $keys = array_map(fn($n) => $n->key, $registry->getTree());
        expect($keys)->not->toBeEmpty();
    });

    it('partitions cache by language', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $original = Craft::$app->language;
        $registry = CpNav::$plugin->getNavSources();

        try {
            Craft::$app->language = 'en-US';
            $registry->invalidate();
            $enFingerprint = (new NavFingerprint())->compute();

            Craft::$app->language = 'fr';
            $registry->invalidate();
            $frFingerprint = (new NavFingerprint())->compute();

            expect($frFingerprint)->not->toBe($enFingerprint);
        } finally {
            Craft::$app->language = $original;
            $registry->invalidate();
        }
    });
});

describe('NavFingerprint', function() {
    it('is stable for unchanged install state', function() {
        $fingerprint = new NavFingerprint();

        expect($fingerprint->compute())->toBe($fingerprint->compute());
    });

    it('changes when install inputs change', function() {
        $general = Craft::$app->getConfig()->getGeneral();
        $original = (bool)$general->enableGql;

        try {
            $before = (new NavFingerprint())->compute();
            $general->enableGql = !$original;
            $after = (new NavFingerprint())->compute();

            expect($after)->not->toBe($before);
        } finally {
            $general->enableGql = $original;
        }
    });
});

describe('NavSources fingerprint mismatch', function() {
    it('re-syncs when in-memory tree is cleared', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSources();
        $registry->invalidate();
        $registry->getTree(true);

        $reflection = new ReflectionClass($registry);
        $treeProp = $reflection->getProperty('_tree');
        $treeProp->setAccessible(true);
        $treeProp->setValue($registry, null);

        $fingerprintProp = $reflection->getProperty('_fingerprint');
        $fingerprintProp->setAccessible(true);
        $fingerprintProp->setValue($registry, null);

        $tree = $registry->getTree();

        expect($tree)->not->toBeEmpty();
        expect($fingerprintProp->getValue($registry))->not->toBeNull();
    });

    it('writes a new cache entry when fingerprint changes', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $general = Craft::$app->getConfig()->getGeneral();
        $original = (bool)$general->enableGql;

        try {
            $registry = CpNav::$plugin->getNavSources();
            $registry->invalidate();

            $fingerprintBefore = (new NavFingerprint())->compute();
            $registry->getTree(true);
            $generation = (int)Craft::$app->getCache()->get('cpnav:sources:generation');
            expect(Craft::$app->getCache()->get('cpnav:sources:v3:' . $generation . ':' . $fingerprintBefore))->toBeArray();

            $general->enableGql = !$original;
            $registry->invalidate();

            $fingerprintAfter = (new NavFingerprint())->compute();
            expect($fingerprintAfter)->not->toBe($fingerprintBefore);

            $registry->getTree(true);
            $generationAfter = (int)Craft::$app->getCache()->get('cpnav:sources:generation');
            expect(Craft::$app->getCache()->get('cpnav:sources:v3:' . $generationAfter . ':' . $fingerprintAfter))->toBeArray();
        } finally {
            $general->enableGql = $original;
        }
    });
});
