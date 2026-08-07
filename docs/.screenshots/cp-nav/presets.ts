import type {
    ScreenshotStep,
    ScreenshotTarget,
    ScreenshotViewport,
} from '@verbb/docs-screenshots/types';
import {
    createCpFocusedRegionPreset as createBaseCpFocusedRegionPreset,
    createCpFullScreenPreset as createBaseCpFullScreenPreset,
} from '@verbb/docs-screenshots/presets';

type CpFocusedRegionOptions = {
    selector?: string;
    viewport?: ScreenshotViewport;
    padding?: NonNullable<Extract<ScreenshotTarget, { type: 'selector' }>['padding']>;
    hidePlaceholder?: boolean;
};

type CpFullScreenOptions = {
    viewport?: ScreenshotViewport;
    hidePlaceholder?: boolean;
};

/**
 * Classic promo was 1024×823. Width 924 (100px tighter); height ~720 so the
 * frame ends just under the builder card instead of a tall empty CP gutter.
 * Sidebar is CSS-forced on below Craft’s 75rem breakpoint.
 */
export const cpNavOverviewViewport: ScreenshotViewport = {
    width: 924,
    height: 700,
    deviceScaleFactor: 2,
};

const cpNavScrollResetSelectors = [
    'html',
    'body',
    '#content-container',
    '#main-content',
    '#content',
    '.content-pane',
    '#cpnav-builder-app',
    '#page-container',
];

/**
 * Full-CP promo cleanup: classic-ish frame, expanded sidebar even under 75rem,
 * quiet chrome, and a bold docs-friendly site label ("Craft CMS").
 */
function buildCpNavFullCpCleanupCss({ hidePlaceholder = true }: { hidePlaceholder?: boolean }) {
    const rules = [
        'footer#global-footer { display: none !important; }',
        '#devmode { display: none !important; }',
        '#notifications { display: none !important; }',
        '#announcements { display: none !important; }',
        '#details-container { position: static !important; }',
        'body.fixed-header #header { position: static !important; top: auto !important; }',
        'body.fixed-header #content-container { padding-top: 0 !important; }',
        'html, body, * { scrollbar-width: none !important; -ms-overflow-style: none !important; }',
        'html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; }',

        // Craft hides the global sidebar below 75rem. Force desktop flex so the
        // classic-width promo still shows sidebar + builder together.
        // Keep Craft’s stock --global-sidebar-width (14.125rem) — do not shrink it.
        '#global-container { inset-inline-start: 0 !important; width: 100% !important; display: flex !important; overflow: visible !important; }',
        '.global-sidebar { --is-always-visible: true !important; flex: 0 0 var(--global-sidebar-width) !important; }',
        '#page-container { flex: 1 1 auto !important; min-width: 0 !important; width: auto !important; overflow: visible !important; }',
        '#header, #main-content { width: auto !important; max-width: none !important; }',
        // Promo frame is 924px — under Craft’s ~973px breakpoint #header becomes
        // display:block (title above actions). Keep a single aligned flex row.
        '#header { display: flex !important; align-items: center !important; flex-wrap: nowrap !important; place-content: stretch space-between !important; }',
        '#action-buttons { display: flex !important; margin-block-start: 0 !important; flex-direction: row !important; align-items: center !important; }',
        '#page-title { flex-wrap: nowrap !important; align-items: center !important; }',
        // Balanced gutters — enough on both sides for .content-pane box-shadow.
        // Keep bottom padding tight so the promo frame isn’t mostly empty CP chrome.
        '#main-content { padding: 1rem 1.25rem 0.75rem !important; overflow: visible !important; align-items: stretch !important; justify-content: flex-start !important; text-align: start !important; }',
        '#main, #content-container, #content, .content-pane { overflow: visible !important; }',
        '#content-container { flex: 1 1 auto !important; width: auto !important; max-width: none !important; min-width: 0 !important; }',
        '.content-pane { margin: 0 !important; padding-block-end: 0.5rem !important; }',
        // Don’t force a full-viewport tall empty column under the builder card.
        '#global-container, #page-container, #main { min-height: 0 !important; height: auto !important; }',
        'html, body { min-height: 0 !important; height: auto !important; }',
        // Builder host shouldn’t stretch taller than the tree.
        '#cpnav-builder-app { min-height: 0 !important; height: auto !important; }',
        '#content { padding-block-end: 0 !important; }',
        '#primary-nav-toggle { display: none !important; }',
        '#global-sidebar .nav-item__subnav { display: none !important; }',
        '#sidebar-trigger { display: none !important; }',

        // Classic promo had a heavy “Craft CMS” wordmark — Craft 5’s .h2 is medium.
        '#system-name, #system-name .h2, #system-name h2 { font-weight: 700 !important; font-size: 1.05rem !important; letter-spacing: -0.01em !important; -webkit-line-clamp: 1 !important; max-height: none !important; }',
    ];

    if (hidePlaceholder) {
        rules.push('.cp-placeholder, .placeholder { display: none !important; }');
    }

    return rules.join('\n');
}

/** Show URL + Type columns inside the builder shadow tree (light-DOM CSS cannot).
 * Match stock wide grid — Label `1fr`, fixed URL/Type — so columns stay straight. */
const cpNavForceWideTreeCss = `
[data-tree-row],
[data-cpnav-tree-container] [role="row"],
[data-cpnav-tree-container] [role="rowgroup"] {
  grid-template-columns: 3.5rem minmax(0, 1fr) 10rem 8rem 2.25rem !important;
  justify-content: stretch !important;
}
[data-cpnav-tree-container] [role="columnheader"].hidden,
[data-cpnav-tree-container] [role="cell"].hidden {
  display: flex !important;
}
`;

const cpNavTreeGridCols = '3.5rem minmax(0, 1fr) 10rem 8rem 2.25rem';

function buildCpNavFullCpCleanupStep({ hidePlaceholder = true }: { hidePlaceholder?: boolean } = {}): ScreenshotStep {
    const css = buildCpNavFullCpCleanupCss({ hidePlaceholder });

    return {
        type: 'evaluate',
        expression: `
            (() => {
                const styleId = 'cpnav-docs-screenshot-cleanup';
                let style = document.getElementById(styleId);
                if (!style) {
                    style = document.createElement('style');
                    style.id = styleId;
                    document.head.appendChild(style);
                }
                style.textContent = ${JSON.stringify(css)};

                // Expanded labels (not the collapsed icon rail).
                document.body.dataset.sidebar = 'expanded';
                document.body.classList.remove('showing-nav');

                // Promo-only label — install site name stays "Verbb Docs Screenshots".
                // Prefer the inner .h2 (Craft’s wordmark node); fall back to the wrapper.
                const systemName =
                    document.querySelector('#system-name .h2') ||
                    document.querySelector('#system-name h2') ||
                    document.querySelector('#system-name');
                if (systemName) {
                    systemName.textContent = 'Craft CMS';
                }

                // Builder UI is in shadow roots — pierce to keep URL/Type columns.
                // Set columns inline (beats Tailwind container-query utilities in adopted sheets).
                const forceWideCss = ${JSON.stringify(cpNavForceWideTreeCss)};
                const gridCols = ${JSON.stringify(cpNavTreeGridCols)};

                const shadowRoots = [];
                const appHost = document.querySelector('#cpnav-builder-app');
                if (appHost instanceof HTMLElement && appHost.shadowRoot) {
                    shadowRoots.push(appHost.shadowRoot);
                }
                // Nested open roots (if any).
                document.querySelectorAll('*').forEach((el) => {
                    if (el instanceof HTMLElement && el.shadowRoot && el.id !== 'cpnav-builder-app') {
                        // Only builder-related hosts.
                        if (el.id.startsWith('cpnav-builder') || el.hasAttribute('data-cpnav-shadow-root')) {
                            shadowRoots.push(el.shadowRoot);
                        }
                    }
                });

                let patched = 0;
                for (const root of shadowRoots) {
                    let shadowStyle = root.getElementById('cpnav-docs-force-wide');
                    if (!shadowStyle) {
                        shadowStyle = document.createElement('style');
                        shadowStyle.id = 'cpnav-docs-force-wide';
                        root.appendChild(shadowStyle);
                    }
                    shadowStyle.textContent = forceWideCss;

                    root.querySelectorAll('[data-tree-row], [role="row"], [role="rowgroup"]').forEach((el) => {
                        if (!(el instanceof HTMLElement)) return;
                        const display = window.getComputedStyle(el).display;
                        if (display !== 'grid') return;
                        el.style.setProperty('grid-template-columns', gridCols, 'important');
                        el.style.setProperty('justify-content', 'stretch', 'important');
                        patched += 1;
                    });
                }
                void patched;

                for (const selector of ${JSON.stringify(cpNavScrollResetSelectors)}) {
                    document.querySelectorAll(selector).forEach((el) => {
                        if (el instanceof HTMLElement) {
                            el.scrollTop = 0;
                            el.scrollLeft = 0;
                        }
                    });
                }

                window.scrollTo(0, 0);
            })();
        `,
    };
}

/** Full control-panel frame including Craft sidebar + CP Nav builder page. */
export function createCpNavFullCpCleanupStep(): ScreenshotStep {
    return buildCpNavFullCpCleanupStep({ hidePlaceholder: true });
}

/**
 * Fixed viewport-sized crop so Playwright captures the same composition as the
 * classic promo (sidebar + breadcrumbs/title/actions/tabs + nav tree).
 * Re-packs tree columns immediately before framing — rows mount async after the
 * first cleanup pass, and React can replace nodes afterward.
 */
export function createCpNavFullCpCropStep(
    width = cpNavOverviewViewport.width,
    height = cpNavOverviewViewport.height,
): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                const gridCols = ${JSON.stringify(cpNavTreeGridCols)};
                const appHost = document.querySelector('#cpnav-builder-app');
                const root = appHost instanceof HTMLElement ? appHost.shadowRoot : null;
                if (root) {
                    let shadowStyle = root.getElementById('cpnav-docs-force-wide');
                    if (!shadowStyle) {
                        shadowStyle = document.createElement('style');
                        shadowStyle.id = 'cpnav-docs-force-wide';
                        root.appendChild(shadowStyle);
                    }
                    shadowStyle.textContent = ${JSON.stringify(cpNavForceWideTreeCss)};
                    root.querySelectorAll('[data-tree-row], [role="row"], [role="rowgroup"]').forEach((el) => {
                        if (!(el instanceof HTMLElement)) return;
                        if (window.getComputedStyle(el).display !== 'grid') return;
                        el.style.setProperty('grid-template-columns', gridCols, 'important');
                        el.style.setProperty('justify-content', 'start', 'important');
                    });
                }

                let frame = document.getElementById('cpnav-docs-screenshot-frame');
                if (!frame) {
                    frame = document.createElement('div');
                    frame.id = 'cpnav-docs-screenshot-frame';
                    document.body.appendChild(frame);
                }

                Object.assign(frame.style, {
                    position: 'fixed',
                    left: '0',
                    top: '0',
                    width: ${width} + 'px',
                    height: ${height} + 'px',
                    zIndex: '2147483646',
                    pointerEvents: 'none',
                    boxSizing: 'border-box',
                });
            })();
        `,
    };
}

/** @deprecated Prefer {@link createCpNavFullCpCleanupStep} for overview promo shots. */
export function createCpNavBuilderPromoCleanupStep(): ScreenshotStep {
    return createCpNavFullCpCleanupStep();
}

/** @deprecated Prefer {@link createCpNavFullCpCropStep} for overview promo shots. */
export function createCpNavBuilderPromoCropStep(_padding = 20): ScreenshotStep {
    return createCpNavFullCpCropStep();
}

export function createCpFocusedRegionPreset(options: CpFocusedRegionOptions = {}) {
    return createBaseCpFocusedRegionPreset(options);
}

export function createCpFullScreenPreset(options: CpFullScreenOptions = {}) {
    return createBaseCpFullScreenPreset(options);
}
