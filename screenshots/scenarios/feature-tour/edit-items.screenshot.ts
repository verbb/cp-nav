import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';
import { seedCpNavFixture } from '../../support/fixtures';
import { createExactFrameStep } from '../../support/presets';

let route = '/admin/cp-nav';
export default defineScreenshotScenario({
    id: 'cp-nav-feature-tour-edit-items',
    output: 'feature-tour/cp-nav-edit.png',
    route: () => route,
    viewport: { width: 900, height: 650, deviceScaleFactor: 2 },
    expectedOutput: { width: 253, height: 151 },
    async setup(context) { route = (await seedCpNavFixture(context)).navigationRoute; },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '#cp-nav-items tbody', state: 'visible' },
    ],
    preSteps: [createExactFrameStep('#cp-nav-items tbody', 127, 76, 0.38)],
    target: { type: 'selector', selector: '#cp-nav-screenshot-frame', padding: 0 },
    caption: 'Visibility and ordering controls for CP Nav items.',
    intent: 'Recreates the compact production detail showing per-item controls.',
});
