import { create } from 'zustand';
import type { BuilderNode, LayoutMeta, LayoutOption } from './types';
import type { EditorSession } from './utils/nodeEditor';
import {
  acknowledgeNewItems as acknowledgeNewItemsApi,
  createNode as createNodeApi,
  deleteNode as deleteNodeApi,
  displayError,
  fetchLayoutTree,
  reorderNodes,
  resetLayout as resetLayoutApi,
  t,
  updateNode as updateNodeApi,
} from './api';
import { getCraft } from './utils/cp';
import { toReorderItems } from './utils/structure';
import { refreshHasDescendants } from './utils/headlessTreeData';
import {
  indentNode,
  moveNodeDown,
  moveNodeUp,
  outdentNode,
} from './utils/treeOps';

type NodeMutationData = {
  currLabel?: string;
  url?: string;
  newWindow?: boolean;
  enabled?: boolean;
  icon?: string | null;
  /** Relative SVG path under iconsPath, or null to clear. */
  customIcon?: string | null;
};

type BuilderStore = {
  layoutId: number;
  layouts: LayoutOption[];
  layout: LayoutMeta | null;
  maxLevels: number;
  loading: boolean;
  reordering: boolean;
  resettingLayout: boolean;
  error: string | null;

  nodes: BuilderNode[];
  newItemCount: number;
  /** Configured icons folder (alias string from settings). */
  iconsPath: string;

  /** Create/edit HUD session — null when closed. */
  editorSession: EditorSession | null;
  collapsedNodeKeys: Record<string, boolean>;

  init: (layoutId: number, layouts: LayoutOption[]) => Promise<void>;
  refresh: () => Promise<void>;
  /** Optimistic structure update, then persist via reorder-nodes. */
  setNodes: (nodes: BuilderNode[]) => void;
  openCreateEditor: (type: 'manual' | 'divider') => void;
  openEditEditor: (nodeKey: string) => void;
  closeEditor: () => void;
  toggleNodeCollapsed: (key: string) => void;
  expandNodeCollapsed: (key: string) => void;

  moveNodeUp: (key: string) => void;
  moveNodeDown: (key: string) => void;
  indent: (key: string) => void;
  outdent: (key: string) => void;

  /** Returns true when the server accepted the mutation. */
  updateNode: (key: string, data: NodeMutationData) => Promise<boolean>;
  toggleEnabled: (key: string, enabled: boolean) => Promise<void>;
  createNode: (data: {
    type: 'manual' | 'divider';
    currLabel?: string;
    url?: string;
    newWindow?: boolean;
  }) => Promise<boolean>;
  deleteNode: (key: string) => Promise<void>;
  /** Clear this layout’s customizations (builder header → Reset navigation). */
  resetLayout: () => Promise<void>;
  acknowledgeNewItems: () => Promise<void>;
};

export const useBuilderStore = create<BuilderStore>((set, get) => {
  // Monotonic counters so a slow response cannot overwrite a newer user intent (ASTRA-13).
  let treeEpoch = 0;
  const toggleGeneration: Record<string, number> = {};

  const nextTreeEpoch = () => ++treeEpoch;

  const applyMeta = (meta: {
    newItemCount?: number;
    iconsPath?: string;
  }) => ({
    ...(meta.newItemCount !== undefined ? { newItemCount: meta.newItemCount } : {}),
    ...(meta.iconsPath !== undefined ? { iconsPath: meta.iconsPath } : {}),
  });

  const applyServerTree = (
    serverNodes: BuilderNode[] | undefined,
    meta: { newItemCount?: number; iconsPath?: string } | undefined,
    epoch: number,
    layoutIdAtStart: number,
  ) => {
    if (epoch !== treeEpoch || get().layoutId !== layoutIdAtStart) {
      return;
    }

    if (!serverNodes) {
      void get().refresh();
      return;
    }

    set({
      nodes: serverNodes,
      ...(meta ? applyMeta(meta) : {}),
    });
  };

  /** Persist the current (or provided) flat order immediately. */
  const persistStructure = async (nodes: BuilderNode[]) => {
    const { layoutId } = get();
    const previous = get().nodes;
    const epoch = nextTreeEpoch();

    set({ nodes, reordering: true });

    try {
      const res = await reorderNodes(layoutId, toReorderItems(nodes));

      if (epoch !== treeEpoch || get().layoutId !== layoutId) {
        return;
      }

      if (res.tree) {
        set({
          layout: res.tree.layout,
          maxLevels: res.tree.layout.maxLevels ?? 2,
          nodes: res.tree.nodes,
          ...applyMeta(res.tree.meta),
        });
      }

      getCraft().cp.displayNotice(res.message ?? t('New position saved.'));
    } catch (error) {
      if (epoch === treeEpoch && get().layoutId === layoutId) {
        set({ nodes: previous });
      }
      displayError(error);
      await get().refresh();
    } finally {
      if (epoch === treeEpoch) {
        set({ reordering: false });
      }
    }
  };

  return {
    layoutId: 0,
    layouts: [],
    layout: null,
    maxLevels: 2,
    loading: true,
    reordering: false,
    resettingLayout: false,
    error: null,

    nodes: [],
    newItemCount: 0,
    iconsPath: '',

    editorSession: null,
    collapsedNodeKeys: {},

    init: async (layoutId, layouts) => {
      nextTreeEpoch();
      set({ loading: true, layoutId, layouts, error: null, editorSession: null });

      try {
        const data = await fetchLayoutTree(layoutId);

        if (get().layoutId !== layoutId) {
          return;
        }

        set({
          layout: data.layout,
          maxLevels: data.layout.maxLevels ?? 2,
          nodes: data.nodes,
          ...applyMeta(data.meta),
          loading: false,
        });
      } catch (error) {
        displayError(error);
        if (get().layoutId === layoutId) {
          set({ loading: false, error: t('Couldn’t load navigation.') });
        }
      }
    },

    refresh: async () => {
      const { layoutId } = get();
      const epoch = nextTreeEpoch();

      try {
        const data = await fetchLayoutTree(layoutId);

        if (epoch !== treeEpoch || get().layoutId !== layoutId) {
          return;
        }

        set({
          layout: data.layout,
          maxLevels: data.layout.maxLevels ?? 2,
          nodes: data.nodes,
          ...applyMeta(data.meta),
        });
      } catch (error) {
        displayError(error);
      }
    },

    setNodes: (nodes) => {
      void persistStructure(refreshHasDescendants(nodes));
    },

    openCreateEditor: (type) =>
      set((state) => ({
        editorSession:
          state.editorSession?.kind === 'create' && state.editorSession.type === type
            ? null
            : { kind: 'create', type },
      })),
    openEditEditor: (nodeKey) =>
      set((state) => ({
        editorSession:
          state.editorSession?.kind === 'edit' && state.editorSession.nodeKey === nodeKey
            ? null
            : { kind: 'edit', nodeKey },
      })),
    closeEditor: () => set({ editorSession: null }),

    toggleNodeCollapsed: (key) => {
      const { collapsedNodeKeys } = get();
      const next = { ...collapsedNodeKeys };

      if (next[key]) {
        delete next[key];
      } else {
        next[key] = true;
      }

      set({ collapsedNodeKeys: next });
    },

    expandNodeCollapsed: (key) => {
      const { collapsedNodeKeys } = get();

      if (!collapsedNodeKeys[key]) {
        return;
      }

      const next = { ...collapsedNodeKeys };
      delete next[key];
      set({ collapsedNodeKeys: next });
    },

    moveNodeUp: (key) => get().setNodes(moveNodeUp(get().nodes, key)),
    moveNodeDown: (key) => get().setNodes(moveNodeDown(get().nodes, key)),
    indent: (key) => {
      const { nodes, maxLevels } = get();
      get().setNodes(indentNode(nodes, key, maxLevels));
    },
    outdent: (key) => get().setNodes(outdentNode(get().nodes, key)),

    updateNode: async (key, data) => {
      const { layoutId } = get();
      const epoch = nextTreeEpoch();

      try {
        const res = await updateNodeApi(layoutId, key, data);

        if (epoch !== treeEpoch || get().layoutId !== layoutId) {
          return true;
        }

        getCraft().cp.displayNotice(res.message ?? t('Navigation updated.'));
        applyServerTree(res.tree?.nodes, res.tree?.meta, epoch, layoutId);
        return true;
      } catch (error) {
        displayError(error);
        return false;
      }
    },

    toggleEnabled: async (key, enabled) => {
      const { layoutId, nodes } = get();
      const previousEnabled = nodes.find((node) => node.key === key)?.enabled;
      const gen = (toggleGeneration[key] ?? 0) + 1;
      toggleGeneration[key] = gen;

      // Optimistic — don’t wait on the network, and don’t toast. Craft’s
      // `#notifications` is a fixed hit layer over the Show column, so a notice
      // per toggle blocks rapid clicking until it auto-dismisses.
      set({
        nodes: nodes.map((node) => (node.key === key ? { ...node, enabled } : node)),
      });

      try {
        const res = await updateNodeApi(layoutId, key, { enabled });

        // A newer toggle for this row (or layout switch) wins — ignore this response.
        if (toggleGeneration[key] !== gen || get().layoutId !== layoutId) {
          return;
        }

        const serverNode = res.tree?.nodes?.find((node) => node.key === key);

        // Patch only this row — a full tree replace would clobber other in-flight toggles.
        set({
          nodes: get().nodes.map((node) => {
            if (node.key !== key) {
              return node;
            }

            if (!serverNode) {
              return { ...node, enabled };
            }

            return {
              ...node,
              enabled: serverNode.enabled,
              isCustomized: serverNode.isCustomized,
            };
          }),
          ...(res.tree?.meta ? applyMeta(res.tree.meta) : {}),
        });
      } catch (error) {
        if (toggleGeneration[key] === gen && previousEnabled !== undefined) {
          set({
            nodes: get().nodes.map((node) =>
              node.key === key ? { ...node, enabled: previousEnabled } : node,
            ),
          });
        }

        displayError(error);
      }
    },

    createNode: async (data) => {
      const { layoutId } = get();
      const epoch = nextTreeEpoch();

      try {
        const res = await createNodeApi(layoutId, data);

        if (epoch !== treeEpoch || get().layoutId !== layoutId) {
          return true;
        }

        getCraft().cp.displayNotice(res.message ?? t('Navigation item added.'));
        applyServerTree(res.tree?.nodes, res.tree?.meta, epoch, layoutId);
        return true;
      } catch (error) {
        displayError(error);
        return false;
      }
    },

    deleteNode: async (key) => {
      const { layoutId, editorSession } = get();
      const epoch = nextTreeEpoch();

      try {
        const res = await deleteNodeApi(layoutId, key);

        if (epoch !== treeEpoch || get().layoutId !== layoutId) {
          return;
        }

        getCraft().cp.displayNotice(res.message ?? t('Navigation item deleted.'));

        if (editorSession?.kind === 'edit' && editorSession.nodeKey === key) {
          set({ editorSession: null });
        }

        applyServerTree(res.tree?.nodes, res.tree?.meta, epoch, layoutId);
      } catch (error) {
        displayError(error);
      }
    },

    resetLayout: async () => {
      const { layoutId } = get();
      const epoch = nextTreeEpoch();
      set({ resettingLayout: true });

      try {
        const res = await resetLayoutApi(layoutId);

        if (epoch !== treeEpoch || get().layoutId !== layoutId) {
          return;
        }

        getCraft().cp.displayNotice(res.message ?? t('Navigation reset.'));
        set({ editorSession: null, collapsedNodeKeys: {} });
        applyServerTree(res.tree?.nodes, res.tree?.meta, epoch, layoutId);

        if (res.tree?.layout) {
          set({
            layout: res.tree.layout,
            maxLevels: res.tree.layout.maxLevels ?? 2,
          });
        }
      } catch (error) {
        displayError(error);
      } finally {
        if (epoch === treeEpoch) {
          set({ resettingLayout: false });
        }
      }
    },

    acknowledgeNewItems: async () => {
      const { layoutId } = get();
      const epoch = nextTreeEpoch();

      try {
        const res = await acknowledgeNewItemsApi(layoutId);
        applyServerTree(res.tree?.nodes, res.tree?.meta, epoch, layoutId);
      } catch (error) {
        displayError(error);
      }
    },
  };
});
