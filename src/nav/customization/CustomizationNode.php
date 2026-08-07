<?php
namespace verbb\cpnav\nav\customization;

use verbb\cpnav\nav\sources\NodeKey;

/**
 * Per-layout nav customization entry (persisted in project config).
 */
final class CustomizationNode
{
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

        return new self(
            key: $canonicalKey,
            enabled: (bool)($config['enabled'] ?? true),
            sort: (int)($config['sort'] ?? 0),
            parent: $config['parent'] ?? null,
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

    public function toConfig(): array
    {
        $config = array_filter([
            'key' => $this->key,
            'sort' => $this->sort,
            'parent' => $this->parent,
            'label' => $this->label,
            'type' => $this->type,
            'url' => $this->url,
            'icon' => $this->icon,
            'customIcon' => $this->customIcon,
            'newWindow' => $this->newWindow ?: null,
        ], fn($value) => $value !== null && $value !== false);

        // Always persist enabled — `false` must survive project config round-trips.
        $config['enabled'] = $this->enabled;

        return $config;
    }
}
