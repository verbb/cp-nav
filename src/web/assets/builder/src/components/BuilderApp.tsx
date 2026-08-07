import { useEffect } from 'react';
import { useBuilderStore } from '../store';
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
    return <div className="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>;
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
