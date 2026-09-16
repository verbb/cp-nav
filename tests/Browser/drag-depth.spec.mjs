import { test, expect } from '@playwright/test';
import { login, waitAction } from './helpers.mjs';
const action = (page, name, data) => page.evaluate(async ({ name, data }) =>
  (await Craft.sendActionRequest('POST', `cp-nav/${name}`, { data })).data, { name, data });

for (const collapsed of [false, true]) {
  test(`keeps ${collapsed ? 'collapsed' : 'expanded'} subtrees within two levels when dropped on another root`, async ({ page }) => {
    await login(page);
    const { layout } = await action(page, 'layout/new', { name: 'Depth fixture' });
    try {
      const keys = [];
      for (const currLabel of ['Depth parent', 'Depth child', 'Depth target']) {
        keys.push((await action(page, 'api/create-node', { layoutId: layout.id, data: { type: 'manual', currLabel, url: 'dashboard' } })).node.key);
      }
      await action(page, 'api/reparent-node', { layoutId: layout.id, key: keys[1], parentKey: keys[0] });
      await page.goto(`/admin/cp-nav?layoutId=${layout.id}`);
      const parent = page.locator(`[data-key="${keys[0]}"]`);
      const child = page.locator(`[data-key="${keys[1]}"]`);
      const target = page.locator(`[data-key="${keys[2]}"]`);
      if (collapsed) await parent.getByRole('button', { name: 'Collapse Depth parent', exact: true }).click();
      await target.scrollIntoViewIfNeeded();
      const box = await target.boundingBox();
      const reordered = waitAction(page, 'api/reorder-nodes');
      await parent.getByRole('button', { name: 'Drag to reorder', exact: true }).dragTo(target, {
        targetPosition: { x: box.width / 2, y: box.height * 0.6 },
      });
      expect((await reordered).status()).toBe(200);
      await expect(parent).toHaveAttribute('data-level', '1');
      if (collapsed) await parent.getByRole('button', { name: 'Expand Depth parent', exact: true }).click();
      await expect(child).toHaveAttribute('data-level', '2');
      await page.reload();
      await expect(parent).toHaveAttribute('data-level', '1');
      await expect(child).toHaveAttribute('data-level', '2');
      const tree = await action(page, 'api/layout-tree', { layoutId: layout.id });
      expect(tree.nodes.find(node => node.key === keys[1]).parentKey).toBe(keys[0]);
    } finally {
      await action(page, 'layout/delete', { id: layout.id });
    }
  });
}
