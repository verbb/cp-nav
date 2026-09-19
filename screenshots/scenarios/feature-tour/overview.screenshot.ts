import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedCpNavFixture } from '../../support/fixtures';
import { createExactFrameStep } from '../../support/presets';

let navigationRoute = '/admin/cp-nav';

export default defineScreenshotScenario({
    id: 'cp-nav-feature-tour-overview',
    output: 'feature-tour/main-new.png',
    route: () => navigationRoute,
    viewport: { width: 1100, height: 700, deviceScaleFactor: 2 },
    expectedOutput: { width: 1262, height: 591 },
    async setup(context) {
        const fixture = await seedCpNavFixture(context);
        navigationRoute = fixture.navigationRoute;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '#cp-nav-items .cp-nav-item', state: 'visible' },
    ],
    preSteps: [
        createExactFrameStep('#main-container', 631, 296, 0.52),
        { type: 'wait', waitFor: { type: 'selector', selector: '#cp-nav-screenshot-frame', state: 'visible' } },
    ],
    target: { type: 'selector', selector: '#cp-nav-screenshot-frame', padding: 0 },
    caption: 'CP Nav showing current and custom control-panel navigation items.',
    intent: 'Recreates the production overview with the current Craft 5 navigation editor.',
});
