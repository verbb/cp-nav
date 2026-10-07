import { test, expect } from '@playwright/test';
import { login } from './helpers.mjs';

const isLayoutTree = url => decodeURIComponent(url.href).includes('cp-nav/api/layout-tree');

test('builder load failure uses ErrorState and recovers in place', async ({ page }) => {
  await login(page);
  let requests = 0;
  await page.route(isLayoutTree, async route => {
    if (++requests === 1) {
      await route.fulfill({
        status: 503,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Navigation service unavailable' }),
      });
      return;
    }

    await route.continue();
  });

  await page.goto('/admin/cp-nav');
  const errorState = page.locator('pk-state-panel[variant="error"]');
  await expect(errorState).toContainText('Couldn’t load navigation.');
  await expect(errorState).toContainText('The navigation builder could not be loaded.');
  await expect(page.locator('#notifications')).not.toContainText('Navigation service unavailable');

  await errorState.getByRole('button', { name: 'Retry', exact: true }).click();
  await expect.poll(() => requests).toBe(2);
  await expect(errorState).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'New menu item', exact: true })).toBeVisible();
});

test('builder preserves its new-items notice and uses the shared empty state', async ({ page }) => {
  await login(page);
  await page.goto('/admin/cp-nav');
  const config = await page.evaluate(() => window.CpNavBuilderConfig);
  const response = await page.evaluate(async layoutId => (
    await Craft.sendActionRequest('POST', 'cp-nav/api/layout-tree', { data: { layoutId } })
  ).data, config.layoutId);

  await page.route(isLayoutTree, route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      ...response,
      nodes: [],
      meta: { ...response.meta, newItemCount: 3 },
    }),
  }));
  await page.reload();

  await expect(page.locator('pk-alert[variant="info"]')).toHaveCount(0);
  await expect(page.getByText('3 new menu items were added at Craft’s default positions.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Got it', exact: true })).toBeVisible();

  const emptyState = page.locator('pk-state-panel[variant="empty"]');
  await expect(emptyState).toContainText('No navigation items yet');
  await expect(emptyState).toContainText('Use New menu item above');
});
