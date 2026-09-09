import type { BuilderNode, ReorderItem } from '../types';

/** Map the current flat node list to the reorder API payload (canonical keys). */
export function toReorderItems(nodes: BuilderNode[]): ReorderItem[] {
  return nodes.map((node) => ({
    key: node.key,
    parentKey: node.parentKey,
  }));
}
