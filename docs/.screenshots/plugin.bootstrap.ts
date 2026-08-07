import type { ScreenshotSetupContext } from '@verbb/docs-screenshots/types';
import { registerPluginBootstrap } from '@verbb/docs-screenshots/api';

export default registerPluginBootstrap({
    id: 'plugin-default',
    async setup(context: ScreenshotSetupContext) {
        // Ensure pending plugin migrations apply on the disposable install.
        await context.runCraft(['migrate/up', '--plugin=cp-nav', '--interactive=0'], {
            allowFailure: true,
        });
    },
});
