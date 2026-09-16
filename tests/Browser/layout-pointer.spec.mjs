import { test, expect } from '@playwright/test';
import { login, waitAction } from './helpers.mjs';
const action = (page, name, data) => page.evaluate(async ({ name, data }) =>
  (await Craft.sendActionRequest('POST', `cp-nav/layout/${name}`, { data })).data, { name, data });

for (const rejectFirst of [false, true]) {
  test(`${rejectFirst ? 'rolls back rejected' : 'persists'} pointer layout moves and blocks competing keyboard saves`, async ({ page }) => {
    await login(page);
    const ids = [];
    let release = () => {};
    let rejected = rejectFirst;
    let arrivals = 0;
    try {
      for (const name of ['Pointer A', 'Pointer B', 'Pointer C']) ids.push((await action(page, 'new', { name })).layout.id);
      await page.goto('/admin/cp-nav/layouts');
      const rows = () => page.locator('#layoutItems tbody tr').evaluateAll((rows, ids) => rows.map(row => Number(row.dataset.id)).filter(id => ids.includes(id)), ids);
      const a = page.locator(`#layoutItems tr[data-id="${ids[0]}"]`);
      const b = page.locator(`#layoutItems tr[data-id="${ids[1]}"]`);
      const handle = a.locator('.move');
      const matcher = url => decodeURIComponent(url.href).includes('cp-nav/layout/reorder');
      let gate = new Promise(resolve => { release = resolve; });
      await page.route(matcher, async route => {
        arrivals++;
        await gate;
        if (rejected) await route.fulfill({ status: 400, contentType: 'application/json', body: JSON.stringify({ message: 'Reorder rejected' }) });
        else await route.continue();
      });
      await b.scrollIntoViewIfNeeded();
      const source = await handle.boundingBox();
      const target = await b.boundingBox();
      await page.mouse.move(source.x + source.width / 2, source.y + source.height / 2);
      await page.mouse.down();
      await page.mouse.move(source.x + source.width / 2, target.y + target.height - 3, { steps: 20 });
      // Garnish waits for its helper/insertion animation before committing the drop.
      await expect.poll(rows).toEqual([ids[1], ids[0], ids[2]]);
      await page.mouse.up();
      await expect.poll(() => arrivals).toBe(1);
      await expect(handle).toBeDisabled();
      await handle.dispatchEvent('keydown', { key: 'ArrowDown' });
      await expect.poll(rows).toEqual([ids[1], ids[0], ids[2]]);
      expect(arrivals).toBe(1);
      const saved = waitAction(page, 'layout/reorder');
      release();
      expect((await saved).status()).toBe(rejectFirst ? 400 : 200);
      await expect(handle).toBeEnabled();
      await page.reload();
      const savedOrder = rejectFirst ? ids : [ids[1], ids[0], ids[2]];
      await expect.poll(rows).toEqual(savedOrder);
      rejected = true;
      gate = new Promise(resolve => { release = resolve; });
      await handle.focus();
      await page.keyboard.press('ArrowDown');
      await expect.poll(() => arrivals).toBe(2);
      await expect(handle).toBeDisabled();
      release();
      await expect(handle).toBeEnabled();
      await expect(handle).toBeFocused();
      await expect.poll(rows).toEqual(savedOrder);
      rejected = false;
      const retry = waitAction(page, 'layout/reorder');
      await page.keyboard.press('ArrowDown');
      expect((await retry).status()).toBe(200);
      await expect(handle).toBeEnabled();
      await page.reload();
      await expect.poll(rows).toEqual(rejectFirst ? [ids[1], ids[0], ids[2]] : [ids[1], ids[2], ids[0]]);
    } finally {
      await page.mouse.up();
      release();
      await page.unrouteAll({ behavior: 'wait' });
      for (const id of ids) await action(page, 'delete', { id });
    }
  });
}
