import { useState } from 'react';
import { Button, DropdownItem, DropdownMenu } from '@verbb/plugin-kit-react/components';
import { useBuilderStore } from '../store';
import { getCraft } from '../utils/cp';
import { type PkOpenChangeEvent } from '../utils/pluginKitEvents';

type Props = {
  showToolbar?: boolean;
};

type PkSelectDetail = { value?: string };

/** Layout switcher only — status filtering was removed for a simpler header. */
export function BuilderHeader({ showToolbar = true }: Props) {
  const layoutId = useBuilderStore((s) => s.layoutId);
  const layouts = useBuilderStore((s) => s.layouts);
  const [layoutMenuOpen, setLayoutMenuOpen] = useState(false);

  if (!showToolbar || layouts.length <= 1) {
    return null;
  }

  const activeLayout = layouts.find((layout) => layout.id === layoutId) ?? layouts[0];

  if (!activeLayout) {
    return null;
  }

  const handleLayoutSelect = (event: Event) => {
    const value = (event as CustomEvent<PkSelectDetail>).detail?.value;
    const nextId = value ? Number(value) : NaN;

    if (!Number.isFinite(nextId) || nextId === layoutId) {
      setLayoutMenuOpen(false);
      return;
    }

    const craft = getCraft();
    window.location.href = craft.getUrl(`cp-nav?layoutId=${nextId}`);
  };

  return (
    <div id="cpnav-builder-toolbar" className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
      <DropdownMenu
        open={layoutMenuOpen}
        placement="bottom-start"
        onPkSelect={handleLayoutSelect}
        onPkOpenChange={(event) => setLayoutMenuOpen((event as PkOpenChangeEvent).detail.open)}
      >
        <Button slot="trigger" type="button" variant="default" withCaret>
          <span>{activeLayout.name}</span>
        </Button>

        {layouts.map((layout) => (
          <DropdownItem
            key={layout.id}
            value={String(layout.id)}
            type="radio"
            radioGroup="cpnav-layout-filter"
            checked={layout.id === layoutId}
          >
            {layout.name}
          </DropdownItem>
        ))}
      </DropdownMenu>
    </div>
  );
}
