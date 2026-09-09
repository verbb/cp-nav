import type { LayoutTreeResponse, ReorderItem } from './types';
import { getCraft } from './utils/cp';

type UpdateNodeData = {
  enabled?: boolean;
  currLabel?: string;
  url?: string;
  newWindow?: boolean;
  icon?: string | null;
  /** Relative SVG path under iconsPath, or null to clear. */
  customIcon?: string | null;
};

type CreateNodeData = {
  type: 'manual' | 'divider';
  currLabel?: string;
  url?: string;
  newWindow?: boolean;
};

type MutationResponse = {
  tree?: LayoutTreeResponse;
  /** Present on create-node — used to select the new row in the sidebar. */
  node?: { key?: string };
  message?: string;
};

export async function fetchLayoutTree(layoutId: number): Promise<LayoutTreeResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/layout-tree', {
    data: { layoutId },
  });

  return response.data as unknown as LayoutTreeResponse;
}

export async function updateNode(
  layoutId: number,
  key: string,
  data: UpdateNodeData,
): Promise<MutationResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/update-node', {
    data: { layoutId, key, data },
  });

  return response.data as MutationResponse;
}

export async function createNode(layoutId: number, data: CreateNodeData): Promise<MutationResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/create-node', {
    data: { layoutId, data },
  });

  return response.data as MutationResponse;
}

export async function deleteNode(layoutId: number, key: string): Promise<MutationResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/delete-node', {
    data: { layoutId, key },
  });

  return response.data as MutationResponse;
}

export async function reorderNodes(layoutId: number, items: ReorderItem[]): Promise<MutationResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/reorder-nodes', {
    data: { layoutId, items },
  });

  return response.data as MutationResponse;
}

/** Nest under the previous root sibling. Fails if `canIndent` is false. */
export async function indentNode(layoutId: number, key: string): Promise<MutationResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/indent-node', {
    data: { layoutId, key },
  });

  return response.data as MutationResponse;
}

/** Promote a nested node to top level after its former parent block. */
export async function outdentNode(layoutId: number, key: string): Promise<MutationResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/outdent-node', {
    data: { layoutId, key },
  });

  return response.data as MutationResponse;
}

/**
 * Set an explicit parent. Pass `null` / omit to move to root.
 * Rejects depth > 2 and moving a parent that still has children.
 */
export async function reparentNode(
  layoutId: number,
  key: string,
  parentKey: string | null = null,
): Promise<MutationResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/reparent-node', {
    data: { layoutId, key, parentKey },
  });

  return response.data as MutationResponse;
}

export async function acknowledgeNewItems(layoutId: number): Promise<MutationResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/acknowledge-new-items', {
    data: { layoutId },
  });

  return response.data as MutationResponse;
}

export async function resetLayout(layoutId: number): Promise<MutationResponse> {
  const response = await getCraft().sendActionRequest('POST', 'cp-nav/api/reset-layout', {
    data: { layoutId },
  });

  return response.data as MutationResponse;
}

export function displayError(error: unknown): void {
  const response = (error as { response?: { data?: { message?: string } } })?.response;

  if (response?.data?.message) {
    getCraft().cp.displayError(response.data.message);
  } else {
    getCraft().cp.displayError();
  }
}

export function t(message: string, params?: Record<string, unknown>): string {
  return getCraft().t('cp-nav', message, params);
}
