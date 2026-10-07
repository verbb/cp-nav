import { useEffect } from 'react';
import { ErrorState } from '@verbb/plugin-kit-react/utils';
import { useBuilderStore } from '../store';
import { t } from '../api';
import { NodeTree } from './NodeTree';
import { NewItemsBanner } from './NewItemsBanner';
import { NodeEditorPopover } from './NodeEditorPopover';
import type { LayoutOption } from '../types';

type Props = {
  layoutId: number;
  layouts: LayoutOption[];
};

export function BuilderApp({ layoutId, layouts }: Props) {
  const init = useBuilderStore((s) => s.init);
  const loading = useBuilderStore((s) => s.loading);
  const error = useBuilderStore((s) => s.error);

  useEffect(() => {
    void init(layoutId, layouts);
  }, [init, layoutId, layouts]);

  if (loading) {
    return null;
  }

  if (error) {
    return (
      <ErrorState
        error={error}
        heading={t('Couldn’t load navigation.')}
        message={t('The navigation builder could not be loaded. Retry the request or refresh the page.')}
        detailsLabel={t('Show error details')}
        copyLabel={t('Copy error details')}
        copiedLabel={t('Error details copied.')}
        copyErrorLabel={t('Copy failed. Select the details and copy them manually.')}
        actionLabel={t('Retry')}
        onAction={() => void init(layoutId, layouts)}
        className="[--pk-state-panel-min-height:20rem]"
      />
    );
  }

  // Sit inside Craft’s content-pane (no second card) so page tabs join the white surface.
  return (
    <>
      <NewItemsBanner />
      <NodeTree />
      <NodeEditorPopover />
    </>
  );
}
