<?php

declare(strict_types=1);

use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
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
 * @return list<string>
 */
function captureCraftNavKeys(): array
{
    $captured = [];

    Event::on(
        Cp::class,
        Cp::EVENT_REGISTER_CP_NAV_ITEMS,
        function(RegisterCpNavItemsEvent $event) use (&$captured) {
            $captured = $event->navItems;
        },
        null,
        false,
    );

    (new Cp())->nav();

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
