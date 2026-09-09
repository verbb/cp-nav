<?php
namespace verbb\cpnav\helpers;

use verbb\cpnav\CpNav;

/**
 * Resolve stored customIcon values for CP nav rendering and builder previews.
 *
 * Canonical storage is a relative SVG path under the plugin icons folder
 * (e.g. `brand.svg` or `team/logo.svg`) — portable in project config.
 * Legacy `[assetId]` JSON is ignored (no longer portable across environments).
 */
final class CustomIcon
{
    // Static Methods
    // =========================================================================

    /**
     * Filesystem path Craft’s CP nav can load via `iconSvg()`, or null.
     */
    public static function resolveSvgSource(?string $customIcon): ?string
    {
        return CpNav::$plugin->getStaticIcons()->resolveAbsolutePath($customIcon);
    }

    /**
     * Builder preview: relative path + stream URL when resolvable.
     *
     * @return array{path: string, url: ?string, label: string}|null
     */
    public static function serializeForBuilder(?string $customIcon): ?array
    {
        $path = CpNav::$plugin->getStaticIcons()->normalizeValue($customIcon);

        if ($path === null) {
            return null;
        }

        return [
            'path' => $path,
            'url' => CpNav::$plugin->getStaticIcons()->resolveUrl($path),
            'label' => $path,
        ];
    }

    /**
     * Normalize incoming builder value to a portable relative path, or null.
     */
    public static function normalizeStored(mixed $customIcon): ?string
    {
        if ($customIcon === null || $customIcon === '' || $customIcon === []) {
            return null;
        }

        if (is_array($customIcon)) {
            // Legacy asset id list — drop.
            return null;
        }

        if (is_int($customIcon) || (is_string($customIcon) && ctype_digit($customIcon))) {
            return null;
        }

        return CpNav::$plugin->getStaticIcons()->normalizeValue((string)$customIcon);
    }
}
