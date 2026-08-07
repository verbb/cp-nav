import { isOrderedDragTarget, type TreeInstance } from '@headless-tree/core';
import type { BuilderNode } from '../types';
import {
  getNodeRowPaddingLeft,
  NODE_TREE_ACTIONS_COLUMN_PX,
  NODE_TREE_SHOW_COLUMN_PX,
  NODE_TREE_TYPE_COLUMN_PX,
  NODE_TREE_URL_COLUMN_PX,
  NODE_TREE_WIDE_MIN_PX,
  isNodeTreeWideLayout,
} from './nodeRowLayout';
import { CPNAV_TREE_ROOT_ID } from './headlessTreeData';

export type DragLinePosition = {
  top: number;
  left: number;
  right: number;
};

/** Label cell base inset (px-3 = 12px). */
const LABEL_BASE_LEFT_PX = 12;

function dragLineRightInset(showTypeColumn: boolean, wideLayout: boolean): number {
  // Narrow layout drops URL/Type — inset is actions only.
  if (!wideLayout) {
    return NODE_TREE_ACTIONS_COLUMN_PX;
  }

  return (
    NODE_TREE_URL_COLUMN_PX +
    (showTypeColumn ? NODE_TREE_TYPE_COLUMN_PX : 0) +
    NODE_TREE_ACTIONS_COLUMN_PX
  );
}

function dragLineLeft(level: number): number {
  return NODE_TREE_SHOW_COLUMN_PX + LABEL_BASE_LEFT_PX + getNodeRowPaddingLeft(level);
}

export function computeDragLinePosition(
  tree: TreeInstance<BuilderNode>,
  showTypeColumn: boolean,
  wideLayout = isNodeTreeWideLayout(
    typeof window !== 'undefined' ? window.innerWidth : NODE_TREE_WIDE_MIN_PX,
  ),
): DragLinePosition | null {
  const target = tree.getDragTarget();
  const treeEl = tree.getElement();

  if (!target || !treeEl) {
    return null;
  }

  const treeRect = treeEl.getBoundingClientRect();
  const right = dragLineRightInset(showTypeColumn, wideLayout);

  if (!isOrderedDragTarget(target)) {
    const folderItem = target.item;

    if (folderItem.getId() === CPNAV_TREE_ROOT_ID) {
      return null;
    }

    const folderRect = folderItem.getElement()?.getBoundingClientRect();

    if (!folderRect) {
      return null;
    }

    const childLevel = folderItem.getItemMeta().level + 2;

    return {
      top: folderRect.bottom - treeRect.top,
      left: dragLineLeft(childLevel),
      right,
    };
  }

  const items = tree.getItems().filter((item) => item.getId() !== CPNAV_TREE_ROOT_ID);
  const lineIndex = target.dragLineIndex;
  const level = target.dragLineLevel + 1;
  const left = dragLineLeft(level);

  const rectAt = (index: number) => items[index]?.getElement()?.getBoundingClientRect();

  if (items.length === 0) {
    return null;
  }

  let gapCenter: number | null = null;

  if (lineIndex >= items.length) {
    gapCenter = rectAt(items.length - 1)?.bottom ?? null;
  } else if (lineIndex <= 0) {
    gapCenter = rectAt(0)?.top ?? null;
  } else {
    const aboveRect = rectAt(lineIndex - 1);
    const belowRect = rectAt(lineIndex);

    if (aboveRect && belowRect) {
      gapCenter = (aboveRect.bottom + belowRect.top) / 2;
    } else {
      gapCenter = belowRect?.top ?? aboveRect?.bottom ?? null;
    }
  }

  if (gapCenter === null) {
    return null;
  }

  return {
    top: gapCenter - treeRect.top,
    left,
    right,
  };
}
