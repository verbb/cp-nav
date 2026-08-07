import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { BuilderApp } from './BuilderApp';
import { BuilderHeader } from './BuilderHeader';
import { BuilderActions } from './BuilderActions';
import { useBuilderStore } from '../store';
import type { LayoutOption } from '../types';

const BUILDER_READY_CLASS = 'cpnav-builder-ready';

function BuilderReadyMarker() {
  const loading = useBuilderStore((s) => s.loading);

  useEffect(() => {
    if (loading) {
      document.body.classList.remove(BUILDER_READY_CLASS);
      return;
    }

    document.body.classList.add(BUILDER_READY_CLASS);

    return () => {
      document.body.classList.remove(BUILDER_READY_CLASS);
    };
  }, [loading]);

  return null;
}

type Props = {
  layoutId: number;
  layouts: LayoutOption[];
  headerMountNode: HTMLElement | null;
  actionsMountNode: HTMLElement | null;
};

function HeaderMount() {
  const loading = useBuilderStore((s) => s.loading);
  const error = useBuilderStore((s) => s.error);

  return <BuilderHeader showToolbar={!loading && !error} />;
}

export function BuilderShell({
  layoutId,
  layouts,
  headerMountNode,
  actionsMountNode,
}: Props) {
  return (
    <>
      <BuilderReadyMarker />
      {headerMountNode && createPortal(<HeaderMount />, headerMountNode)}
      {actionsMountNode && createPortal(<BuilderActions />, actionsMountNode)}
      <BuilderApp layoutId={layoutId} layouts={layouts} />
    </>
  );
}
