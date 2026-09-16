import { test, expect } from '@playwright/test';
import { login, waitAction } from './helpers.mjs';
const action = (page, name, data) => page.evaluate(async ({ name, data }) =>
  (await Craft.sendActionRequest('POST', `cp-nav/layout/${name}`, { data })).data, { name, data });

for (const addition of ['create', 'duplicate']) {
  test(`keeps a ${addition}d layout at its saved priority after a failed reorder`, async ({ page }) => {
    await login(page);
    const ids = [];
    let release = () => {};
    const matcher = url => decodeURIComponent(url.href).includes('cp-nav/layout/reorder');
    try {
      const { layout } = await action(page, 'new', { name: 'Priority recovery fixture' });
      ids.push(layout.id);
      await page.goto('/admin/cp-nav/layouts');
      const order = () => page.locator('#layoutItems tbody tr').evaluateAll(rows => rows.map(row => Number(row.dataset.id)));
      const original = await order();
      const row = page.locator(`#layoutItems tr[data-id="${layout.id}"]`);
      let arrivals = 0;
      const gate = new Promise(resolve => { release = resolve; });
      await page.route(matcher, async route => {
        arrivals++;
        await gate;
        await route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Reorder rejected' }) });
      });
      await row.locator('.move').focus();
      await page.keyboard.press('ArrowUp');
      await expect.poll(() => arrivals).toBe(1);
      await expect(row.locator('.move')).toBeDisabled();
      let response;
      if (addition === 'create') {
        await page.getByRole('button', { name: 'New layout', exact: true }).click();
        const hud = page.locator('.hud:visible');
        await hud.locator('input[name="name"]').fill('New priority fixture');
        const saved = waitAction(page, 'layout/new');
        await hud.getByRole('button', { name: 'Save', exact: true }).click();
        response = await saved;
      } else {
        const saved = waitAction(page, 'layout/duplicate');
        await row.locator('.duplicate').click();
        response = await saved;
      }
      expect(response.status()).toBe(200);
      const added = (await response.json()).layout.id;
      ids.push(added);
      await expect(page.locator(`#layoutItems tr[data-id="${added}"]`)).toBeVisible();
      const failed = waitAction(page, 'layout/reorder');
      release();
      expect((await failed).status()).toBe(500);
      await expect(row.locator('.move')).toBeEnabled();
      await expect.poll(order).toEqual([...original, added]);
      await page.unroute(matcher);
      await page.reload();
      await expect.poll(order).toEqual([...original, added]);
      const retry = waitAction(page, 'layout/reorder');
      await page.locator(`#layoutItems tr[data-id="${added}"] .move`).focus();
      await page.keyboard.press('ArrowUp');
      expect((await retry).status()).toBe(200);
      await page.reload();
      await expect.poll(order).toEqual([...original.slice(0, -1), added, layout.id]);
    } finally {
      release();
      await page.unrouteAll({ behavior: 'wait' });
      for (const id of ids) await action(page, 'delete', { id });
    }
  });
}
