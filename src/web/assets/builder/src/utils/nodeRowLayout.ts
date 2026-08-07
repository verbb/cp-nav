import { cn } from './cn';

export const NODE_LEVEL_INDENT_PX = 20;

/** Show (lightswitch) column — matches `3.5rem` in the grid template. */
export const NODE_TREE_SHOW_COLUMN_PX = 56;

/** URL column — matches `10rem` in the grid template (wide layout only). */
export const NODE_TREE_URL_COLUMN_PX = 160;

/** Type column — matches `8rem` in the grid template (wide layout only). */
export const NODE_TREE_TYPE_COLUMN_PX = 128;

/** Actions (⋮) column — matches `2.25rem` in the grid template. */
export const NODE_TREE_ACTIONS_COLUMN_PX = 36;

/**
 * Wide layout threshold in **container** px (not viewport). Craft’s content pane is often
 * narrower than the window when the details sidebar is open — viewport `md` caused a
 * 5-column + min-width table to overflow and sticky Actions to paint over Label.
 */
export const NODE_TREE_WIDE_MIN_PX = 768;

/** @deprecated Prefer `isNodeTreeWideLayout` with a measured container width. */
export const NODE_TREE_WIDE_MEDIA_QUERY = `(min-width: ${NODE_TREE_WIDE_MIN_PX}px)`;

/** Top-level (level 1) is 0; each level deeper adds 20px (level 2 → 20px). */
export function getNodeRowPaddingLeft(level: number): number {
  return Math.max(0, level - 1) * NODE_LEVEL_INDENT_PX;
}

export function isNodeTreeWideLayout(widthPx: number): boolean {
  return widthPx >= NODE_TREE_WIDE_MIN_PX;
}

/** @deprecated Use `isNodeTreeWideLayout` with container width. */
export function isNodeTreeWideViewport(width = typeof window !== 'undefined' ? window.innerWidth : NODE_TREE_WIDE_MIN_PX): boolean {
  return isNodeTreeWideLayout(width);
}

/**
 * Columns: Show | Label | URL | Type | Actions.
 * Narrow: Show | Label | Actions (URL/Type use container `@min-[768px]:`).
 * Arbitrary 768px — `@min-md` resolved to container-md (28rem) in this theme, not 48rem.
 */
export function getNodeTreeGridClass(showTypeColumn: boolean): string {
  if (showTypeColumn) {
    return 'grid grid-cols-[3.5rem_minmax(0,1fr)_2.25rem] @min-[768px]:grid-cols-[3.5rem_minmax(0,1fr)_10rem_8rem_2.25rem]';
  }

  return 'grid grid-cols-[3.5rem_minmax(0,1fr)_2.25rem] @min-[768px]:grid-cols-[3.5rem_minmax(0,1fr)_10rem_2.25rem]';
}

/** Hide secondary columns until the tree container is ≥768px — match `getNodeTreeGridClass`. */
export function nodeTreeSecondaryColumnClass(className?: string): string {
  return cn('hidden @min-[768px]:flex', className);
}

export function nodeTreeGridClass(showTypeColumn: boolean, className?: string): string {
  return cn(getNodeTreeGridClass(showTypeColumn), className);
}
