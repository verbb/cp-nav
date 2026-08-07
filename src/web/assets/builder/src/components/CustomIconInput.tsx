import { Button, Icon } from '@verbb/plugin-kit-react/components';
import type { CustomIconAsset } from '../types';
import { getCraft } from '../utils/cp';
import { t } from '../api';

type CustomIconInputProps = {
  asset: CustomIconAsset | null;
  sources: string[];
  onChange: (asset: CustomIconAsset | null) => void;
  /** So the parent editor can suppress light-dismiss while Craft’s modal is up. */
  onModalOpen?: () => void;
  onModalClose?: () => void;
};

/**
 * Craft AssetSelectInput stand-in for the edit pane — opens Craft's element selector modal
 * (lives outside the shadow root) and keeps a compact chip for the chosen SVG.
 */
export function CustomIconInput({
  asset,
  sources,
  onChange,
  onModalOpen,
  onModalClose,
}: CustomIconInputProps) {
  const openModal = () => {
    onModalOpen?.();

    getCraft().createElementSelectorModal('craft\\elements\\Asset', {
      multiSelect: false,
      sources: sources.length > 0 ? sources : undefined,
      criteria: { kind: ['image'] },
      storageKey: 'cpnav.customIcon',
      onSelect: (elements) => {
        const selected = elements[0];

        if (!selected) {
          return;
        }

        const previewUrl = selected.url ?? null;

        onChange({
          id: selected.id,
          title: selected.label ?? selected.title ?? `Asset #${selected.id}`,
          url: previewUrl,
          thumbUrl: previewUrl,
        });
      },
      // Any dismiss path (Cancel, shade, Esc, post-Select hide) — not only onCancel.
      onHide: () => {
        onModalClose?.();
      },
    });
  };

  if (!asset) {
    return (
      <Button type="button" variant="dashed" size="sm" onClick={openModal}>
        <Icon slot="start" icon="plus" className="size-3.5" />
        {t('Choose')}
      </Button>
    );
  }

  const previewSrc = asset.thumbUrl ?? asset.url;

  // Chip mirrors Craft’s elementselect chip: thumb + label + quiet dismiss (not a filled btn).
  return (
    <div className="flex min-w-0 items-center gap-1 rounded-md border border-gray-200 bg-gray-50 py-1 pr-1 pl-1.5">
      <button
        type="button"
        className="flex min-w-0 flex-1 items-center gap-2 text-left"
        onClick={openModal}
      >
        <span className="flex size-7 shrink-0 items-center justify-center overflow-hidden rounded bg-white ring-1 ring-gray-150">
          {previewSrc ? (
            <img src={previewSrc} alt="" className="max-h-full max-w-full object-contain" />
          ) : (
            <span className="text-[10px] text-gray-400">SVG</span>
          )}
        </span>
        <span className="truncate text-sm text-gray-700">{asset.title}</span>
      </button>
      <button
        type="button"
        className="flex size-7 shrink-0 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-150 hover:text-gray-700"
        aria-label={t('Remove')}
        onClick={() => onChange(null)}
      >
        <Icon icon="xmark" className="size-3.5" />
      </button>
    </div>
  );
}
