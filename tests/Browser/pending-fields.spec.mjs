import { test, expect } from '@playwright/test';
import { login, addLink } from './helpers.mjs';

const action = (page, name, data) => page.evaluate(async ({ name, data }) =>
  (await Craft.sendActionRequest('POST', `cp-nav/${name}`, { data })).data, { name, data });

async function holdSave(page, endpoint, reject) {
  let release;
  let arrived;
  let result;
  let handled;
  const done = new Promise(resolve => { handled = resolve; });
  const gate = new Promise(resolve => { release = resolve; });
  const arrival = new Promise(resolve => { arrived = resolve; });
  const matcher = url => decodeURIComponent(url.href).includes(`cp-nav/${endpoint}`);
  await page.route(matcher, async route => {
    const response = reject ? null : await route.fetch();
    if (response) result = await response.json();
    arrived();
    await gate;
    await route.fulfill(response ? { response } : {
      status: 400, contentType: 'application/json', body: JSON.stringify({ message: 'Save rejected' }),
    });
    handled();
  });
  return { arrival, release, result: () => result, close: async () => { await done; await page.unroute(matcher); } };
}

for (const mode of ['create', 'edit']) {
  test(`locks menu item fields during ${mode} and restores the draft after failure`, async ({ page }) => {
    await login(page);
    const { layout } = await action(page, 'layout/new', { name: `Pending item ${mode}` });
    try {
      await page.goto(`/admin/cp-nav?layoutId=${layout.id}`);
      if (mode === 'edit') {
        await addLink(page, 'Existing item');
        await page.locator('[data-tree-row]').getByRole('link', { name: 'Existing item', exact: true }).click();
      } else {
        await page.getByRole('button', { name: 'New menu item', exact: true }).click();
      }
      const label = page.locator('pk-input[name="currLabel"] input');
      const url = page.locator('pk-input[name="url"] input');
      const windowControl = page.locator('form pk-lightswitch');
      const iconTrigger = page.locator('pk-image-browser').getByRole('button', { name: /Custom Icon/ });
      const save = page.getByRole('button', { name: 'Save', exact: true });
      await label.fill('Submitted item');
      await url.fill('https://example.test/submitted');
      for (const reject of [true, false]) {
        const hold = await holdSave(page, `api/${mode === 'create' ? 'create' : 'update'}-node`, reject);
        try {
          await save.click();
          await hold.arrival;
          await expect(label).toBeDisabled();
          await expect(url).toBeDisabled();
          await expect(windowControl).toHaveJSProperty('disabled', true);
          if (mode === 'edit') await expect(iconTrigger).toBeDisabled();
          hold.release();
          if (reject) {
            await expect(label).toBeEnabled();
            await expect(url).toBeEnabled();
            await expect(windowControl).toHaveJSProperty('disabled', false);
            if (mode === 'edit') await expect(iconTrigger).toBeEnabled();
            await expect(label).toHaveValue('Submitted item');
            await expect(url).toHaveValue('https://example.test/submitted');
            await label.fill('Retried item');
          } else {
            await expect(label).toHaveCount(0);
          }
        } finally {
          hold.release();
          await hold.close();
        }
      }
      await page.reload();
      await expect(page.locator('[data-tree-row]').getByRole('link', { name: 'Retried item', exact: true })).toHaveCount(1);
    } finally {
      await page.unrouteAll({ behavior: 'wait' });
      await action(page, 'layout/delete', { id: layout.id });
    }
  });

  test(`locks layout fields during ${mode} and preserves assignments through retry`, async ({ page }) => {
    await login(page);
    let id;
    if (mode === 'edit') id = (await action(page, 'layout/new', { name: 'Existing pending layout' })).layout.id;
    try {
      await page.goto('/admin/cp-nav/layouts');
      if (id) await page.locator(`tr[data-id="${id}"] .edit-layout`).click();
      else await page.locator('.add-new-layout').click();
      const hud = page.locator('.hud:visible');
      const name = hud.locator('input[name="name"]');
      const group = hud.getByRole('checkbox', { name: 'Browser editors', exact: true });
      const save = hud.getByRole('button', { name: 'Save', exact: true });
      await name.fill('Submitted layout');
      await hud.locator('label').filter({ hasText: /^\s*Browser editors\s*$/ }).click();
      for (const reject of [true, false]) {
        const hold = await holdSave(page, `layout/${mode === 'create' ? 'new' : 'save'}`, reject);
        try {
          await save.click();
          await hold.arrival;
          if (!reject) id = hold.result().layout.id;
          await expect(name).toBeDisabled();
          await expect(group).toBeDisabled();
          hold.release();
          if (reject) {
            await expect(name).toBeEnabled();
            await expect(group).toBeEnabled();
            await expect(name).toHaveValue('Submitted layout');
            await expect(group).toBeChecked();
            await name.fill('Retried layout');
          } else {
            await expect(name).toHaveCount(0);
          }
        } finally {
          hold.release();
          await hold.close();
        }
      }
      await page.reload();
      const row = page.locator(`tr[data-id="${id}"]`);
      await expect(row.locator('.edit-layout')).toHaveText('Retried layout');
      await row.locator('.edit-layout').click();
      await expect(hud.getByRole('checkbox', { name: 'Browser editors', exact: true })).toBeChecked();
    } finally {
      await page.unrouteAll({ behavior: 'wait' });
      if (id) await action(page, 'layout/delete', { id });
    }
  });
}
