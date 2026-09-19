import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';
import { seedCpNavFixture } from '../../support/fixtures';

let route = '/admin/cp-nav';
export default defineScreenshotScenario({
    id: 'cp-nav-feature-tour-new-item',
    output: 'feature-tour/new-cp-nav.png',
    route: () => route,
    viewport: { width: 900, height: 720, deviceScaleFactor: 2 },
    expectedOutput: { width: 339, height: 440 },
    async setup(context) { route = (await seedCpNavFixture(context)).navigationRoute; },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '.add-new-menu-item', state: 'visible' },
    ],
    steps: [
        { type: 'click', selector: '.add-new-menu-item' },
        { type: 'wait', waitFor: { type: 'selector', selector: '.hud', state: 'visible' } },
        {
            type: 'evaluate',
            expression: `
                (() => {
                    const hud = document.querySelector('.hud');
                    if (!hud) throw new Error('CP Nav new-item HUD was not found.');
                    const frame = document.createElement('div');
                    frame.id = 'cp-nav-screenshot-frame';
                    frame.style.cssText = 'position:fixed;left:0;top:0;width:170px;height:220px;overflow:hidden;background:#fff;z-index:2147483646';
                    hud.style.cssText += ';position:static!important;display:block!important;opacity:1!important;transform:scale(.5)!important;transform-origin:top left!important;width:340px!important;max-width:none!important;margin:0!important';
                    frame.appendChild(hud);
                    document.body.appendChild(frame);
                })();
            `,
        },
    ],
    target: { type: 'selector', selector: '#cp-nav-screenshot-frame', padding: 0 },
    caption: 'Creating a custom CP Nav item with its label, destination and icon.',
    intent: 'Recreates the production custom-item popover using the current Craft 5 HUD.',
});
