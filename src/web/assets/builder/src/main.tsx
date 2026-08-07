// Register icon glyphs before any <pk-icon> render (empty registry in bundler builds).
import './icons';

// Head FOUCE tokens. Component imports register custom elements.
import '@verbb/plugin-kit-react/style.css';
import builderStyles from './css/style.css?inline';
import pluginKitStyles from '@verbb/plugin-kit-react/style.css?inline';

import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import { mountShadowApp, PluginKitProvider } from '@verbb/plugin-kit-react/utils';

import { BuilderErrorBoundary } from './components/BuilderErrorBoundary';
import { BuilderShell } from './components/BuilderShell';
import { ensurePkSpinnerRegistered } from './register-pk-spinner';
import {
  ensureCraftNamespace,
  mountBuilderShadowHost,
} from './utils/bootstrap';
import type { LayoutOption } from './types';

ensureCraftNamespace('CpNav');
// Button loading embeds <pk-spinner>; plugin-kit does not pull that CE in (see register module).
ensurePkSpinnerRegistered();

// Craft’s #notifications is a fixed hit layer (up to 350px / full width on narrow
// viewports) over the Show column. Builder styles live in shadow roots, so patch
// the document sheet so empty toast chrome doesn’t swallow clicks.
const NOTIFICATIONS_CLICKTHROUGH_ATTR = 'data-cpnav-notifications-clickthrough';

if (!document.head.querySelector(`style[${NOTIFICATIONS_CLICKTHROUGH_ATTR}]`)) {
  const style = document.createElement('style');
  style.setAttribute(NOTIFICATIONS_CLICKTHROUGH_ATTR, '');
  style.textContent = `
#notifications { pointer-events: none; }
#notifications .notification { pointer-events: auto; }
`;
  document.head.appendChild(style);
}

const builderStyleConfig = {
  pluginHandle: 'cpnav',
  // Tokens into each shadow root — head FOUCE CSS alone does not pierce shadow.
  styleTexts: [pluginKitStyles, builderStyles],
  styleNamespace: 'cpnav',
  styleAttr: 'data-cpnav-shadow-style',
  rootAttr: 'data-cpnav-shadow-root',
  portalClassName: 'cpnav-ui',
  translationCategory: 'cp-nav',
};

function parseLayouts(raw: string | undefined): LayoutOption[] {
  if (!raw) {
    return [];
  }

  try {
    return JSON.parse(raw) as LayoutOption[];
  } catch {
    return [];
  }
}

const appEl = document.querySelector('#cpnav-builder-app') as HTMLElement | null;
const headerEl = document.querySelector('#cpnav-builder-header-root') as HTMLElement | null;
const actionsEl = document.querySelector('#cpnav-builder-actions-root') as HTMLElement | null;

if (!appEl) {
  console.error('CP Nav builder container not found: #cpnav-builder-app');
} else {
  const layoutId = Number(appEl.dataset.layoutId ?? window.CpNavBuilderConfig?.layoutId ?? 0);
  const layouts = parseLayouts(appEl.dataset.layouts);

  // App shadow first so portals target the main builder, not a chrome slot.
  const { mountNode, portalContainer } = mountShadowApp({
    element: appEl,
    styles: builderStyleConfig.styleTexts,
    styleAttr: builderStyleConfig.styleAttr,
    rootAttr: builderStyleConfig.rootAttr,
  });

  const headerMountNode = headerEl ? mountBuilderShadowHost(headerEl, builderStyleConfig).mountNode : null;
  const actionsMountNode = actionsEl ? mountBuilderShadowHost(actionsEl, builderStyleConfig).mountNode : null;

  createRoot(mountNode).render(
    <StrictMode>
      <PluginKitProvider
        translationCategory={builderStyleConfig.translationCategory}
        portalContainer={portalContainer}
        portalClassName={builderStyleConfig.portalClassName}
        shadowRootSelectors={[`[${builderStyleConfig.rootAttr}]`]}
      >
        <BuilderErrorBoundary>
          <BuilderShell
            layoutId={layoutId}
            layouts={layouts}
            headerMountNode={headerMountNode}
            actionsMountNode={actionsMountNode}
          />
        </BuilderErrorBoundary>
      </PluginKitProvider>
    </StrictMode>,
  );
}
