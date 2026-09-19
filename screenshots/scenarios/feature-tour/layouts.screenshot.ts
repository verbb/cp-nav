import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';
import { seedCpNavFixture } from '../../support/fixtures';
import { createExactFrameStep } from '../../support/presets';

let route = '/admin/cp-nav/layouts';
export default defineScreenshotScenario({
    id: 'cp-nav-feature-tour-layouts',
    output: 'feature-tour/cp-nav-layout.png',
    route: () => route,
    viewport: { width: 900, height: 620, deviceScaleFactor: 2 },
    expectedOutput: { width: 253, height: 207 },
    async setup(context) { route = (await seedCpNavFixture(context)).layoutsRoute; },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '#layoutItems', state: 'visible' },
    ],
    preSteps: [createExactFrameStep('#layoutItems', 127, 104, 0.48)],
    target: { type: 'selector', selector: '#cp-nav-screenshot-frame', padding: 0 },
    caption: 'Multiple CP Nav layouts for different user groups.',
    intent: 'Recreates the production layouts list with current Craft 5 styling.',
});
