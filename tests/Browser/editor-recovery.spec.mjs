import { test, expect } from '@playwright/test';
import { login } from './helpers.mjs';

for (const dismissal of ['Escape', 'outside click']) {
  test(`keeps the editor draft available after ${dismissal} during a rejected save`, async ({ page }) => {
    await login(page);
    await page.goto('/admin/cp-nav');
    await expect(page.locator('[data-key="craft:dashboard"]')).toBeVisible();
    await page.getByRole('button', { name: 'New menu item', exact: true }).click();
    const label = page.locator('pk-input[name="currLabel"] input');
    const url = page.locator('pk-input[name="url"] input');
    await label.fill('Recoverable draft');
    await url.fill('https://example.test/draft');
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    let received = false;
    await page.route(url => decodeURIComponent(url.href).includes('cp-nav/api/create-node'), async route => {
      received = true;
      await gate;
      await route.fulfill({ status: 400, contentType: 'application/json', body: JSON.stringify({ message: 'Draft save rejected' }) });
    });
    try {
      await page.getByRole('button', { name: 'Save', exact: true }).click();
      await expect.poll(() => received).toBe(true);
      if (dismissal === 'Escape') {
        await page.keyboard.press('Escape');
      } else {
        await page.locator('#page-heading').click();
      }
    } finally {
      release();
      await page.unrouteAll({ behavior: 'wait' });
    }
    await expect(page.locator('#notifications')).toContainText('Draft save rejected');
    await expect(label).toBeVisible();
    await expect(label).toHaveValue('Recoverable draft');
    await expect(url).toHaveValue('https://example.test/draft');
    await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeEnabled();
    const created = page.waitForResponse(response => decodeURIComponent(response.url()).includes('cp-nav/api/create-node') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    const response = await created;
    expect(response.status()).toBe(200);
    const data = await response.json();
    try {
      await expect(page.locator('[data-tree-row]').filter({ has: page.getByRole('link', { name: 'Recoverable draft', exact: true }) })).toBeVisible();
      await expect(label).toHaveCount(0);
    } finally {
      await page.evaluate(({ layoutId, key }) => Craft.sendActionRequest('POST', 'cp-nav/api/delete-node', { data: { layoutId, key } }), { layoutId: data.tree.layout.id, key: data.node.key });
    }
  });
}
