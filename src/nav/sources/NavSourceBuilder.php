<?php
namespace verbb\cpnav\nav\sources;

use Craft;
use craft\elements\User;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;

use yii\base\Event;

/**
 * Builds nav sources by delegating to Craft's CP nav pipeline.
 *
 * Captures nav items from {@see Cp::EVENT_REGISTER_CP_NAV_ITEMS} before Craft
 * normalizes URLs, so node keys use stable relative CP paths.
 */
final class NavSourceBuilder
{
    // Properties
    // =========================================================================

    private static bool $_capturing = false;


    // Static Methods
    // =========================================================================

    public static function isCapturing(): bool
    {
        return self::$_capturing;
    }


    // Public Methods
    // =========================================================================

    public function build(?User $identity = null): array
    {
        $previousIdentity = Craft::$app->getUser()->getIdentity();
        $previousRequest = Craft::$app->getRequest();

        self::$_capturing = true;

        try {
            if ($identity) {
                Craft::$app->getUser()->setIdentity($identity);
            } else {
                $this->_ensureAdminIdentity();
            }

            $this->_activateCpWebRequest();

            $navItems = $this->_captureNavItems();

            return $this->_transformTopLevel($navItems);
        } finally {
            self::$_capturing = false;
            Craft::$app->getUser()->setIdentity($previousIdentity);
            Craft::$app->set('request', $previousRequest);
        }
    }

    public function buildKeys(?User $identity = null): array
    {
        $keys = [];

        foreach ($this->build($identity) as $node) {
            foreach ($node->flatten() as $flat) {
                $keys[] = $flat->key;
            }
        }

        return $keys;
    }


    // Private Methods
    // =========================================================================

    private function _captureNavItems(): array
    {
        $captured = [];

        // Append so we run *after* ordinary RegisterCpNavItems handlers. Prepending
        // would snapshot Craft’s base array and discard project/plugin additions.
        // NavRenderer skips while isCapturing(), so it won’t replace this capture.
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

        try {
            (new Cp())->nav();
        } finally {
            // Avoid stacking duplicate handlers when build() runs more than once per request.
            Event::off(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler);
        }

        return $captured;
    }

    private function _activateCpWebRequest(): void
    {
        Craft::$app->set('request', Craft::createObject(\craft\web\Request::class));
        Craft::$app->getRequest()->setIsCpRequest(true);
        Craft::$app->getRequest()->setPathInfo('dashboard');
    }

    private function _ensureAdminIdentity(): void
    {
        if (Craft::$app->getUser()->getIsAdmin()) {
            return;
        }

        $admin = User::find()->admin(true)->status(null)->one();
        if (!$admin) {
            throw new \RuntimeException('Cannot build nav sources without an admin user.');
        }

        Craft::$app->getUser()->setIdentity($admin);
    }

    private function _transformTopLevel(array $navItems): array
    {
        $nodes = [];
        $order = 0;

        foreach ($navItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $order++;
            $relativeUrl = $this->_normalizeRelativeUrl((string)($item['url'] ?? ''));
            $key = NodeKey::fromNavItem(['relativeUrl' => $relativeUrl]);
            $children = $this->_transformSubnav($item, $key, $relativeUrl);

            $nodes[] = new NavNode(
                key: $key,
                source: $this->_guessSource($key),
                defaultLabel: (string)($item['label'] ?? ''),
                defaultUrl: $relativeUrl,
                icon: isset($item['icon']) ? (string)$item['icon'] : null,
                defaultOrder: $order,
                parentKey: null,
                children: $children,
                defaultExternal: (bool)($item['external'] ?? false),
            );
        }

        return $nodes;
    }

    private function _transformSubnav(array $parentItem, string $parentKey, string $parentRelativeUrl): array
    {
        $subnav = $parentItem['subnav'] ?? null;
        if (!is_array($subnav) || $subnav === []) {
            return [];
        }

        $children = [];
        $order = 0;

        foreach ($subnav as $handle => $subItem) {
            if (!is_array($subItem)) {
                continue;
            }

            $order++;
            $relativeUrl = $this->_normalizeRelativeUrl(
                (string)($subItem['url'] ?? ''),
                $parentRelativeUrl . '/' . $handle,
            );
            $key = NodeKey::fromNavItem(['relativeUrl' => $relativeUrl], $parentKey, (string)$handle);

            $children[] = new NavNode(
                key: $key,
                source: $this->_guessSource($key),
                defaultLabel: (string)($subItem['label'] ?? ''),
                defaultUrl: $relativeUrl,
                icon: null,
                defaultOrder: $order,
                parentKey: $parentKey,
                children: [],
                subHandle: (string)$handle,
                // Craft marks GraphiQL (and any plugin subnav) via `external`.
                defaultExternal: (bool)($subItem['external'] ?? false),
            );
        }

        return $children;
    }

    private function _normalizeRelativeUrl(string $url, ?string $fallback = null): string
    {
        $url = trim($url, '/');

        return $url !== '' ? $url : ($fallback ?? '');
    }

    private function _guessSource(string $key): string
    {
        return match (true) {
            str_starts_with($key, NodeKey::NS_PLUGIN . ':') => 'plugin',
            str_starts_with($key, NodeKey::NS_EVENT . ':') => 'event',
            default => 'craft',
        };
    }
}
