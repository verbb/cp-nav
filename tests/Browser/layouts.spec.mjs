import { test, expect } from '@playwright/test';
import { login, waitAction } from './helpers.mjs';

test('recovers when the new-layout form fails to load', async ({ page }) => {
  await login(page);
  await page.goto('/admin/cp-nav/layouts');
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.route(url => decodeURIComponent(url.href).includes('cp-nav/layout/get-hud-html'), route => route.fulfill({
    status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Layout form unavailable' }),
  }), { times: 1 });
  const trigger = page.locator('.add-new-layout');
  const failed = waitAction(page, 'layout/get-hud-html');
  await trigger.click();
  expect((await failed).status()).toBe(500);
  await expect(trigger).not.toHaveClass(/loading/);
  await expect(page.locator('#notifications')).toContainText('Layout form unavailable');
  await trigger.click();
  const hud = page.locator('.hud:visible');
  await expect(hud.locator('input[name="name"]')).toBeVisible();
  await hud.getByRole('button', { name: 'Cancel', exact: true }).click();
  expect(errors).toEqual([]);
});

test('submits a layout only once while saving and allows retry after failure', async ({ page }) => {
  await login(page);
  await page.goto('/admin/cp-nav/layouts');
  await page.locator('.add-new-layout').click();
  const hud = page.locator('.hud:visible');
  await hud.locator('input[name="name"]').fill('Browser retry layout');
  const save = hud.getByRole('button', { name: 'Save', exact: true });
  let release;
  const gate = new Promise(resolve => { release = resolve; });
  let requests = 0;
  const matcher = url => decodeURIComponent(url.href).includes('cp-nav/layout/new');
  await page.route(matcher, async route => {
    requests++;
    await gate;
    await route.fulfill({ status: 400, contentType: 'application/json', body: JSON.stringify({ message: 'Layout save rejected' }) });
  });
  try {
    // Dispatch both clicks in one browser turn, before a server reply can mask re-entry.
    await save.evaluate(button => { button.click(); button.click(); });
    await expect.poll(() => requests).toBeGreaterThan(0);
    expect(requests).toBe(1);
    await expect(hud.locator('button[type="submit"]')).toBeDisabled();
  } finally {
    release();
    await page.unrouteAll({ behavior: 'wait' });
  }
  await expect(save).toBeEnabled();
  await expect(page.locator('#notifications')).toContainText('Layout save rejected');
  const created = waitAction(page, 'layout/new');
  await save.click();
  const response = await created;
  expect(response.status()).toBe(200);
  const id = (await response.json()).layout.id;
  try {
    await page.reload();
    await expect(page.locator('tr.layout-item').filter({ hasText: 'Browser retry layout' })).toHaveCount(1);
  } finally {
    await page.evaluate(id => Craft.sendActionRequest('POST', 'cp-nav/layout/delete', { data: { id } }), id);
  }
});

test('creates, renames, duplicates and deletes layouts with literal names and saved group assignments', async ({ page }) => {
  await login(page);
  await page.goto('/admin/cp-nav/layouts');
  const ids = [];
  const hud = page.locator('.hud:visible');
  const literalName = 'Browser <em>renamed</em>';
  try {
    await page.locator('.add-new-layout').click();
    await hud.locator('input[name="name"]').fill('Browser original');
    await hud.locator('label').filter({ hasText: /^\s*Browser editors\s*$/ }).click();
    await expect(hud.getByRole('checkbox', { name: 'Browser editors', exact: true })).toBeChecked();
    const created = waitAction(page, 'layout/new');
    await hud.getByRole('button', { name: 'Save', exact: true }).click();
    const result = await created;
    expect(result.status()).toBe(200);
    const id = (await result.json()).layout.id;
    ids.push(id);
    const row = page.locator(`tr.layout-item[data-id="${id}"]`);
    await row.locator('.edit-layout').click();
    await expect(hud.getByRole('checkbox', { name: 'Browser editors', exact: true })).toBeChecked();
    await hud.locator('input[name="name"]').fill(literalName);
    const saved = waitAction(page, 'layout/save');
    await hud.getByRole('button', { name: 'Save', exact: true }).click();
    expect((await saved).status()).toBe(200);
    await expect(row.locator('.edit-layout')).toHaveText(literalName);
    await expect(row.locator('em')).toHaveCount(0);
    // Duplicate before reloading: this must use the new name, including jQuery's cached row data.
    const duplicated = waitAction(page, 'layout/duplicate');
    await row.getByRole('button', { name: 'Duplicate', exact: true }).click();
    const copyResponse = await duplicated;
    expect(copyResponse.status()).toBe(200);
    const copyId = (await copyResponse.json()).layout.id;
    ids.push(copyId);
    const copy = page.locator(`tr.layout-item[data-id="${copyId}"]`);
    await expect(copy.locator('.edit-layout')).toHaveText(`${literalName} copy`);
    await page.reload();
    await expect(row.locator('.edit-layout')).toHaveText(literalName);
    await expect(copy.locator('.edit-layout')).toHaveText(`${literalName} copy`);
    await copy.locator('.edit-layout').click();
    await expect(hud.getByRole('checkbox', { name: 'Browser editors', exact: true })).toBeChecked();
    await hud.getByRole('button', { name: 'Cancel', exact: true }).click();
    for (const target of [copy, row]) {
      page.once('dialog', dialog => dialog.accept());
      const deleted = waitAction(page, 'layout/delete');
      await target.getByRole('button', { name: 'Delete', exact: true }).click();
      expect((await deleted).status()).toBe(200);
      await expect(target).toHaveCount(0);
    }
    ids.length = 0;
    await page.reload();
    await expect(row).toHaveCount(0);
    await expect(copy).toHaveCount(0);
  } finally {
    // Clean up owned layouts after assertion failures as well as successful runs.
    await page.evaluate(async ids => {
      for (const id of ids) {
        await Craft.sendActionRequest('POST', 'cp-nav/layout/delete', { data: { id } }).catch(() => {});
      }
    }, ids).catch(() => {});
  }
});
