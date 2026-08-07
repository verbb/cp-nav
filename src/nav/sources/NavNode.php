<?php
namespace verbb\cpnav\nav\sources;

/**
 * Immutable nav source node — canonical CP nav metadata.
 */
final class NavNode
{
    // Properties
    // =========================================================================

    public readonly string $key;
    public readonly string $source;
    public readonly string $defaultLabel;
    public readonly string $defaultUrl;
    public readonly ?string $icon;
    public readonly int $defaultOrder;
    public readonly ?string $parentKey;
    public readonly array $children;
    public readonly ?string $subHandle;
    /** Craft/plugin `external` — e.g. GraphiQL opens in a new window. */
    public readonly bool $defaultExternal;


    // Public Methods
    // =========================================================================

    public function __construct(
        string $key,
        string $source,
        string $defaultLabel,
        string $defaultUrl,
        ?string $icon,
        int $defaultOrder,
        ?string $parentKey,
        array $children = [],
        ?string $subHandle = null,
        bool $defaultExternal = false,
    ) {
        $this->key = $key;
        $this->source = $source;
        $this->defaultLabel = $defaultLabel;
        $this->defaultUrl = $defaultUrl;
        $this->icon = $icon;
        $this->defaultOrder = $defaultOrder;
        $this->parentKey = $parentKey;
        $this->children = $children;
        $this->subHandle = $subHandle;
        $this->defaultExternal = $defaultExternal;
    }

    public function isTopLevel(): bool
    {
        return $this->parentKey === null;
    }

    public function flatten(): array
    {
        $nodes = [$this];

        foreach ($this->children as $child) {
            $nodes = array_merge($nodes, $child->flatten());
        }

        return $nodes;
    }
}
