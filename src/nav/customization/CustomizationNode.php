<?php
namespace verbb\cpnav\nav\customization;

use verbb\cpnav\nav\sources\NodeKey;

/**
 * Per-layout nav customization entry (persisted in project config).
 *
 * Parent contract:
 * - `null` — inherit the registry default parent (canonical) or root (manual/divider with no registry).
 * - `''` (`PARENT_ROOT`) — explicit top-level placement.
 * - non-empty string — nest under that canonical key.
 */
final class CustomizationNode
{
    // Constants
    // =========================================================================

    /** Project-config sentinel for an explicit root parent (distinct from inherit/`null`). */
    public const PARENT_ROOT = '';


    // Properties
    // =========================================================================

    public readonly string $key;
    public readonly bool $enabled;
    public readonly int $sort;
    public readonly ?string $parent;
    public readonly ?string $label;
    public readonly ?string $type;
    public readonly ?string $url;
    public readonly ?string $icon;
    public readonly ?string $customIcon;
    public readonly bool $newWindow;


    // Static Methods
    // =========================================================================

    public static function fromConfig(string $encodedOrCanonicalKey, array $config): self
    {
        $canonicalKey = (string)($config['key'] ?? NodeKey::decodePathKey($encodedOrCanonicalKey));

        // Absent parent key → inherit. Present null/'' → explicit root (legacy PC used null).
        $parent = null;
        if (array_key_exists('parent', $config)) {
            $raw = $config['parent'];
            $parent = ($raw === null || $raw === '') ? self::PARENT_ROOT : (string)$raw;
        }

        return new self(
            key: $canonicalKey,
            enabled: (bool)($config['enabled'] ?? true),
            sort: (int)($config['sort'] ?? 0),
            parent: $parent,
            label: $config['label'] ?? null,
            type: $config['type'] ?? null,
            url: $config['url'] ?? null,
            icon: $config['icon'] ?? null,
            customIcon: $config['customIcon'] ?? null,
            newWindow: (bool)($config['newWindow'] ?? false),
        );
    }


    // Public Methods
    // =========================================================================

    public function __construct(
        string $key,
        bool $enabled = true,
        int $sort = 0,
        ?string $parent = null,
        ?string $label = null,
        ?string $type = null,
        ?string $url = null,
        ?string $icon = null,
        ?string $customIcon = null,
        bool $newWindow = false,
    ) {
        $this->key = $key;
        $this->enabled = $enabled;
        $this->sort = $sort;
        $this->parent = $parent;
        $this->label = $label;
        $this->type = $type;
        $this->url = $url;
        $this->icon = $icon;
        $this->customIcon = $customIcon;
        $this->newWindow = $newWindow;
    }

    /**
     * Resolve stored parent against a registry default.
     * `null` inherits; `PARENT_ROOT` becomes resolved `null` (top-level).
     */
    public function resolvedParent(?string $registryDefaultParent): ?string
    {
        if ($this->parent === null) {
            return $registryDefaultParent;
        }

        return $this->parent === self::PARENT_ROOT ? null : $this->parent;
    }

    public function toConfig(): array
    {
        $config = array_filter([
            'key' => $this->key,
            'sort' => $this->sort,
            'label' => $this->label,
            'type' => $this->type,
            'url' => $this->url,
            'icon' => $this->icon,
            'customIcon' => $this->customIcon,
            'newWindow' => $this->newWindow ?: null,
        ], fn($value) => $value !== null && $value !== false);

        // Persist parent when explicitly set (including '' for root). Omit when inheriting.
        if ($this->parent !== null) {
            $config['parent'] = $this->parent;
        }

        // Always persist enabled — `false` must survive project config round-trips.
        $config['enabled'] = $this->enabled;

        return $config;
    }
}
