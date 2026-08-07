export function isRowInteractiveTarget(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) {
    return false;
  }

  return Boolean(
    target.closest(
      'button, a, input, label, textarea, select, [role="checkbox"], [data-no-row-select]',
    ),
  );
}

/**
 * Dropdown menus close under the cursor; the dismiss click can land on the row label
 * and open/close the editor unexpectedly. Suppress label-open briefly after ⋮ Edit.
 */
let suppressRowToggleUntilMs = 0;

export function suppressRowSelectionToggle(durationMs = 400): void {
  suppressRowToggleUntilMs = Date.now() + durationMs;
}

export function shouldSuppressRowSelectionToggle(): boolean {
  return Date.now() < suppressRowToggleUntilMs;
}
