import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedCpNavFixture } from '../../support/fixtures';

let navigationRoute = '/admin/cp-nav';

export default defineScreenshotScenario({
    id: 'cp-nav-feature-tour-builder',
    output: 'feature-tour/cp-nav-builder.png',
    route: () => navigationRoute,
    viewport: { width: 1440, height: 900, deviceScaleFactor: 2 },
    async setup(context) {
        const fixture = await seedCpNavFixture(context);
        navigationRoute = fixture.navigationRoute;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '#cp-nav-items .cp-nav-item', state: 'visible' },
    ],
    target: {
        type: 'anchoredClip',
        selector: '#main-container',
        x: 0,
        y: 0,
        width: 1214,
        height: 520,
    },
    caption: 'CP Nav showing current and custom control-panel navigation items.',
    intent: 'Shows the real Craft 5 navigation builder with custom and native menu items in one prominent view.',
});
