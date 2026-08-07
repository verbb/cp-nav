import type { BuilderNode } from '../types';

/** Intentional create/edit session — nothing persists until Save. */
export type EditorSession =
  | { kind: 'create'; type: 'manual' | 'divider' }
  | { kind: 'edit'; nodeKey: string };

export type NodeEditorFormState = {
  currLabel: string;
  url: string;
  newWindow: boolean;
};

export type NodeEditorFieldErrors = {
  currLabel?: string;
  url?: string;
};

export function emptyCreateForm(_type: 'manual' | 'divider' = 'manual'): NodeEditorFormState {
  return {
    currLabel: '',
    url: '',
    newWindow: false,
  };
}

export function formFromNode(node: BuilderNode): NodeEditorFormState {
  return {
    currLabel: node.label ?? '',
    url: node.url ?? '',
    newWindow: node.newWindow,
  };
}

/**
 * Client-side required checks before create/update.
 * Dividers: label optional. Manual: label + URL. Registry nodes: label required.
 */
export function validateNodeEditorForm(
  form: NodeEditorFormState,
  options: { type: BuilderNode['type'] | 'manual' | 'divider'; isCreate: boolean },
): NodeEditorFieldErrors {
  const errors: NodeEditorFieldErrors = {};
  const isDivider = options.type === 'divider';
  const isManual = options.type === 'manual';

  if (!isDivider && form.currLabel.trim() === '') {
    errors.currLabel = 'Label cannot be blank.';
  }

  if (isManual && form.url.trim() === '') {
    errors.url = 'URL cannot be blank.';
  }

  return errors;
}

export function hasFieldErrors(errors: NodeEditorFieldErrors): boolean {
  return Boolean(errors.currLabel || errors.url);
}

/** Resolve the HUD anchor across Craft chrome / builder shadow roots. */
export function resolveEditorAnchor(session: EditorSession): Element | null {
  if (session.kind === 'create') {
    return (
      document
        .querySelector('#cpnav-builder-actions-root')
        ?.shadowRoot?.querySelector('[data-cpnav-editor-anchor="new-menu"]') ?? null
    );
  }

  const key = CSS.escape(session.nodeKey);

  return (
    document
      .querySelector('#cpnav-builder-app')
      ?.shadowRoot?.querySelector(`[data-cpnav-editor-anchor="${key}"]`) ?? null
  );
}
