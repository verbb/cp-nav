<?php
namespace verbb\cpnav\helpers;

use Craft;
use craft\base\LocalFsInterface;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\helpers\Json;

use Throwable;

/**
 * Resolve stored customIcon JSON (`[assetId]`) for CP nav rendering and builder previews.
 *
 * Craft’s sidebar `iconSvg()` only accepts a filesystem path or inline `<svg>` markup —
 * public asset URLs are not fetched. Strategy:
 *
 * 1. Local FS path when the volume filesystem is local
 * 2. Otherwise stream SVG contents from any FS (`Asset::getContents()`) and pass markup
 *
 * Builder chips can still use a public URL / data-URI / CP thumb for `<img>` previews.
 */
final class CustomIcon
{
    // Static Methods
    // =========================================================================

    /**
     * Filesystem path or inline SVG markup Craft’s CP nav can load via `iconSvg()`.
     * Returns null when the asset is missing / not SVG / unreadable.
     */
    public static function resolveSvgSource(?string $customIcon): ?string
    {
        $asset = self::svgAssetFromStored($customIcon);

        if (!$asset) {
            return null;
        }

        $localPath = self::localFilesystemPath($asset);

        if ($localPath !== null) {
            return $localPath;
        }

        // S3 / remote / local-without-direct-path: Html::svg() will not fetch URLs.
        // Inline the SVG markup so iconSvg() can sanitize and render it.
        return self::svgContents($asset);
    }

    /**
     * Builder chip preview: public URL, else data-URI from file/stream, else CP thumb.
     *
     * @return array{id: int, title: string, url: ?string, thumbUrl: ?string}|null
     */
    public static function serializeForBuilder(?string $customIcon): ?array
    {
        $id = self::assetIdFromStored($customIcon);

        if (!$id) {
            return null;
        }

        $asset = Craft::$app->getAssets()->getAssetById($id);

        if (!$asset) {
            return null;
        }

        $url = $asset->getUrl();
        $thumbUrl = $url;

        if (!$thumbUrl && strtolower($asset->getExtension()) === 'svg') {
            $contents = self::svgContents($asset);

            if ($contents !== null) {
                $thumbUrl = 'data:image/svg+xml;base64,' . base64_encode($contents);
            }
        }

        if (!$thumbUrl) {
            // Craft 5: thumbs live on the Assets service (Asset::getThumbUrl() removed).
            $thumbUrl = Craft::$app->getAssets()->getThumbUrl($asset, 30);
        }

        return [
            'id' => (int)$asset->id,
            'title' => (string)$asset->title,
            'url' => $url,
            'thumbUrl' => $thumbUrl,
        ];
    }

    public static function assetIdFromStored(?string $customIcon): ?int
    {
        if (!$customIcon) {
            return null;
        }

        try {
            $id = Json::decode($customIcon)[0] ?? null;

            return $id ? (int)$id : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function svgAssetFromStored(?string $customIcon): ?Asset
    {
        $id = self::assetIdFromStored($customIcon);

        if (!$id) {
            return null;
        }

        try {
            $asset = Craft::$app->getAssets()->getAssetById($id);

            if (!$asset || strtolower($asset->getExtension()) !== 'svg') {
                return null;
            }

            return $asset;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Absolute local path when the volume FS is local and the file exists on disk.
     */
    public static function localFilesystemPath(Asset $asset): ?string
    {
        try {
            $volume = $asset->getVolume();
            $fs = $volume->getFs();

            if (!$fs instanceof LocalFsInterface) {
                return null;
            }

            // Same composition Craft uses for transform source paths on local volumes.
            $path = FileHelper::normalizePath(
                $fs->getRootPath() . DIRECTORY_SEPARATOR . $volume->getSubpath() . $asset->getPath()
            );

            return @file_exists($path) ? $path : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * SVG file contents from any filesystem (local or remote). Null if empty / not SVG markup.
     */
    public static function svgContents(Asset $asset): ?string
    {
        try {
            // Prefer a direct local read when we already know the path (avoids stream setup).
            $localPath = self::localFilesystemPath($asset);

            if ($localPath !== null) {
                $contents = @file_get_contents($localPath);
            } else {
                // Remote FS (S3, etc.) or local path we couldn’t resolve — Craft streams it.
                $contents = $asset->getContents();
            }

            if (!is_string($contents) || $contents === '' || stripos($contents, '<svg') === false) {
                return null;
            }

            return $contents;
        } catch (Throwable) {
            return null;
        }
    }
}
