import type { BuilderNode } from '../types';
import {
  buildTreeChildrenMap,
  CPNAV_TREE_ROOT_ID,
  flattenTree,
  refreshHasDescendants,
  type CpNavTreeChildrenMap,
} from './headlessTreeData';

type NodeContext = {
  node: BuilderNode;
  parentKey: string;
  siblings: string[];
  index: number;
};

function buildContext(nodes: BuilderNode[], key: string): {
  nodeMap: Map<string, BuilderNode>;
  childrenMap: CpNavTreeChildrenMap;
  ctx: NodeContext | null;
} {
  const nodeMap = new Map(nodes.map((node) => [node.key, node]));
  const childrenMap = buildTreeChildrenMap(nodes);
  const node = nodeMap.get(key);

  if (!node) {
    return { nodeMap, childrenMap, ctx: null };
  }

  const parentKey = node.parentKey ?? CPNAV_TREE_ROOT_ID;
  const siblings = childrenMap[parentKey] ?? [];
  const index = siblings.indexOf(key);

  return { nodeMap, childrenMap, ctx: { node, parentKey, siblings, index } };
}

function rebuild(nodeMap: Map<string, BuilderNode>, childrenMap: CpNavTreeChildrenMap): BuilderNode[] {
  return refreshHasDescendants(flattenTree(nodeMap, childrenMap));
}

/** Deepest level reached under `key` (self counted as its own level). */
function subtreeDepth(childrenMap: CpNavTreeChildrenMap, key: string): number {
  const children = childrenMap[key] ?? [];

  if (children.length === 0) {
    return 1;
  }

  return 1 + Math.max(...children.map((child) => subtreeDepth(childrenMap, child)));
}

export type NodeMoveCapabilities = {
  canMoveUp: boolean;
  canMoveDown: boolean;
  canIndent: boolean;
  canOutdent: boolean;
};

export function getNodeMoveCapabilities(
  nodes: BuilderNode[],
  key: string,
  maxLevels: number,
): NodeMoveCapabilities {
  const { childrenMap, ctx } = buildContext(nodes, key);

  if (!ctx) {
    return { canMoveUp: false, canMoveDown: false, canIndent: false, canOutdent: false };
  }

  const isTopLevel = ctx.parentKey === CPNAV_TREE_ROOT_ID;
  const previousSiblingKey = ctx.index > 0 ? ctx.siblings[ctx.index - 1] : null;
  const previousSibling = previousSiblingKey ? nodes.find((node) => node.key === previousSiblingKey) : null;
  const targetLevel = (isTopLevel ? 1 : 2) + 1;
  // Dividers stay top-level; nothing nests under a divider.
  const canIndent =
    ctx.node.type !== 'divider' &&
    previousSibling != null &&
    previousSibling.type !== 'divider' &&
    targetLevel + subtreeDepth(childrenMap, key) - 1 <= maxLevels;

  return {
    canMoveUp: ctx.index > 0,
    canMoveDown: ctx.index >= 0 && ctx.index < ctx.siblings.length - 1,
    canIndent,
    canOutdent: !isTopLevel,
  };
}

export function moveNodeUp(nodes: BuilderNode[], key: string): BuilderNode[] {
  const { nodeMap, childrenMap, ctx } = buildContext(nodes, key);

  if (!ctx || ctx.index <= 0) {
    return nodes;
  }

  const siblings = [...ctx.siblings];
  [siblings[ctx.index - 1], siblings[ctx.index]] = [siblings[ctx.index], siblings[ctx.index - 1]];
  childrenMap[ctx.parentKey] = siblings;

  return rebuild(nodeMap, childrenMap);
}

export function moveNodeDown(nodes: BuilderNode[], key: string): BuilderNode[] {
  const { nodeMap, childrenMap, ctx } = buildContext(nodes, key);

  if (!ctx || ctx.index < 0 || ctx.index >= ctx.siblings.length - 1) {
    return nodes;
  }

  const siblings = [...ctx.siblings];
  [siblings[ctx.index + 1], siblings[ctx.index]] = [siblings[ctx.index], siblings[ctx.index + 1]];
  childrenMap[ctx.parentKey] = siblings;

  return rebuild(nodeMap, childrenMap);
}

export function indentNode(nodes: BuilderNode[], key: string, maxLevels: number): BuilderNode[] {
  const { nodeMap, childrenMap, ctx } = buildContext(nodes, key);

  if (!ctx || ctx.index <= 0 || ctx.node.type === 'divider') {
    return nodes;
  }

  const newParentKey = ctx.siblings[ctx.index - 1];
  const newParent = nodeMap.get(newParentKey);

  if (!newParent || newParent.type === 'divider') {
    return nodes;
  }

  const isTopLevel = ctx.parentKey === CPNAV_TREE_ROOT_ID;
  const targetLevel = (isTopLevel ? 1 : 2) + 1;

  if (targetLevel + subtreeDepth(childrenMap, key) - 1 > maxLevels) {
    return nodes;
  }

  childrenMap[ctx.parentKey] = ctx.siblings.filter((sibling) => sibling !== key);
  childrenMap[newParentKey] = [...(childrenMap[newParentKey] ?? []), key];

  return rebuild(nodeMap, childrenMap);
}

export function outdentNode(nodes: BuilderNode[], key: string): BuilderNode[] {
  const { nodeMap, childrenMap, ctx } = buildContext(nodes, key);

  if (!ctx || ctx.parentKey === CPNAV_TREE_ROOT_ID) {
    return nodes;
  }

  const parentNode = nodeMap.get(ctx.parentKey);
  const grandParentKey = parentNode?.parentKey ?? CPNAV_TREE_ROOT_ID;
  const grandSiblings = childrenMap[grandParentKey] ?? [];
  const parentIndex = grandSiblings.indexOf(ctx.parentKey);

  childrenMap[ctx.parentKey] = ctx.siblings.filter((sibling) => sibling !== key);

  const insertAt = parentIndex === -1 ? grandSiblings.length : parentIndex + 1;
  childrenMap[grandParentKey] = [
    ...grandSiblings.slice(0, insertAt),
    key,
    ...grandSiblings.slice(insertAt),
  ];

  return rebuild(nodeMap, childrenMap);
}
