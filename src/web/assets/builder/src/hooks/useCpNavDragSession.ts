import { useEffect, useRef, useState } from 'react';
import { isOrderedDragTarget, type TreeInstance } from '@headless-tree/core';
import type { BuilderNode } from '../types';
import { computeDragLinePosition, type DragLinePosition } from '../utils/dragLine';
import { isNodeTreeWideLayout } from '../utils/nodeRowLayout';

export type CpNavDragSessionSnapshot = {
  linePosition: DragLinePosition | null;
  nestTargetId: string | null;
};

function snapshotsEqual(a: CpNavDragSessionSnapshot, b: CpNavDragSessionSnapshot): boolean {
  if (a.nestTargetId !== b.nestTargetId) {
    return false;
  }

  if (a.linePosition === b.linePosition) {
    return true;
  }

  if (!a.linePosition || !b.linePosition) {
    return a.linePosition === b.linePosition;
  }

  return (
    a.linePosition.top === b.linePosition.top &&
    a.linePosition.left === b.linePosition.left &&
    a.linePosition.right === b.linePosition.right
  );
}

function treeContainerWidth(tree: TreeInstance<BuilderNode>): number {
  const treeEl = tree.getElement();
  const container = treeEl?.closest('[data-cpnav-tree-container]') ?? treeEl;

  return container?.clientWidth ?? 0;
}

function readSnapshot(
  tree: TreeInstance<BuilderNode>,
  showTypeColumn: boolean,
): CpNavDragSessionSnapshot {
  const target = tree.getDragTarget();
  const nestTargetId = target && !isOrderedDragTarget(target) ? target.item.getId() : null;
  const wideLayout = isNodeTreeWideLayout(treeContainerWidth(tree));

  return {
    linePosition: nestTargetId ? null : computeDragLinePosition(tree, showTypeColumn, wideLayout),
    nestTargetId,
  };
}

/** Poll the drag target during an HTML5 drag so rows and the insert line stay in sync. */
export function useCpNavDragSession(
  tree: TreeInstance<BuilderNode>,
  showTypeColumn: boolean,
  isDragSession: boolean,
): CpNavDragSessionSnapshot {
  const [snapshot, setSnapshot] = useState<CpNavDragSessionSnapshot>({
    linePosition: null,
    nestTargetId: null,
  });
  const lastStableSnapshotRef = useRef<CpNavDragSessionSnapshot>({
    linePosition: null,
    nestTargetId: null,
  });

  useEffect(() => {
    if (!isDragSession) {
      lastStableSnapshotRef.current = { linePosition: null, nestTargetId: null };
      setSnapshot({ linePosition: null, nestTargetId: null });
      return;
    }

    let frameId = 0;

    const measure = () => {
      const hasDragTarget = Boolean(tree.getDragTarget());
      let next = readSnapshot(tree, showTypeColumn);

      // HT briefly clears dragTarget on dragLeave between rows; hold the last target
      // so the line and nest highlight do not flash during fast pointer movement.
      if (hasDragTarget) {
        lastStableSnapshotRef.current = next;
      } else if (
        lastStableSnapshotRef.current.linePosition ||
        lastStableSnapshotRef.current.nestTargetId
      ) {
        next = lastStableSnapshotRef.current;
      }

      setSnapshot((prev) => (snapshotsEqual(prev, next) ? prev : next));
      frameId = window.requestAnimationFrame(measure);
    };

    frameId = window.requestAnimationFrame(measure);

    return () => {
      window.cancelAnimationFrame(frameId);
    };
  }, [isDragSession, showTypeColumn, tree]);

  return snapshot;
}
