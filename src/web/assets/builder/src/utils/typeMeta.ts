import type { NodeType } from '../types';

export type TypeMeta = {
  label: string;
  typeClass: string;
  colorRgb: string;
  textColorRgb: string;
};

/**
 * Badge appearance per node type. The server (`NavBuilderApi::_serializeNode`)
 * emits the same values on each node; this map is the client-side fallback and
 * the source of truth for UI-only affordances (e.g. Add panels).
 */
export const TYPE_META: Record<NodeType, TypeMeta> = {
  craft: { label: 'Craft', typeClass: 'cpnav-type-craft', colorRgb: '37, 99, 235', textColorRgb: '37, 99, 235' },
  plugin: { label: 'Plugin', typeClass: 'cpnav-type-plugin', colorRgb: '124, 58, 237', textColorRgb: '124, 58, 237' },
  manual: { label: 'Manual', typeClass: 'cpnav-type-manual', colorRgb: '13, 148, 136', textColorRgb: '13, 148, 136' },
  divider: { label: 'Divider', typeClass: 'cpnav-type-divider', colorRgb: '107, 114, 128', textColorRgb: '107, 114, 128' },
};

export function getTypeMeta(type: string): TypeMeta {
  return TYPE_META[(type as NodeType)] ?? TYPE_META.craft;
}
