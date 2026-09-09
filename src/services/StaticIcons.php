<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Settings;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

/**
 * Filesystem SVG icons for nav customizations — portable Project Config paths,
 * not Craft assets (dev/prod asset IDs diverge).
 *
 * Mirrors Vizy’s BlockPreviewImages / Icons scan: root + one-level subfolders.
 */
class StaticIcons extends Component
{
    // Constants
    // =========================================================================

    private const EXTENSIONS = ['*.svg'];


    // Properties
    // =========================================================================

    private ?array $_catalog = null;


    // Public Methods
    // =========================================================================

    public function getCatalog(): array
    {
        if ($this->_catalog !== null) {
            return $this->_catalog;
        }

        $root = $this->getRootPath();
        if ($root === '' || !is_dir($root)) {
            return $this->_catalog = [];
        }

        $items = [];

        foreach ($this->_filesIn($root) as $filepath) {
            $value = pathinfo($filepath, PATHINFO_BASENAME);
            $items[] = $this->_entry($value, $filepath);
        }

        foreach (FileHelper::findDirectories($root, ['recursive' => false]) as $folder) {
            $subdir = trim(str_replace($root, '', $folder), DIRECTORY_SEPARATOR);
            if ($subdir === '' || str_contains($subdir, '..')) {
                continue;
            }
            foreach ($this->_filesIn($folder) as $filepath) {
                $value = $subdir . '/' . pathinfo($filepath, PATHINFO_BASENAME);
                $items[] = $this->_entry($value, $filepath);
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp($a['value'], $b['value']));

        return $this->_catalog = $items;
    }

    /**
     * Combobox options — optional query filters by value/label.
     */
    public function getOptions(?string $query = null): array
    {
        $query = $query !== null ? mb_strtolower(trim($query)) : '';
        $options = [];

        foreach ($this->getCatalog() as $item) {
            if ($query !== '' && !str_contains(mb_strtolower($item['value'] . ' ' . $item['label']), $query)) {
                continue;
            }

            $options[] = [
                'value' => $item['value'],
                'label' => $item['value'],
                'url' => $item['url'],
            ];
        }

        return $options;
    }

    public function resolveUrl(?string $value): ?string
    {
        $path = $this->resolveAbsolutePath($value);
        if ($path === null) {
            return null;
        }

        $mtime = @filemtime($path) ?: 0;

        return UrlHelper::actionUrl('cp-nav/static-icons/view', [
            'file' => $this->normalizeValue($value),
            'v' => $mtime,
        ]);
    }

    /**
     * Absolute filesystem path for Craft `iconSvg()` — local file only.
     */
    public function resolveAbsolutePath(?string $value): ?string
    {
        $relative = $this->normalizeValue($value);
        if ($relative === null) {
            return null;
        }

        $root = $this->getRootPath();
        if ($root === '' || !is_dir($root)) {
            return null;
        }

        $candidate = FileHelper::normalizePath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $rootReal = realpath($root);
        $fileReal = realpath($candidate);
        if ($rootReal === false || $fileReal === false) {
            return null;
        }
        if (!str_starts_with($fileReal, $rootReal . DIRECTORY_SEPARATOR) && $fileReal !== $rootReal) {
            return null;
        }
        if (!is_file($fileReal)) {
            return null;
        }

        if (strtolower(pathinfo($fileReal, PATHINFO_EXTENSION)) !== 'svg') {
            return null;
        }

        return $fileReal;
    }

    public function normalizeValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        // Legacy Craft asset JSON `[123]` — no longer portable; treat as cleared.
        if (str_starts_with(trim($value), '[')) {
            return null;
        }

        $value = str_replace('\\', '/', trim($value));
        $value = ltrim($value, '/');
        if ($value === '' || str_contains($value, '..') || str_starts_with($value, './')) {
            return null;
        }

        // Root or one nested folder: `icon.svg` or `group/icon.svg`.
        if (!preg_match('/^[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)?$/i', $value)) {
            return null;
        }

        if (!str_ends_with(strtolower($value), '.svg')) {
            return null;
        }

        return $value;
    }

    public function getRootPath(): string
    {
        /* @var Settings $settings */
        $settings = CpNav::$plugin->getSettings();

        return $settings->getIconsPath();
    }

    public function resetCache(): void
    {
        $this->_catalog = null;
    }


    // Private Methods
    // =========================================================================

    private function _filesIn(string $dir): array
    {
        try {
            return FileHelper::findFiles($dir, [
                'only' => self::EXTENSIONS,
                'recursive' => false,
            ]);
        } catch (\Throwable) {
            return [];
        }
    }

    private function _entry(string $value, string $filepath): array
    {
        $label = $this->_titleize(pathinfo($filepath, PATHINFO_FILENAME));

        return [
            'label' => $label,
            'value' => $value,
            'url' => UrlHelper::actionUrl('cp-nav/static-icons/view', [
                'file' => $value,
                'v' => @filemtime($filepath) ?: 0,
            ]),
        ];
    }

    private function _titleize(string $value): string
    {
        return StringHelper::titleize(str_replace(['-', '_'], ' ', $value));
    }
}
