<?php

declare(strict_types=1);

use verbb\cpnav\helpers\ManualUrl;

describe('ManualUrl', function() {
    it('allows relative paths and allowlisted schemes', function() {
        expect(ManualUrl::isAllowed('entries'))->toBeTrue();
        expect(ManualUrl::isAllowed('/admin/settings'))->toBeTrue();
        expect(ManualUrl::isAllowed('https://example.com'))->toBeTrue();
        expect(ManualUrl::isAllowed('http://example.com'))->toBeTrue();
        expect(ManualUrl::isAllowed('mailto:hello@example.com'))->toBeTrue();
        expect(ManualUrl::isAllowed('tel:+15551212'))->toBeTrue();
        expect(ManualUrl::isAllowed('//cdn.example.com/x'))->toBeTrue();
    });

    it('rejects dangerous or unknown schemes', function() {
        expect(ManualUrl::isAllowed('javascript:alert(1)'))->toBeFalse();
        expect(ManualUrl::isAllowed('data:text/html,hi'))->toBeFalse();
        expect(ManualUrl::isAllowed('ftp://files.example.com'))->toBeFalse();
        expect(ManualUrl::isAllowed(''))->toBeFalse();
        expect(ManualUrl::isAllowed(null))->toBeFalse();
    });
});
