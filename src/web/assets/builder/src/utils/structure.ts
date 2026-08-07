import type { BuilderNode, ReorderItem } from '../types';

/** Map the current flat node list to the reorder API payload. */
export function toReorderItems(nodes: BuilderNode[]): ReorderItem[] {
  const builderIdByKey = new Map(nodes.map((node) => [node.key, node.builderId]));

  return nodes.map((node) => ({
    id: node.builderId,
    parentId: node.parentKey ? builderIdByKey.get(node.parentKey) ?? null : null,
  }));
}
