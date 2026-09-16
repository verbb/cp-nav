import { test, expect } from '@playwright/test';
import { login, waitAction } from './helpers.mjs';
const action = (page, name, data) => page.evaluate(async ({ name, data }) =>
  (await Craft.sendActionRequest('POST', `cp-nav/${name}`, { data })).data, { name, data });

test('reports a failed icon catalog and retries without losing the editor draft or selection', async ({ page }) => {
  await login(page);
  const { layout } = await action(page, 'layout/new', { name: 'Icon retry fixture' });
  let release;
  const gate = new Promise(resolve => { release = resolve; });
  let requests = 0;
  try {
    await action(page, 'api/update-node', { layoutId: layout.id, key: 'craft:dashboard', data: { customIcon: 'mark.svg' } });
    await page.goto(`/admin/cp-nav?layoutId=${layout.id}`);
    await page.route(url => decodeURIComponent(url.href).includes('cp-nav/static-icons') && !decodeURIComponent(url.href).includes('static-icons/view'), async route => {
      if (++requests === 1) {
        await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Catalog unavailable' }) });
      } else {
        await gate;
        await route.continue();
      }
    });
    const row = page.locator('[data-key="craft:dashboard"]');
    await row.locator('[data-cpnav-editor-anchor]').click();
    const label = page.locator('pk-input[name="currLabel"] input');
    const picker = page.locator('pk-image-browser');
    await label.fill('Draft survives icon retry');
    await expect(page.getByRole('alert')).toContainText('Couldn’t load custom icons.');
    await expect(picker).toHaveJSProperty('value', 'mark.svg');
    await picker.getByRole('button', { name: /Custom Icon/ }).click();
    await expect(page.getByText('No SVG files found in the icons folder.')).toHaveCount(0);
    await expect(picker.getByRole('status')).toHaveText('Couldn’t load custom icons.');
    await page.keyboard.press('Escape');
    await expect(picker.getByRole('button', { name: /Custom Icon/ })).toHaveAttribute('aria-expanded', 'false');
    await page.getByRole('button', { name: 'Retry', exact: true }).click();
    await expect.poll(() => requests).toBe(2);
    await expect(picker).toHaveJSProperty('loading', true);
    await expect(label).toHaveValue('Draft survives icon retry');
    await expect(picker).toHaveJSProperty('value', 'mark.svg');
    release();
    await expect(picker).toHaveJSProperty('loading', false);
    await expect(page.getByRole('alert')).toHaveCount(0);
    await picker.getByRole('button', { name: /Custom Icon/ }).click();
    await expect(page.getByRole('option', { name: 'mark.svg', exact: true })).toHaveAttribute('aria-selected', 'true');
    await page.keyboard.press('Escape');
    await expect(picker.getByRole('button', { name: /Custom Icon/ })).toHaveAttribute('aria-expanded', 'false');
    const saved = waitAction(page, 'api/update-node');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    expect((await saved).status()).toBe(200);
    await page.reload();
    await row.locator('[data-cpnav-editor-anchor]').click();
    await expect(label).toHaveValue('Draft survives icon retry');
    await expect(picker).toHaveJSProperty('value', 'mark.svg');
  } finally {
    release();
    await page.unrouteAll({ behavior: 'wait' });
    await action(page, 'layout/delete', { id: layout.id });
  }
});
