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
    let reject = true;
    await page.route(url => decodeURIComponent(url.href).includes('cp-nav/api/create-node'), async route => {
      if (!reject) {
        await route.continue();
        return;
      }
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
      release();
      // Keep interception active while Craft fetches the error icon and recovers.
      await expect(page.locator('#notifications')).toContainText('Draft save rejected');
      await expect(label).toBeVisible();
      await expect(label).toHaveValue('Recoverable draft');
      await expect(url).toHaveValue('https://example.test/draft');
      await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeEnabled();
      reject = false;
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
    } finally {
      release();
      await page.unrouteAll({ behavior: 'wait' });
    }
  });
}

// Hold the real exit animation at its asynchronous boundary to make rapid reopening repeatable.
test('keeps a newly opened editor after the previous editor finishes closing', async ({ page }) => {
  await login(page);
  await page.goto('/admin/cp-nav');
  await expect(page.locator('[data-key="craft:dashboard"]')).toBeVisible();
  await page.getByRole('button', { name: 'New menu item', exact: true }).click();
  const label = page.locator('pk-input[name="currLabel"] input');
  await label.fill('First draft');
  await page.evaluate(() => {
    const popover = document.querySelector('#cpnav-builder-app').shadowRoot.querySelector('pk-popover');
    const original = popover.waitForExitAnimation.bind(popover);
    popover.waitForExitAnimation = async () => {
      await original();
      await new Promise(resolve => { window.releaseEditorExit = resolve; });
    };
  });
  await page.getByRole('button', { name: 'Cancel', exact: true }).click();
  await page.waitForFunction(() => typeof window.releaseEditorExit === 'function');
  await page.getByRole('button', { name: 'New menu item', exact: true }).click();
  await label.fill('Second draft');
  await page.evaluate(() => window.releaseEditorExit());
  await expect(label).toBeVisible();
  await expect(label).toHaveValue('Second draft');
  await page.locator('pk-input[name="url"] input').fill('https://example.test/second');
  await page.getByRole('button', { name: 'Cancel', exact: true }).click();
  await expect(label).toHaveCount(0);
});
