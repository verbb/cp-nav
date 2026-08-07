import {
  Button,
  ButtonGroup,
  ButtonGroupSeparator,
  DropdownItem,
  DropdownMenu,
  Icon,
} from '@verbb/plugin-kit-react/components';
import { useBuilderStore } from '../store';
import { t } from '../api';

type PkSelectDetail = { value?: string };

/** Header actions: reset current layout + split “New menu item” (opens create HUD). */
export function BuilderActions() {
  const loading = useBuilderStore((s) => s.loading);
  const error = useBuilderStore((s) => s.error);
  const resettingLayout = useBuilderStore((s) => s.resettingLayout);
  const reordering = useBuilderStore((s) => s.reordering);
  const resetLayout = useBuilderStore((s) => s.resetLayout);
  const openCreateEditor = useBuilderStore((s) => s.openCreateEditor);

  if (loading || error) {
    return null;
  }

  const busy = reordering || resettingLayout;

  const handleMenuSelect = (event: Event) => {
    const value = (event as CustomEvent<PkSelectDetail>).detail?.value;

    if (value === 'divider') {
      openCreateEditor('divider');
    }
  };

  const handleReset = () => {
    if (!confirm(t('Are you sure you want to reset the navigation? This cannot be undone.'))) {
      return;
    }

    void resetLayout();
  };

  return (
    <div className="flex items-center gap-2">
      <Button
        type="button"
        variant="default"
        loading={resettingLayout}
        disabled={busy && !resettingLayout}
        spinnerVariant="outline"
        onClick={(event) => {
          event.currentTarget.blur();
          handleReset();
        }}
      >
        {t('Reset navigation')}
      </Button>

      <ButtonGroup>
        <Button
          type="button"
          variant="primary"
          disabled={busy}
          data-cpnav-editor-anchor="new-menu"
          onClick={() => openCreateEditor('manual')}
        >
          <Icon slot="start" icon="plus" className="size-3.5" />
          {t('New menu item')}
        </Button>
        <ButtonGroupSeparator />
        <DropdownMenu placement="bottom-end" onPkSelect={handleMenuSelect}>
          <Button
            slot="trigger"
            type="button"
            variant="primary"
            groupTrigger
            disabled={busy}
            aria-label={t('More options')}
            onClick={(event) => event.currentTarget.blur()}
          />
          <DropdownItem value="divider">{t('New divider item')}</DropdownItem>
        </DropdownMenu>
      </ButtonGroup>
    </div>
  );
}
