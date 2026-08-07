import { Button } from '@verbb/plugin-kit-react/components';
import { useBuilderStore } from '../store';
import { t } from '../api';

export function NewItemsBanner() {
  const newItemCount = useBuilderStore((s) => s.newItemCount);
  const acknowledgeNewItems = useBuilderStore((s) => s.acknowledgeNewItems);

  if (newItemCount <= 0) {
    return null;
  }

  return (
    <div className="m-3 flex items-center gap-3 rounded-md border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm text-sky-900">
      <div className="flex-1">
        {newItemCount === 1
          ? t('1 new menu item was added at Craft’s default position.')
          : t('{count} new menu items were added at Craft’s default positions.', { count: newItemCount })}
      </div>
      <Button type="button" variant="default" onClick={() => void acknowledgeNewItems()}>
        {t('Got it')}
      </Button>
    </div>
  );
}
