import { test, expect } from '@playwright/test';
import { login, waitAction } from './helpers.mjs';
const action = (page, name, data) => page.evaluate(async ({ name, data }) =>
  (await Craft.sendActionRequest('POST', `cp-nav/${name}`, { data })).data, { name, data });

for (const character of ['A', '界']) {
  test(`duplicates a maximum-length ${character === 'A' ? 'ASCII' : 'Unicode'} layout name`, async ({ page }) => {
    await login(page);
    const ids = [];
    try {
      const name = character.repeat(255);
      const { layout } = await action(page, 'layout/new', { name });
      ids.push(layout.id);
      await action(page, 'api/update-node', { layoutId: layout.id, key: 'craft:dashboard', data: { currLabel: 'Copied customization' } });
      await page.goto('/admin/cp-nav/layouts');
      const duplicated = waitAction(page, 'layout/duplicate');
      await page.locator(`#layoutItems tr[data-id="${layout.id}"] .duplicate`).click();
      const response = await duplicated;
      expect(response.status()).toBe(200);
      const copy = (await response.json()).layout;
      ids.push(copy.id);
      expect(copy.name).toBe(character.repeat(250) + ' copy');
      await page.reload();
      await expect(page.locator(`#layoutItems tr[data-id="${copy.id}"] strong`)).toHaveText(copy.name);
      await expect(page.locator(`#layoutItems tr[data-id="${layout.id}"] strong`)).toHaveText(name);
      const tree = await action(page, 'api/layout-tree', { layoutId: copy.id });
      expect(tree.nodes.find(node => node.key === 'craft:dashboard').label).toBe('Copied customization');
    } finally {
      for (const id of ids) await action(page, 'layout/delete', { id });
    }
  });
}
