import { PkSpinner } from '@verbb/plugin-kit-web/components/spinner/pk-spinner.js';

/**
 * pk-button embeds `<pk-spinner>` while `loading` but never imports that CE (plugin-kit 2.0.3).
 * A bare side-effect import is also dropped by Vite/Rolldown tree-shaking, so register (or
 * retain) the class via a live reference from main.
 */
export function ensurePkSpinnerRegistered(): void {
  if (!customElements.get('pk-spinner')) {
    customElements.define('pk-spinner', PkSpinner);
  }
}
