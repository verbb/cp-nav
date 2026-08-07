<?php
namespace verbb\cpnav\nav\sources;

use craft\helpers\StringHelper;

/**
 * Stable node identity for nav sources + customization merge.
 */
final class NodeKey
{
    // Constants
    // =========================================================================

    public const NS_CRAFT = 'craft';
    public const NS_PLUGIN = 'plugin';
    public const NS_EVENT = 'event';
    public const NS_MANUAL = 'manual';
    public const NS_DIVIDER = 'divider';


    // Static Methods
    // =========================================================================

    public static function craft(string $relativeUrl): string
    {
        return self::NS_CRAFT . ':' . self::_normalizePath($relativeUrl);
    }

    public static function craftSubnav(string $parentRelativeUrl, string $subHandle): string
    {
        $parent = self::_normalizePath($parentRelativeUrl);
        $childPath = trim($parent . '/' . $subHandle, '/');

        return self::NS_CRAFT . ':' . $childPath;
    }

    public static function plugin(string $pluginHandle, ?string $subHandle = null): string
    {
        $handle = StringHelper::toKebabCase($pluginHandle);

        if ($subHandle) {
            return self::NS_PLUGIN . ':' . $handle . ':' . StringHelper::toKebabCase($subHandle);
        }

        return self::NS_PLUGIN . ':' . $handle;
    }

    public static function manual(string $uuid): string
    {
        return self::NS_MANUAL . ':' . $uuid;
    }

    public static function divider(string $uuid): string
    {
        return self::NS_DIVIDER . ':' . $uuid;
    }

    /**
     * Derive a key from a Craft CP nav item array (post-{@see \craft\web\twig\variables\Cp::nav()}).
     */
    public static function fromNavItem(array $item, ?string $parentKey = null, ?string $subHandle = null): string
    {
        if ($subHandle !== null && $parentKey) {
            $parentUrl = self::_parentRelativeUrlFromKey($parentKey);
            if (str_starts_with($parentKey, self::NS_PLUGIN . ':')) {
                $parts = explode(':', $parentKey, 3);
                $pluginHandle = $parts[1] ?? '';

                return self::plugin($pluginHandle, $subHandle);
            }

            return self::craftSubnav($parentUrl, $subHandle);
        }

        $relativeUrl = (string)($item['relativeUrl'] ?? $item['url'] ?? '');

        // Plugin top-level items typically use their handle as the first URL segment.
        $pluginHandle = self::_guessPluginHandle($relativeUrl);
        if ($pluginHandle) {
            return self::plugin($pluginHandle);
        }

        return self::craft($relativeUrl);
    }

    public static function encodePathKey(string $key): string
    {
        [$namespace, $path] = self::split($key);

        return $namespace . '__' . str_replace(['/', ':'], '_', $path);
    }

    public static function decodePathKey(string $encoded, ?string $canonicalKey = null): string
    {
        if ($canonicalKey) {
            return $canonicalKey;
        }

        if (!str_contains($encoded, '__')) {
            return $encoded;
        }

        [$namespace, $path] = explode('__', $encoded, 2);

        return $namespace . ':' . str_replace('_', '/', $path);
    }

    public static function split(string $key): array
    {
        $pos = strpos($key, ':');
        if ($pos === false) {
            return ['', $key];
        }

        return [substr($key, 0, $pos), substr($key, $pos + 1)];
    }

    public static function isCustomizationOnly(string $key): bool
    {
        return str_starts_with($key, self::NS_MANUAL . ':') || str_starts_with($key, self::NS_DIVIDER . ':');
    }

    public static function isDivider(string $key): bool
    {
        return str_starts_with($key, self::NS_DIVIDER . ':');
    }

    /** UUID segment of a `divider:{uuid}` key, or null. */
    public static function dividerUuid(string $key): ?string
    {
        if (!self::isDivider($key)) {
            return null;
        }

        [, $uuid] = self::split($key);

        return $uuid !== '' ? $uuid : null;
    }

    public static function isManual(string $key): bool
    {
        return str_starts_with($key, self::NS_MANUAL . ':');
    }


    // Private Methods
    // =========================================================================

    private static function _normalizePath(string $path): string
    {
        $path = trim($path);
        $path = trim($path, '/');

        return $path === '' ? 'dashboard' : $path;
    }

    private static function _parentRelativeUrlFromKey(string $parentKey): string
    {
        [, $path] = self::split($parentKey);

        return $path;
    }

    private static function _guessPluginHandle(string $relativeUrl): ?string
    {
        $relativeUrl = self::_normalizePath($relativeUrl);
        $segment = explode('/', $relativeUrl)[0] ?? null;
        if (!$segment) {
            return null;
        }

        $plugin = \Craft::$app->getPlugins()->getPlugin($segment);

        return $plugin?->handle;
    }
}
