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
        $cached = Craft::$app->getCache()->get('cpnav:sources:v2:' . $fingerprint);

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

    it('clears in-memory cache when invalidated', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSources();
        $registry->getTree(true);

        (new NavSourcesInvalidator())->invalidateSources();

        // Should still resolve after invalidation (hydrate from Craft cache or rebuild).
        $keys = array_map(fn($n) => $n->key, $registry->getTree());
        expect($keys)->not->toBeEmpty();
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
    it('re-syncs when in-memory fingerprint is stale', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registry = CpNav::$plugin->getNavSources();
        $registry->invalidate();
        $registry->getTree(true);

        $reflection = new ReflectionClass($registry);
        $fingerprintProp = $reflection->getProperty('_fingerprint');
        $fingerprintProp->setAccessible(true);
        $fingerprintProp->setValue($registry, 'stale-fingerprint');

        $currentFingerprint = (new NavFingerprint())->compute();
        $tree = $registry->getTree();

        expect($fingerprintProp->getValue($registry))->toBe($currentFingerprint);
        expect($tree)->not->toBeEmpty();
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
            expect(Craft::$app->getCache()->get('cpnav:sources:v2:' . $fingerprintBefore))->toBeArray();

            $general->enableGql = !$original;
            $registry->invalidate();

            $fingerprintAfter = (new NavFingerprint())->compute();
            expect($fingerprintAfter)->not->toBe($fingerprintBefore);

            $registry->getTree(true);
            expect(Craft::$app->getCache()->get('cpnav:sources:v2:' . $fingerprintAfter))->toBeArray();
        } finally {
            $general->enableGql = $original;
        }
    });
});
