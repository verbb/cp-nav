import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import type { ScreenshotSetupContext } from '@verbb/docs-screenshots/types';

export type CpNavDocsFixture = {
    builderRoute: string;
};

const fixtureDir = dirname(fileURLToPath(import.meta.url));
const cpSidebarSeedScript = readFileSync(join(fixtureDir, 'seed-cp-sidebar.php'), 'utf8');

/**
 * Seed Pro + sample section/category group so the overview promo shows Entries,
 * Categories, and Users (classic denser CP nav). Plugin install/migrate stays
 * in plugin.bootstrap.ts.
 */
export async function seedCpNavDocsFixture(context: ScreenshotSetupContext): Promise<CpNavDocsFixture> {
    const output = await context.runCraftScript(cpSidebarSeedScript, { label: 'seed-cp-sidebar' });

    if (!output.trim().includes('ok')) {
        throw new Error(`seed-cp-sidebar did not report ok: ${output}`);
    }

    const adminPath = context.profile.adminPath ?? 'admin';

    return {
        builderRoute: `/${adminPath}/cp-nav`,
    };
}
