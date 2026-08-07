import { defineScreenshotScenario } from '@verbb/docs-screenshots/api';
import { seedCpNavDocsFixture } from '../.screenshots/cp-nav/fixtures';
import {
    cpNavOverviewViewport,
    createCpNavFullCpCleanupStep,
    createCpNavFullCpCropStep,
} from '../.screenshots/cp-nav/presets';

let builderRoute = '/admin/cp-nav';

export default defineScreenshotScenario({
    id: 'feature-tour-overview-builder',
    output: '_screenshots/feature-tour/overview-builder.png',
    route: () => builderRoute,
    // Promo frame 924×700 — width shaved vs classic; height tight under the builder card.
    viewport: cpNavOverviewViewport,
    async setup(context) {
        const fixture = await seedCpNavDocsFixture(context);
        builderRoute = fixture.builderRoute;
    },
    waitFor: [
        { type: 'selector', selector: 'body.cpnav-builder-ready' },
        { type: 'selector', selector: '#cpnav-builder-app', state: 'visible' },
        { type: 'selector', selector: 'craft-global-sidebar', state: 'visible' },
    ],
    preSteps: [
        createCpNavFullCpCleanupStep(),
        { type: 'wait', waitFor: { type: 'timeout', ms: 500 } },
        // Re-apply tree column packing after async tree rows mount.
        createCpNavFullCpCleanupStep(),
        { type: 'wait', waitFor: { type: 'timeout', ms: 200 } },
        createCpNavFullCpCropStep(),
        { type: 'wait', waitFor: { type: 'selector', selector: '#cpnav-docs-screenshot-frame', state: 'visible' } },
        { type: 'wait', waitFor: { type: 'timeout', ms: 200 } },
    ],
    steps: [],
    target: {
        type: 'selector',
        selector: '#cpnav-docs-screenshot-frame',
        padding: 0,
    },
    caption: 'Control Panel Nav builder in the Craft CP, with sidebar and nav tree.',
    intent: 'Match the classic promo composition at 924×700 (sidebar fudged on; bold Craft CMS label; tight bottom).',
});
