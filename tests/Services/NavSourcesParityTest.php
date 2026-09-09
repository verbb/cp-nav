<?php

declare(strict_types=1);

use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\sources\NavSourceBuilder;
use verbb\cpnav\nav\sources\NodeKey;
use yii\base\Event;

describe('NavSourceBuilder parity', function() {
    it('matches keys derived from Craft RegisterCpNavItemsEvent capture', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $registryKeys = CpNav::$plugin->getNavSourceBuilder()->buildKeys();
        $craftKeys = captureCraftNavKeys();

        expect($registryKeys)->not->toBeEmpty();
        expect($registryKeys)->toEqual($craftKeys);
    });

    it('includes dashboard as the first nav source key', function() {
        AdminUser::login();
        CpRequestContext::activate();

        $tree = CpNav::$plugin->getNavSourceBuilder()->build();
        expect($tree[0]->key)->toBe(NodeKey::craft('dashboard'));
    });
});

/**
 * Capture Craft nav the same way NavSourceBuilder does: after ordinary handlers,
 * while NavRenderer is suppressed via isCapturing().
 *
 * @return list<string>
 */
function captureCraftNavKeys(): array
{
    $captured = [];

    $handler = function(RegisterCpNavItemsEvent $event) use (&$captured) {
        $captured = $event->navItems;
    };

    Event::on(
        Cp::class,
        Cp::EVENT_REGISTER_CP_NAV_ITEMS,
        $handler,
        null,
        true,
    );

    $reflection = new ReflectionProperty(NavSourceBuilder::class, '_capturing');
    $reflection->setAccessible(true);
    $previous = $reflection->getValue();
    $reflection->setValue(null, true);

    try {
        (new Cp())->nav();
    } finally {
        $reflection->setValue(null, $previous);
        Event::off(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler);
    }

    return extractKeysFromRawNav($captured);
}

/**
 * @param array<int, array<string, mixed>> $navItems
 * @return list<string>
 */
function extractKeysFromRawNav(array $navItems): array
{
    $keys = [];

    foreach ($navItems as $item) {
        if (!is_array($item)) {
            continue;
        }

        $relativeUrl = trim((string)($item['url'] ?? ''), '/');
        $parentKey = NodeKey::fromNavItem(['relativeUrl' => $relativeUrl]);
        $keys[] = $parentKey;

        $subnav = $item['subnav'] ?? null;
        if (!is_array($subnav)) {
            continue;
        }

        foreach ($subnav as $handle => $subItem) {
            if (!is_array($subItem)) {
                continue;
            }

            $subUrl = trim((string)($subItem['url'] ?? ''), '/');
            if ($subUrl === '') {
                $subUrl = trim($parentKey . '/' . $handle, '/');
                $subUrl = str_replace('craft:', '', $subUrl);
            }

            $keys[] = NodeKey::fromNavItem(
                ['relativeUrl' => $subUrl],
                $parentKey,
                (string)$handle,
            );
        }
    }

    return $keys;
}
