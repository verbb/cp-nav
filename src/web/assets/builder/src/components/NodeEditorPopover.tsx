import {
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
  type CSSProperties,
  type FormEvent,
} from 'react';
import { Button, Field, Input, Lightswitch, Popover } from '@verbb/plugin-kit-react/components';
import { useBuilderStore } from '../store';
import { t } from '../api';
import type { CustomIconAsset } from '../types';
import { type PkOpenChangeEvent } from '../utils/pluginKitEvents';
import {
  emptyCreateForm,
  formFromNode,
  hasFieldErrors,
  resolveEditorAnchor,
  validateNodeEditorForm,
  type EditorSession,
  type NodeEditorFieldErrors,
  type NodeEditorFormState,
} from '../utils/nodeEditor';
import { CustomIconInput } from './CustomIconInput';

/** ~375px — between kit flush default (22.5rem) and a full CP field column. */
const POPOVER_WIDTH = '375px';
const POPOVER_FLUSH_MAX = '375px';

/**
 * pk-input Enter dispatches this on the nearest `<form>` instead of requestSubmit()
 * (avoids Craft `#main`). Same bridge Formie / SchemaFormEngine listen for.
 */
const PK_IMPLICIT_SUBMIT_EVENT = 'pk-implicit-submit';

type FormState = NodeEditorFormState & {
  customIconAsset: CustomIconAsset | null;
};

type PkInputHost = HTMLElement & { value: string };

/** Nested `pk-popup` — `positionMethod: 'fixed'` skips the native top layer (modal handoff). */
type PkPopupHost = HTMLElement & {
  positionMethod?: 'fixed' | 'absolute';
};

function forcePopoverFixedPosition(popoverHost: HTMLElement | null): void {
  const popup = popoverHost?.shadowRoot?.querySelector('pk-popup') as PkPopupHost | null;

  if (popup && popup.positionMethod !== 'fixed') {
    popup.positionMethod = 'fixed';
  }
}

/**
 * Live value from a named pk-input. Do not trust React controlled `value` here —
 * kit maps the HTML `value` attribute to `defaultValue`, so controlled binding
 * desyncs (field shows text, React state stays blank → false “cannot be blank”).
 */
function readNamedPkInput(formElement: HTMLFormElement | null, name: string): string | null {
  const input = formElement?.querySelector(`pk-input[name="${name}"]`) as PkInputHost | null;

  if (!input || typeof input.value !== 'string') {
    return null;
  }

  return input.value;
}

/** Formie-style: kit `onChange` listens to the CE `input` event; read host `.value`. */
function readPkChangeValue(event: Event): string {
  return String((event.target as { value?: unknown } | null)?.value ?? '');
}

/**
 * Craft-HUD-style create/edit popover — draft form only; Save commits, Cancel discards.
 * Anchors to New menu item (create) or the row label (edit) via `anchor` across shadow roots.
 *
 * Keep the popover mounted with `open={false}` until `pk-after-hide` so the kit exit
 * animation can run (clearing `editorSession` immediately used to unmount mid-close).
 */
export function NodeEditorPopover() {
  const session = useBuilderStore((s) => s.editorSession);
  const nodes = useBuilderStore((s) => s.nodes);
  const assetSources = useBuilderStore((s) => s.assetSources);
  const closeEditor = useBuilderStore((s) => s.closeEditor);
  const createNode = useBuilderStore((s) => s.createNode);
  const updateNode = useBuilderStore((s) => s.updateNode);

  const [visibleSession, setVisibleSession] = useState<EditorSession | null>(null);
  const [open, setOpen] = useState(false);
  const [anchor, setAnchor] = useState<Element | null>(null);
  // Remount inputs when a session opens so defaultValue applies once (uncontrolled).
  const [fieldMountKey, setFieldMountKey] = useState(0);
  const [fields, setFields] = useState<FormState>({
    ...emptyCreateForm('manual'),
    customIconAsset: null,
  });
  const [errors, setErrors] = useState<NodeEditorFieldErrors>({});
  const [saving, setSaving] = useState(false);
  // Craft asset modal lives outside the popover — light-dismiss would close us on every click.
  const [craftOverlayOpen, setCraftOverlayOpen] = useState(false);
  const formRef = useRef<HTMLFormElement | null>(null);
  const popoverRef = useRef<HTMLElement | null>(null);
  const fieldsRef = useRef(fields);
  const saveRef = useRef<() => Promise<void>>(async () => {});
  const craftOverlayOpenRef = useRef(false);

  fieldsRef.current = fields;
  craftOverlayOpenRef.current = craftOverlayOpen;

  // Drive open/close from the store session; hold visibleSession through the exit motion.
  // Keep open=false here — layout effect applies positionMethod=fixed first, then opens.
  // Otherwise pk-popup lands in the native top layer and covers Craft’s asset modal.
  useEffect(() => {
    if (!session) {
      setOpen(false);
      return;
    }

    setVisibleSession(session);
    setOpen(false);
    setAnchor(resolveEditorAnchor(session));
    setErrors({});
    setSaving(false);

    if (session.kind === 'create') {
      setFields({ ...emptyCreateForm(session.type), customIconAsset: null });
      setFieldMountKey((key) => key + 1);
      return;
    }

    const node = useBuilderStore.getState().nodes.find((candidate) => candidate.key === session.nodeKey);

    if (node) {
      setFields({
        ...formFromNode(node),
        customIconAsset: node.customIconAsset,
      });
      setFieldMountKey((key) => key + 1);
    }
  }, [
    session?.kind,
    session && 'type' in session ? session.type : null,
    session && 'nodeKey' in session ? session.nodeKey : null,
  ]);

  // Re-resolve anchor after layout paints (actions host / row may mount a tick later).
  useEffect(() => {
    if (!session) {
      return;
    }

    const frame = window.requestAnimationFrame(() => {
      setAnchor(resolveEditorAnchor(session));
    });

    return () => window.cancelAnimationFrame(frame);
  }, [session]);

  // Exit top layer before show — kit documents fixed coords for Craft modal handoff.
  // Wait until nested pk-popup exists (Lit shadow) so we never open into top layer first.
  useLayoutEffect(() => {
    if (!session || !visibleSession || !anchor) {
      return;
    }

    let cancelled = false;

    const tryOpen = () => {
      if (cancelled) {
        return;
      }

      const host = popoverRef.current;
      const popup = host?.shadowRoot?.querySelector('pk-popup') as PkPopupHost | null;

      if (!popup) {
        requestAnimationFrame(tryOpen);
        return;
      }

      forcePopoverFixedPosition(host);
      setOpen(true);
    };

    tryOpen();

    return () => {
      cancelled = true;
    };
  }, [session, visibleSession, anchor, fieldMountKey]);

  const editNode =
    visibleSession?.kind === 'edit'
      ? (nodes.find((node) => node.key === visibleSession.nodeKey) ?? null)
      : null;

  // Edit session for a node that disappeared — close quietly.
  useEffect(() => {
    if (session?.kind === 'edit' && !nodes.some((node) => node.key === session.nodeKey)) {
      closeEditor();
    }
  }, [session, nodes, closeEditor]);

  // pk-input Enter → `pk-implicit-submit` on this form (not Craft `#main`).
  // Must stay above early returns — otherwise opening the popover changes hook count (#310).
  // Depend on anchor/visibleSession too: the <form> only mounts once both exist.
  useEffect(() => {
    const formElement = formRef.current;

    if (!formElement || !open || !anchor || !visibleSession) {
      return;
    }

    const onImplicitSubmit = (event: Event) => {
      event.preventDefault();
      event.stopPropagation();
      void saveRef.current();
    };

    formElement.addEventListener(PK_IMPLICIT_SUBMIT_EVENT, onImplicitSubmit);

    return () => {
      formElement.removeEventListener(PK_IMPLICIT_SUBMIT_EVENT, onImplicitSubmit);
    };
  }, [open, anchor, visibleSession]);

  if (!visibleSession) {
    return null;
  }

  // Wait for the anchor before mounting an open popover. Opening with anchor=null
  // skips pk-popover’s light-dismiss registration (click-away never arms).
  if (!anchor) {
    return null;
  }

  const editorType =
    visibleSession.kind === 'create' ? visibleSession.type : (editNode?.type ?? 'manual');
  const isDivider = editorType === 'divider';
  const isManual = editorType === 'manual';
  const showIconFields = !isDivider && visibleSession.kind === 'edit';
  const showUrlFields = isManual;

  const handleOpenChange = (event: Event) => {
    const nextOpen = (event as PkOpenChangeEvent).detail?.open;

    // Ignore dismiss while Craft’s asset picker is open (clicks land “outside” the popover).
    if (nextOpen === false && !saving && !craftOverlayOpenRef.current) {
      closeEditor();
    }
  };

  /** Cancelable `pk-hide` — abort light-dismiss / Esc while the asset modal is up. */
  const handleHide = (event: Event) => {
    if (craftOverlayOpenRef.current) {
      event.preventDefault();
    }
  };

  const handleAfterHide = () => {
    setVisibleSession(null);
    setAnchor(null);
    setErrors({});
    setSaving(false);
    setCraftOverlayOpen(false);
  };

  /** Prefer live CE values at submit time — React state can lag for pk-input. */
  const readSubmitFields = (): FormState => {
    const current = fieldsRef.current;
    const currLabel = readNamedPkInput(formRef.current, 'currLabel') ?? current.currLabel;
    const url = readNamedPkInput(formRef.current, 'url') ?? current.url;
    const next = { ...current, currLabel, url };
    fieldsRef.current = next;
    setFields(next);
    return next;
  };

  const save = async () => {
    if (saving) {
      return;
    }

    const submitFields = readSubmitFields();
    const nextErrors = validateNodeEditorForm(submitFields, {
      type: editorType,
      isCreate: visibleSession.kind === 'create',
    });
    setErrors(nextErrors);

    if (hasFieldErrors(nextErrors)) {
      return;
    }

    setSaving(true);

    try {
      let ok = false;

      if (visibleSession.kind === 'create') {
        ok = await createNode({
          type: visibleSession.type,
          currLabel:
            submitFields.currLabel.trim() ||
            (visibleSession.type === 'divider' ? t('Divider') : submitFields.currLabel),
          ...(visibleSession.type === 'manual'
            ? { url: submitFields.url.trim(), newWindow: submitFields.newWindow }
            : {}),
        });
      } else if (editNode) {
        ok = await updateNode(editNode.key, {
          currLabel: submitFields.currLabel.trim(),
          ...(isManual
            ? { url: submitFields.url.trim(), newWindow: submitFields.newWindow }
            : {}),
          // Craft/system `icon` stays registry/overlay-owned — only custom SVG is editable.
          ...(showIconFields
            ? { customIcon: submitFields.customIconAsset?.id ?? null }
            : {}),
        });
      }

      if (ok) {
        closeEditor();
      }
    } finally {
      setSaving(false);
    }
  };

  saveRef.current = save;

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    event.stopPropagation();
    void save();
  };

  return (
    // Keep the host out of the builder’s block flow — pk-popover is inline-block, and
    // mounting it after the tree was expanding the pane by ~panel height while open.
    //
    // After leaving the native top layer (positionMethod=fixed), stay under Craft
    // modals/shades (z-index 100). pk-popup defaults to --pk-popup-z-index: 1000.
    <div className="pointer-events-none fixed top-0 left-0 z-[90] h-0 w-0 overflow-visible">
      <Popover
        ref={popoverRef as never}
        open={open}
        anchor={anchor}
        // Center under the trigger (screen1) rather than hard end-align.
        placement="bottom"
        withArrow
        flush
        onPkOpenChange={handleOpenChange}
        onPkHide={handleHide}
        onPkAfterHide={handleAfterHide}
        className="pointer-events-auto"
        style={
          {
            // Kit flush caps at 22.5rem unless overridden.
            '--pk-popover-flush-max-width': POPOVER_FLUSH_MAX,
            '--pk-popup-z-index': '90',
          } as CSSProperties
        }
      >
        {/*
          flush drops kit panel padding/width so we own inset. Match the panel’s
          radius + overflow clip so the footer strip doesn’t square-bleed the corners
          (pk-popover’s .panel rounds but doesn’t overflow:hidden — arrow lives outside).

          Native <form> so Enter submits the traditional way: light-DOM via hidden
          submitter, pk-input via pk-implicit-submit → same save path.
        */}
        <form
          ref={formRef}
          className="flex max-w-[calc(100vw-2rem)] min-w-0 flex-col overflow-hidden rounded-[var(--pk-radius-md)]"
          style={{ width: POPOVER_WIDTH }}
          onSubmit={handleSubmit}
        >
          <div className="flex min-w-0 flex-col gap-4 p-4 [&_pk-field]:min-w-0 [&_pk-input]:w-full">
            <Field
              label={t('Label')}
              instructions={
                isDivider
                  ? t('Optional section label shown on the divider.')
                  : t('Choose what you want this menu item to be called')
              }
              required={!isDivider}
              errors={errors.currLabel ? [t(errors.currLabel)] : []}
            >
              {/*
                Uncontrolled + defaultValue: pk-input’s HTML `value` attr is defaultValue.
                Controlled `value={state}` never tracked typing and Save saw blanks.
              */}
              <Input
                key={`currLabel-${fieldMountKey}`}
                name="currLabel"
                defaultValue={fields.currLabel}
                onChange={(event: Event) => {
                  const currLabel = readPkChangeValue(event);
                  setFields((prev) => ({ ...prev, currLabel }));
                  fieldsRef.current = { ...fieldsRef.current, currLabel };
                  if (errors.currLabel) {
                    setErrors((prev) => ({ ...prev, currLabel: undefined }));
                  }
                }}
              />
            </Field>

            {showUrlFields && (
              <Field
                label={t('URL')}
                instructions={t('Choose the URL this menu points to.')}
                required
                errors={errors.url ? [t(errors.url)] : []}
              >
                <Input
                  key={`url-${fieldMountKey}`}
                  name="url"
                  defaultValue={fields.url}
                  onChange={(event: Event) => {
                    const url = readPkChangeValue(event);
                    setFields((prev) => ({ ...prev, url }));
                    fieldsRef.current = { ...fieldsRef.current, url };
                    if (errors.url) {
                      setErrors((prev) => ({ ...prev, url: undefined }));
                    }
                  }}
                />
              </Field>
            )}

            {showUrlFields && (
              <Field
                label={t('New window')}
                instructions={t('Whether to open this page in a new window.')}
              >
                <Lightswitch
                  checked={fields.newWindow}
                  onCheckedChange={(checked) => {
                    setFields((prev) => ({ ...prev, newWindow: checked }));
                    fieldsRef.current = { ...fieldsRef.current, newWindow: checked };
                  }}
                />
              </Field>
            )}

            {showIconFields && (
              <Field
                label={t('Custom Icon')}
                instructions={t('Specify an SVG asset for this menu item icon.')}
              >
                  <CustomIconInput
                    asset={fields.customIconAsset}
                    sources={assetSources}
                    onChange={(customIconAsset) => {
                      setFields((prev) => ({ ...prev, customIconAsset }));
                      fieldsRef.current = { ...fieldsRef.current, customIconAsset };
                    }}
                    onModalOpen={() => {
                      // Sync ref immediately — setState alone lags one frame, and the
                      // first modal click would still light-dismiss the editor.
                      craftOverlayOpenRef.current = true;
                      setCraftOverlayOpen(true);
                    }}
                    onModalClose={() => {
                      craftOverlayOpenRef.current = false;
                      setCraftOverlayOpen(false);
                    }}
                  />
              </Field>
            )}
          </div>

          <div className="flex shrink-0 items-center justify-end gap-2 border-t border-gray-150 bg-gray-50 px-4 py-3">
            <Button type="button" variant="default" disabled={saving} onClick={() => closeEditor()}>
              {t('Cancel')}
            </Button>
            <Button type="submit" variant="primary" loading={saving} disabled={saving}>
              {t('Save')}
            </Button>
          </div>

          {/* Default submitter for light-DOM text inputs; pk-input uses pk-implicit-submit. */}
          <button type="submit" tabIndex={-1} aria-hidden="true" className="sr-only" />
        </form>
      </Popover>
    </div>
  );
}
