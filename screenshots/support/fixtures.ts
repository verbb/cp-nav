import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { ScreenshotSetupContext } from '@verbb/craft-screenshots/types';

type CpNavFixture = { navigationRoute: string };
const supportDir = dirname(fileURLToPath(import.meta.url));
const seedScript = readFileSync(join(supportDir, 'seed', 'seed-cp-nav.php'), 'utf8');

export async function seedCpNavFixture(context: ScreenshotSetupContext): Promise<CpNavFixture> {
    const output = await context.runCraftScript(seedScript, { label: 'seed-cp-nav' });
    const fixture = JSON.parse(output.trim()) as CpNavFixture;
    if (!fixture.navigationRoute) throw new Error(`Invalid CP Nav fixture payload: ${output}`);
    return fixture;
}
