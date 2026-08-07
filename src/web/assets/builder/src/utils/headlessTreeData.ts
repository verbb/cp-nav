import type { BuilderNode } from '../types';

export const CPNAV_TREE_ROOT_ID = '__cpnav_root__';

export type CpNavTreeChildrenMap = Record<string, string[]>;

/** Build parent→child key lists from a flat, ordered node array. */
export function buildTreeChildrenMap(nodes: BuilderNode[]): CpNavTreeChildrenMap {
  const children: CpNavTreeChildrenMap = {
    [CPNAV_TREE_ROOT_ID]: [],
  };

  for (const node of nodes) {
    const parentKey = node.parentKey === null || node.parentKey === undefined ? CPNAV_TREE_ROOT_ID : node.parentKey;

    if (!children[parentKey]) {
      children[parentKey] = [];
    }

    children[parentKey].push(node.key);

    if (!children[node.key]) {
      children[node.key] = [];
    }
  }

  return children;
}

/**
 * Flatten a parent→child map back into the builder's ordered node array,
 * recomputing `level`, `parentKey` and `parentId` from tree position.
 */
export function flattenTree(
  nodeMap: Map<string, BuilderNode>,
  childrenMap: CpNavTreeChildrenMap,
  parentKey: string = CPNAV_TREE_ROOT_ID,
  parentLevel = 0,
): BuilderNode[] {
  const childKeys = childrenMap[parentKey] ?? [];
  const flat: BuilderNode[] = [];

  for (const childKey of childKeys) {
    const node = nodeMap.get(childKey);

    if (!node) {
      continue;
    }

    const level = parentLevel + 1;
    const isRoot = parentKey === CPNAV_TREE_ROOT_ID;
    const parent = isRoot ? null : nodeMap.get(parentKey);

    flat.push({
      ...node,
      level,
      parentKey: isRoot ? null : parentKey,
      parentId: isRoot ? null : parent?.builderId ?? null,
    });

    flat.push(...flattenTree(nodeMap, childrenMap, childKey, level));
  }

  return flat;
}

/** Recompute `hasDescendants` after a structural change. */
export function refreshHasDescendants(nodes: BuilderNode[]): BuilderNode[] {
  const childCounts = new Map<string, number>();

  for (const node of nodes) {
    if (node.parentKey) {
      childCounts.set(node.parentKey, (childCounts.get(node.parentKey) ?? 0) + 1);
    }
  }

  return nodes.map((node) => ({
    ...node,
    hasDescendants: (childCounts.get(node.key) ?? 0) > 0,
  }));
}

/** Replace visible tree nodes with a new flat order while keeping filtered-out nodes in place. */
export function mergeReorderedTreeNodes(
  allNodes: BuilderNode[],
  treeNodeKeys: Set<string>,
  nextTreeNodes: BuilderNode[],
): BuilderNode[] {
  const nextByKey = new Map(nextTreeNodes.map((node) => [node.key, node]));
  let flatIndex = 0;

  return allNodes.map((node) => {
    if (!treeNodeKeys.has(node.key)) {
      return node;
    }

    const next = nextTreeNodes[flatIndex];

    if (next?.key === node.key) {
      flatIndex += 1;
      return next;
    }

    return nextByKey.get(node.key) ?? node;
  });
}

export function getExpandedTreeItemIds(
  nodes: BuilderNode[],
  collapsedNodeKeys: Record<string, boolean>,
): string[] {
  return nodes
    .filter((node) => node.hasDescendants && !collapsedNodeKeys[node.key])
    .map((node) => node.key);
}

/** Clone a children map so drop mutations never touch the current render snapshot. */
export function cloneTreeChildrenMap(childrenMap: CpNavTreeChildrenMap): CpNavTreeChildrenMap {
  return Object.fromEntries(
    Object.entries(childrenMap).map(([key, childIds]) => [key, [...childIds]]),
  );
}

/** Remove dragged keys from every parent list in the children map. */
export function removeIdsFromChildrenMap(
  childrenMap: CpNavTreeChildrenMap,
  draggedIds: Set<string>,
): CpNavTreeChildrenMap {
  const nextMap = cloneTreeChildrenMap(childrenMap);

  for (const parentId of Object.keys(nextMap)) {
    nextMap[parentId] = nextMap[parentId].filter((id) => !draggedIds.has(id));
  }

  return nextMap;
}
