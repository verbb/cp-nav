<?php
namespace verbb\cpnav\upgrade;

use verbb\cpnav\models\LayoutNavItem;
use verbb\cpnav\nav\sources\NodeKey;

/**
 * v5 navigation → node key resolution and Craft URL remaps.
 */
final class V5KeyMap
{
    // Static Methods
    // =========================================================================

    public static function remapCraftUrl(?string $url): string
    {
        $url = trim((string)$url, '/');

        if ($url === '') {
            return 'dashboard';
        }

        return self::CRAFT_URL_REMAPS[$url] ?? $url;
    }

    /** The parent is the original provider parent, not the item's customized placement. */
    public static function resolveKey(LayoutNavItem $navigation, ?LayoutNavItem $parent = null): string
    {
        if ($navigation->isManual()) {
            return NodeKey::manual((string)$navigation->uid);
        }

        if ($navigation->isDivider()) {
            return NodeKey::divider((string)$navigation->uid);
        }

        $wasSubnav = ($navigation->prevLevel ?? $navigation->level) === 2;

        if ($navigation->isPlugin()) {
            if ($wasSubnav) {
                // Older snapshots can lack the parent row; the original URL still identifies the provider.
                $provider = $parent ?? new LayoutNavItem(['handle' => $navigation->prevUrl ?? $navigation->url]);
                $pluginHandle = self::_resolvePluginHandle($provider);

                return NodeKey::plugin($pluginHandle, (string)$navigation->handle, self::_pluginRootUrl($parent, $pluginHandle));
            }

            $pluginHandle = self::_resolvePluginHandle($navigation);

            return NodeKey::plugin($pluginHandle, null, self::_pluginRootUrl($navigation, $pluginHandle));
        }

        // Craft subnav identity is parent path + handle (e.g. craft:graphql/graphiql), not the child URL alone.
        if ($wasSubnav && $parent) {
            $parentUrl = self::remapCraftUrl($parent->prevUrl ?? $parent->url);
            $subHandle = trim((string)($navigation->handle ?: ''), '/');

            if ($subHandle === '') {
                $childUrl = self::remapCraftUrl($navigation->prevUrl ?? $navigation->url);
                $subHandle = basename($childUrl);
            }

            return NodeKey::craftSubnav($parentUrl, $subHandle);
        }

        $relativeUrl = self::remapCraftUrl($navigation->prevUrl ?? $navigation->url);

        return NodeKey::craft($relativeUrl);
    }

    public static function resolveParentKey(?LayoutNavItem $parent): ?string
    {
        if (!$parent) {
            return null;
        }

        return self::resolveKey($parent);
    }


    private static function _pluginRootUrl(?LayoutNavItem $navigation, string $pluginHandle): string
    {
        $url = trim((string)($navigation?->prevUrl ?: $navigation?->url), '/');
        $path = explode('?', explode('#', $url, 2)[0], 2)[0];

        // Incomplete legacy snapshots may retain only a customized external URL.
        // It cannot identify the provider's original root route.
        return $path === $pluginHandle || str_starts_with($path, $pluginHandle . '/') ? $url : $pluginHandle;
    }

    private static function _resolvePluginHandle(LayoutNavItem $navigation): string
    {
        $candidates = array_filter([
            $navigation->handle,
            $navigation->prevUrl,
            $navigation->url,
        ]);

        foreach ($candidates as $candidate) {
            $segment = trim((string)$candidate, '/');
            $segment = explode('/', $segment)[0] ?? $segment;

            $plugin = \Craft::$app->getPlugins()->getPlugin($segment);
            if ($plugin) {
                return $plugin->handle;
            }
        }

        $fallback = trim((string)($navigation->handle ?? $navigation->prevUrl ?? ''), '/');
        $fallback = explode('/', $fallback)[0] ?? $fallback;

        return $fallback !== '' ? $fallback : 'unknown-plugin';
    }


    // Constants
    // =========================================================================

    // Known Craft core URL changes between v5 CP Nav snapshots and Craft 5.9+.
    public const CRAFT_URL_REMAPS = [
        'entries' => 'content/entries',
    ];
}
