<?php

declare(strict_types=1);

use verbb\cpnav\nav\sources\NodeKey;

describe('NodeKey', function() {
    it('builds craft keys from relative urls', function() {
        expect(NodeKey::craft('dashboard'))->toBe('craft:dashboard');
        expect(NodeKey::craft('content/entries'))->toBe('craft:content/entries');
    });

    it('builds craft subnav keys', function() {
        expect(NodeKey::craftSubnav('graphql', 'schemas'))->toBe('craft:graphql/schemas');
    });

    it('encodes and decodes project config path keys', function() {
        $key = 'craft:content/entries';
        $encoded = NodeKey::encodePathKey($key);

        expect($encoded)->toStartWith('craft__b64_');
        expect(NodeKey::decodePathKey($encoded))->toBe($key);
        expect(NodeKey::decodePathKey($encoded, $key))->toBe($key);
    });

    it('does not collide distinct paths that share underscore forms', function() {
        $a = NodeKey::craft('reports/team_one');
        $b = NodeKey::craft('reports_team/one');

        expect(NodeKey::encodePathKey($a))->not->toBe(NodeKey::encodePathKey($b));
    });
});
