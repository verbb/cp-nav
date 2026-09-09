<?php
namespace verbb\cpnav\nav\builder;

/**
 * Flat-list indent / outdent transforms for the nav builder (max depth 2).
 *
 * Works on nodes identified by canonical `key` / `parentKey` (not CRC32 ids).
 */
final class NavTreeReparent
{
    // Constants
    // =========================================================================

    public const MAX_DEPTH = 2;


    // Static Methods
    // =========================================================================

    public static function canIndent(array $nodes, string $key): bool
    {
        return self::indent($nodes, $key) !== null;
    }

    public static function canOutdent(array $nodes, string $key): bool
    {
        return self::outdent($nodes, $key) !== null;
    }

    /**
     * Make `$key` a child of the previous root sibling.
     *
     * @return array|null New flat node list, or null if the move is illegal.
     */
    public static function indent(array $nodes, string $key): ?array
    {
        $index = self::_indexOfKey($nodes, $key);

        if ($index === null) {
            return null;
        }

        $node = $nodes[$index];

        // Dividers are section breaks — never nest under another item.
        if (str_starts_with((string)($node['key'] ?? ''), 'divider:')) {
            return null;
        }

        // Already nested, or has children (would create depth 3).
        if (!empty($node['parentKey']) || self::_hasChildren($nodes, (string)$node['key'])) {
            return null;
        }

        $prevRootIndex = self::_previousRootIndex($nodes, $index);

        if ($prevRootIndex === null) {
            return null;
        }

        $parent = $nodes[$prevRootIndex];

        // Do not nest under a divider either.
        if (str_starts_with((string)($parent['key'] ?? ''), 'divider:')) {
            return null;
        }

        $parentKey = (string)$parent['key'];

        $moved = $nodes[$index];
        $moved['parentKey'] = $parentKey;
        $moved['level'] = 2;
        // Keep legacy parentId fields in sync when present (builder projection).
        if (array_key_exists('parentId', $moved) && array_key_exists('builderId', $parent)) {
            $moved['parentId'] = (int)$parent['builderId'];
        }

        $without = array_values(array_filter(
            $nodes,
            fn(array $n, int $i) => $i !== $index,
            ARRAY_FILTER_USE_BOTH,
        ));

        $insertAt = self::_indexOfKey($without, $parentKey);
        if ($insertAt === null) {
            return null;
        }

        $insertAt++;
        while (
            $insertAt < count($without)
            && ($without[$insertAt]['parentKey'] ?? null) === $parentKey
        ) {
            $insertAt++;
        }

        array_splice($without, $insertAt, 0, [$moved]);

        return array_values($without);
    }

    /**
     * Promote `$key` to a root, placed after its former parent's block.
     *
     * @return array|null New flat node list, or null if the move is illegal.
     */
    public static function outdent(array $nodes, string $key): ?array
    {
        $index = self::_indexOfKey($nodes, $key);

        if ($index === null) {
            return null;
        }

        $node = $nodes[$index];
        $parentKey = $node['parentKey'] ?? null;

        if (!$parentKey) {
            return null;
        }

        $parentIndex = self::_indexOfKey($nodes, (string)$parentKey);

        if ($parentIndex === null) {
            return null;
        }

        $moved = $node;
        $moved['parentKey'] = null;
        $moved['level'] = 1;
        if (array_key_exists('parentId', $moved)) {
            $moved['parentId'] = null;
        }

        $without = array_values(array_filter(
            $nodes,
            fn(array $n, int $i) => $i !== $index,
            ARRAY_FILTER_USE_BOTH,
        ));

        $insertAt = self::_indexOfKey($without, (string)$parentKey);
        if ($insertAt === null) {
            return null;
        }

        $insertAt++;
        while (
            $insertAt < count($without)
            && ($without[$insertAt]['parentKey'] ?? null) === $parentKey
        ) {
            $insertAt++;
        }

        array_splice($without, $insertAt, 0, [$moved]);

        return array_values($without);
    }

    /**
     * Validate a reorder payload against depth / parent rules.
     *
     * @param array $items [{key, parentKey}, ...]
     * @param array<string, array> $nodesByKey keyed by canonical key
     * @return string[] error messages (empty = ok)
     */
    public static function validateReorderItems(array $items, array $nodesByKey): array
    {
        $errors = [];
        $parentByKey = [];

        foreach ($items as $item) {
            $key = (string)($item['key'] ?? '');
            if ($key === '') {
                $errors[] = 'Reorder item missing key.';
                continue;
            }

            $parentKey = $item['parentKey'] ?? null;
            $parentKey = $parentKey === '' || $parentKey === null ? null : (string)$parentKey;
            $parentByKey[$key] = $parentKey;
        }

        foreach ($parentByKey as $key => $parentKey) {
            if ($parentKey === null) {
                continue;
            }

            if ($parentKey === $key) {
                $errors[] = "Node {$key} cannot be its own parent.";
                continue;
            }

            if (!isset($nodesByKey[$parentKey]) && !isset($parentByKey[$parentKey])) {
                $errors[] = "Node {$key} references unknown parent {$parentKey}.";
                continue;
            }

            if (str_starts_with($key, 'divider:')) {
                $errors[] = "Divider {$key} must stay top-level.";
                continue;
            }

            if (str_starts_with($parentKey, 'divider:')) {
                $errors[] = "Node {$key} cannot nest under a divider.";
                continue;
            }

            // Parent must itself be a root in this payload (max depth 2).
            $grandParent = $parentByKey[$parentKey] ?? null;
            if ($grandParent !== null) {
                $errors[] = "Node {$key} would nest deeper than " . self::MAX_DEPTH . " levels.";
            }

            $seen = [$key => true];
            $cursor = $parentKey;
            while ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    $errors[] = "Node {$key} would create a parent cycle.";
                    break;
                }
                $seen[$cursor] = true;
                $cursor = $parentByKey[$cursor] ?? null;
            }
        }

        return $errors;
    }

    /**
     * Convert flat tree nodes into the reorder API payload.
     *
     * @return array<int, array{key: string, parentKey: string|null}>
     */
    public static function toReorderPayload(array $nodes): array
    {
        return array_map(static fn(array $node) => [
            'key' => (string)$node['key'],
            'parentKey' => $node['parentKey'] ?? null,
        ], $nodes);
    }


    // Private Methods
    // =========================================================================

    private static function _indexOfKey(array $nodes, string $key): ?int
    {
        foreach ($nodes as $i => $node) {
            if (($node['key'] ?? null) === $key) {
                return $i;
            }
        }

        return null;
    }

    private static function _previousRootIndex(array $nodes, int $fromIndex): ?int
    {
        for ($i = $fromIndex - 1; $i >= 0; $i--) {
            if (empty($nodes[$i]['parentKey'])) {
                return $i;
            }
        }

        return null;
    }

    private static function _hasChildren(array $nodes, string $key): bool
    {
        foreach ($nodes as $node) {
            if (($node['parentKey'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }
}
