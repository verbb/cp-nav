import { useEffect, useState } from 'react';
import {
  ImageBrowser,
  type PkImageBrowserItem,
} from '@verbb/plugin-kit-react/components';
import type { CustomIconPreview } from '../types';
import { getCraft } from '../utils/cp';
import { t } from '../api';

type CatalogOption = {
  value: string;
  label: string;
  url?: string | null;
};

type CustomIconInputProps = {
  /** Relative path under the plugin icons folder, or null. */
  value: string | null;
  preview: CustomIconPreview | null;
  onChange: (path: string | null, preview: CustomIconPreview | null) => void;
  /** So the parent editor can suppress light-dismiss while the browser panel is open. */
  onOpenChange?: (open: boolean) => void;
};

/**
 * Static SVG path picker via Plugin Kit ImageBrowser — portable project-config
 * values (not Craft assets). Catalog comes from `cp-nav/static-icons`.
 */
export function CustomIconInput({ value, preview, onChange, onOpenChange }: CustomIconInputProps) {
  const [items, setItems] = useState<PkImageBrowserItem[]>([]);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const response = await getCraft().sendActionRequest('GET', 'cp-nav/static-icons');
        const options = (response.data as { options?: CatalogOption[] })?.options ?? [];

        if (cancelled) {
          return;
        }

        setItems(
          options.map((option) => ({
            value: option.value,
            // Docs: when scanning a folder, pass the path as both value and label.
            label: option.label || option.value,
            preview: option.url ?? undefined,
          })),
        );
      } catch {
        if (!cancelled) {
          setItems([]);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <ImageBrowser
      value={value ?? ''}
      mode="icon"
      width="full"
      withClear
      items={items}
      selectedLabel={value ?? ''}
      selectedPreview={preview?.url ?? ''}
      placeholder={t('Select an SVG…')}
      emptyMessage={t('No SVG files found in the icons folder.')}
      searchPlaceholder={t('Search icons…')}
      aria-label={t('Custom Icon')}
      onChange={(next) => {
        const path = next.trim();

        if (!path) {
          onChange(null, null);
          return;
        }

        const match = items.find((item) => item.value === path);
        const previewUrl =
          typeof match?.preview === 'string' && !match.preview.trim().startsWith('<')
            ? match.preview
            : preview?.path === path
              ? preview.url
              : null;

        onChange(path, {
          path,
          url: previewUrl,
          label: match?.label ?? path,
        });
      }}
      onPkClear={() => onChange(null, null)}
      onPkShow={() => onOpenChange?.(true)}
      onPkHide={() => onOpenChange?.(false)}
      onPkAfterHide={() => onOpenChange?.(false)}
    />
  );
}
