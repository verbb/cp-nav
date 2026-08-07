export type NodeType = 'craft' | 'plugin' | 'manual' | 'divider';

export type CustomIconAsset = {
  id: number;
  title: string;
  url: string | null;
  thumbUrl: string | null;
};

/**
 * A single CP Nav builder row. Nodes are identified by their string `key`
 * (`craft:…`, `plugin:…`, `manual:…`, `divider:…`) — that is the id used by
 * @headless-tree. `builderId` is the synthetic numeric id the reorder API expects.
 */
export type BuilderNode = {
  builderId: number;
  key: string;
  title: string;
  label: string;
  defaultLabel: string | null;
  url: string | null;
  type: NodeType;
  typeLabel: string;
  typeClass: string;
  typeColorRgb: string;
  typeTextColorRgb: string;
  enabled: boolean;
  level: number;
  parentId: number | null;
  parentKey: string | null;
  sort: number;
  newWindow: boolean;
  /** Stored Craft/system icon (`fontIcon:…`, `@appicons/…`, path, `title`, or null) — not editable in the builder. */
  icon: string | null;
  /** User-uploaded SVG asset id override, when set. */
  customIcon: number | null;
  customIconAsset: CustomIconAsset | null;
  isCustomized: boolean;
  isNew: boolean;
  isOrphan: boolean;
  /** Server: may nest under the previous root sibling (max depth 2). */
  canIndent: boolean;
  /** Server: may promote to top level. */
  canOutdent: boolean;
  deletable: boolean;
  hasDescendants: boolean;
};

export type LayoutOption = {
  id: number;
  name: string;
};

export type LayoutMeta = {
  id: number;
  uid: string;
  name: string;
  maxLevels: number;
};

export type LayoutTreeResponse = {
  layout: LayoutMeta;
  nodes: BuilderNode[];
  meta: {
    newItemCount: number;
    /** Max nesting depth — currently 2. */
    maxDepth: number;
    assetSources?: string[];
  };
};

/** Payload shape sent to `cp-nav/api/reorder-nodes`. */
export type ReorderItem = {
  id: number;
  parentId: number | null;
};

declare global {
  interface Window {
    CpNavBuilderConfig?: {
      layoutId: number;
      csrfTokenName: string;
      csrfTokenValue: string;
    };
    Garnish: GarnishGlobal;
    $: JQueryStatic;
  }
}

type GarnishGlobal = {
  getPostData: ($form: JQuery) => string;
};

type JQueryStatic = (selector: string | Element | unknown) => JQuery;
type JQuery = {
  data: (key: string, value?: unknown) => unknown;
  find: (selector: string) => JQuery;
  length: number;
  [index: number]: Element;
};

export {};
