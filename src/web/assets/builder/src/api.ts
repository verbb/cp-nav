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

// A layout's writes must reach the server in intent order. Ignoring stale responses
// alone cannot stop an older request from persisting after a newer one.
const pendingMutations = new Map<number, Promise<void>>();
const failedMutations = new Map<number, number>();
const pendingListeners = new Set<() => void>();

export function hasPendingMutations(layoutId: number): boolean {
  return pendingMutations.has(layoutId);
}

export function onPendingMutationsChange(listener: () => void): () => void {
  pendingListeners.add(listener);
  return () => { pendingListeners.delete(listener); };
}

function sendMutation(action: string, data: Record<string, unknown> & { layoutId: number }) {
  const previous = pendingMutations.get(data.layoutId);
  const send = () => getCraft().sendActionRequest('POST', action, { data });
  const request = previous ? previous.then(send) : send();
  // A failed write must not poison the queue for subsequent edits.
  const settled = request.then(() => {}, () => {
    failedMutations.set(data.layoutId, (failedMutations.get(data.layoutId) ?? 0) + 1);
  });
  pendingMutations.set(data.layoutId, settled);
  pendingListeners.forEach((listener) => listener());
  void settled.then(() => {
    if (pendingMutations.get(data.layoutId) === settled) {
      pendingMutations.delete(data.layoutId);
      pendingListeners.forEach((listener) => listener());
    }
  });
  return request;
}

/** Drain writes before leaving this page, including edits queued while waiting. */
export async function navigateToLayout(layoutId: number, nextId: number): Promise<boolean> {
  return navigateAfterSaving(layoutId, getCraft().getUrl(`cp-nav?layoutId=${nextId}`));
}

export async function navigateAfterSaving(layoutId: number, url: string): Promise<boolean> {
  const failures = failedMutations.get(layoutId) ?? 0;
  let pending;
  while ((pending = pendingMutations.get(layoutId))) {
    await pending;
  }
  if ((failedMutations.get(layoutId) ?? 0) !== failures) {
    getCraft().cp.displayError(t('Couldn’t leave this page because a navigation change failed to save.'));
    return false;
  }
  window.location.href = url;
  return true;
}

export async function fetchLayoutTree(layoutId: number): Promise<LayoutTreeResponse> {
  // Recovery/reload must observe writes already queued by this builder.
  await pendingMutations.get(layoutId);
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
  const response = await sendMutation('cp-nav/api/update-node', { layoutId, key, data });

  return response.data as MutationResponse;
}

export async function createNode(layoutId: number, data: CreateNodeData): Promise<MutationResponse> {
  const response = await sendMutation('cp-nav/api/create-node', { layoutId, data });

  return response.data as MutationResponse;
}

export async function deleteNode(layoutId: number, key: string): Promise<MutationResponse> {
  const response = await sendMutation('cp-nav/api/delete-node', { layoutId, key });

  return response.data as MutationResponse;
}

export async function reorderNodes(layoutId: number, items: ReorderItem[]): Promise<MutationResponse> {
  const response = await sendMutation('cp-nav/api/reorder-nodes', { layoutId, items });

  return response.data as MutationResponse;
}

/** Nest under the previous root sibling. Fails if `canIndent` is false. */
export async function indentNode(layoutId: number, key: string): Promise<MutationResponse> {
  const response = await sendMutation('cp-nav/api/indent-node', { layoutId, key });

  return response.data as MutationResponse;
}

/** Promote a nested node to top level after its former parent block. */
export async function outdentNode(layoutId: number, key: string): Promise<MutationResponse> {
  const response = await sendMutation('cp-nav/api/outdent-node', { layoutId, key });

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
  const response = await sendMutation('cp-nav/api/reparent-node', { layoutId, key, parentKey });

  return response.data as MutationResponse;
}

export async function acknowledgeNewItems(layoutId: number): Promise<MutationResponse> {
  const response = await sendMutation('cp-nav/api/acknowledge-new-items', { layoutId });

  return response.data as MutationResponse;
}

export async function resetLayout(layoutId: number): Promise<MutationResponse> {
  const response = await sendMutation('cp-nav/api/reset-layout', { layoutId });

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
