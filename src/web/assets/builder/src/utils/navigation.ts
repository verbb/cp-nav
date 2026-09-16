import { hasPendingMutations, navigateAfterSaving, onPendingMutationsChange } from '../api';

/** Keep queued writes alive when navigating through Craft's surrounding UI. */
export function guardBuilderNavigation(layoutId: number): () => void {
  let navigating = false;
  const beforeUnload = (event: BeforeUnloadEvent) => {
    if (hasPendingMutations(layoutId)) {
      event.preventDefault();
      event.returnValue = '';
    }
  };
  const syncUnloadGuard = () => {
    window.removeEventListener('beforeunload', beforeUnload);
    if (hasPendingMutations(layoutId)) {
      window.addEventListener('beforeunload', beforeUnload);
    }
  };
  const click = (event: MouseEvent) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    const link = event.composedPath().find((element): element is HTMLAnchorElement => element instanceof HTMLAnchorElement);
    if (!link || !link.hasAttribute('href') || link.hasAttribute('download') || (link.target && link.target !== '_self')) {
      return;
    }
    const url = new URL(link.href, window.location.href);
    if (!['http:', 'https:'].includes(url.protocol) || (url.origin === location.origin && url.pathname === location.pathname && url.search === location.search)) {
      return;
    }
    if (!hasPendingMutations(layoutId)) {
      return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    if (!navigating) {
      navigating = true;
      void navigateAfterSaving(layoutId, url.href).finally(() => { navigating = false; });
    }
  };

  document.addEventListener('click', click, true);
  const unsubscribe = onPendingMutationsChange(syncUnloadGuard);
  syncUnloadGuard();
  return () => {
    unsubscribe();
    document.removeEventListener('click', click, true);
    window.removeEventListener('beforeunload', beforeUnload);
  };
}
