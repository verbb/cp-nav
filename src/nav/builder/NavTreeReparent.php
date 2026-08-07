<?php
namespace verbb\cpnav\nav\builder;

/**
 * Flat-list indent / outdent transforms for the nav builder (max depth 2).
 *
 * Works on the same shape returned by {@see NavBuilderApi::getLayoutTree()} `nodes`:
 * each item has at least `key`, `builderId`, `parentId`.
 *
 * UI agents can call the HTTP actions, or reuse these static helpers client-side
 * by mirroring the rules — server remains the source of truth.
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
        if (!empty($node['parentId']) || self::_hasChildren($nodes, (int)$node['builderId'])) {
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

        $parentId = (int)$parent['builderId'];

        // Detach node (+ none of its children — roots with kids are rejected above).
        $moved = $nodes[$index];
        $moved['parentId'] = $parentId;
        $moved['level'] = 2;

        $without = array_values(array_filter(
            $nodes,
            fn(array $n, int $i) => $i !== $index,
            ARRAY_FILTER_USE_BOTH,
        ));

        // Insert after the parent's last current child (or immediately after parent).
        $insertAt = self::_indexOfBuilderId($without, $parentId);
        if ($insertAt === null) {
            return null;
        }

        $insertAt++;
        while (
            $insertAt < count($without)
            && (int)($without[$insertAt]['parentId'] ?? 0) === $parentId
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
        $parentId = $node['parentId'] ?? null;

        if (!$parentId) {
            return null;
        }

        $parentIndex = self::_indexOfBuilderId($nodes, (int)$parentId);

        if ($parentIndex === null) {
            return null;
        }

        $moved = $node;
        $moved['parentId'] = null;
        $moved['level'] = 1;

        $without = array_values(array_filter(
            $nodes,
            fn(array $n, int $i) => $i !== $index,
            ARRAY_FILTER_USE_BOTH,
        ));

        // After outdent, parent block ends at parent + its remaining children.
        $insertAt = self::_indexOfBuilderId($without, (int)$parentId);
        if ($insertAt === null) {
            return null;
        }

        $insertAt++;
        while (
            $insertAt < count($without)
            && (int)($without[$insertAt]['parentId'] ?? 0) === (int)$parentId
        ) {
            $insertAt++;
        }

        array_splice($without, $insertAt, 0, [$moved]);

        return array_values($without);
    }

    /**
     * Validate a reorder payload against depth / parent rules.
     *
     * @param array $items [{id, parentId}, ...]
     * @param array<int, array> $nodesByBuilderId keyed by builderId
     * @return string[] error messages (empty = ok)
     */
    public static function validateReorderItems(array $items, array $nodesByBuilderId): array
    {
        $errors = [];
        $parentById = [];

        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $parentId = $item['parentId'] ?? null;
            $parentById[$id] = $parentId ? (int)$parentId : null;
        }

        foreach ($parentById as $id => $parentId) {
            if ($parentId === null) {
                continue;
            }

            if ($parentId === $id) {
                $errors[] = "Node {$id} cannot be its own parent.";
                continue;
            }

            if (!isset($nodesByBuilderId[$parentId]) && !isset($parentById[$parentId])) {
                $errors[] = "Node {$id} references unknown parent {$parentId}.";
                continue;
            }

            $nodeKey = (string)($nodesByBuilderId[$id]['key'] ?? '');
            $parentKey = (string)($nodesByBuilderId[$parentId]['key'] ?? '');

            if (str_starts_with($nodeKey, 'divider:')) {
                $errors[] = "Divider {$id} must stay top-level.";
                continue;
            }

            if (str_starts_with($parentKey, 'divider:')) {
                $errors[] = "Node {$id} cannot nest under a divider.";
                continue;
            }

            // Parent must itself be a root in this payload (max depth 2).
            $grandParent = $parentById[$parentId] ?? null;
            if ($grandParent !== null) {
                $errors[] = "Node {$id} would nest deeper than " . self::MAX_DEPTH . " levels.";
            }

            // Cycle: walk parents.
            $seen = [$id => true];
            $cursor = $parentId;
            while ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    $errors[] = "Node {$id} would create a parent cycle.";
                    break;
                }
                $seen[$cursor] = true;
                $cursor = $parentById[$cursor] ?? null;
            }
        }

        return $errors;
    }

    /**
     * Convert flat tree nodes into the reorder API payload.
     *
     * @return array<int, array{id: int, parentId: int|null}>
     */
    public static function toReorderPayload(array $nodes): array
    {
        return array_map(static fn(array $node) => [
            'id' => (int)$node['builderId'],
            'parentId' => $node['parentId'] ?? null,
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

    private static function _indexOfBuilderId(array $nodes, int $builderId): ?int
    {
        foreach ($nodes as $i => $node) {
            if ((int)($node['builderId'] ?? 0) === $builderId) {
                return $i;
            }
        }

        return null;
    }

    private static function _previousRootIndex(array $nodes, int $fromIndex): ?int
    {
        for ($i = $fromIndex - 1; $i >= 0; $i--) {
            if (empty($nodes[$i]['parentId'])) {
                return $i;
            }
        }

        return null;
    }

    private static function _hasChildren(array $nodes, int $builderId): bool
    {
        foreach ($nodes as $node) {
            if ((int)($node['parentId'] ?? 0) === $builderId) {
                return true;
            }
        }

        return false;
    }
}
