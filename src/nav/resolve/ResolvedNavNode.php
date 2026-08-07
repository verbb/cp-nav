<?php
namespace verbb\cpnav\nav\resolve;

final class ResolvedNavNode
{
    // Properties
    // =========================================================================

    public readonly string $key;
    public readonly string $label;
    public readonly string $url;
    public readonly int $sort;
    public readonly ?string $parentKey;
    public readonly string $source;
    public readonly bool $enabled;
    public readonly ?string $icon;
    public readonly ?string $customIcon;
    public readonly bool $newWindow;
    /** Manual/divider whose parent key no longer exists — rendered at top level. */
    public readonly bool $isOrphan;


    // Public Methods
    // =========================================================================

    public function __construct(
        string $key,
        string $label,
        string $url,
        int $sort,
        ?string $parentKey,
        string $source,
        bool $enabled,
        ?string $icon = null,
        ?string $customIcon = null,
        bool $newWindow = false,
        bool $isOrphan = false,
    ) {
        $this->key = $key;
        $this->label = $label;
        $this->url = $url;
        $this->sort = $sort;
        $this->parentKey = $parentKey;
        $this->source = $source;
        $this->enabled = $enabled;
        $this->icon = $icon;
        $this->customIcon = $customIcon;
        $this->newWindow = $newWindow;
        $this->isOrphan = $isOrphan;
    }

    public function withParentKey(?string $parentKey, bool $isOrphan = false): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            url: $this->url,
            sort: $this->sort,
            parentKey: $parentKey,
            source: $this->source,
            enabled: $this->enabled,
            icon: $this->icon,
            customIcon: $this->customIcon,
            newWindow: $this->newWindow,
            isOrphan: $isOrphan,
        );
    }
}
