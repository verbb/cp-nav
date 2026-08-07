import type { PkStatusVariant } from '@verbb/plugin-kit-react/components';

export type PkOpenChangeEvent = CustomEvent<{ open: boolean }>;
export type PkCheckedChangeEvent = CustomEvent<{ checked: boolean }>;

/**
 * Imperative API surface we call on a React `<DropdownMenu>` ref.
 * Structural (not a Lit class import) so author code stays on the React package.
 */
export type DropdownMenuHost = {
  open?: boolean;
  addEventListener: HTMLElement['addEventListener'];
  removeEventListener: HTMLElement['removeEventListener'];
  forceDismissCleanup?: () => void;
};

export const asPkStatusVariant = (status: string): PkStatusVariant => status as PkStatusVariant;
