import { test, expect } from '@playwright/test';
import { login } from './helpers.mjs';
const action = (page, name, data) => page.evaluate(async ({ name, data }) =>
  (await Craft.sendActionRequest('POST', `cp-nav/${name}`, { data })).data, { name, data });

test('retains Users selection on account pages after nesting Users under another item', async ({ page }) => {
  await login(page);
  const { layout } = await action(page, 'layout/new', { name: 'Account selection fixture' });
  try {
    await action(page, 'api/reparent-node', { layoutId: layout.id, key: 'craft:users', parentKey: 'craft:dashboard' });
    for (const path of ['myaccount', 'myaccount/preferences', 'users']) {
      for (const url of [`/admin/${path}?layoutId=${layout.id}`, `/index.php?p=admin/${path}&layoutId=${layout.id}`]) {
        await page.goto(url);
        const dashboard = page.locator('#nav-dashboard');
        await expect(dashboard.locator('#nav-dashboard-subnav a').filter({ hasText: 'Users' })).toHaveAttribute('aria-current', 'page');
        await expect(dashboard.getByRole('button', { name: 'Open subnavigation', exact: true })).toHaveAttribute('aria-expanded', 'true');
        await expect(dashboard.locator('#nav-dashboard-subnav')).toHaveAttribute('data-state', 'expanded');
      }
    }
    const { node } = await action(page, 'api/create-node', {
      layoutId: layout.id, data: { type: 'manual', currLabel: 'My preferences shortcut', url: 'myaccount/preferences' },
    });
    await action(page, 'api/reparent-node', { layoutId: layout.id, key: node.key, parentKey: 'craft:settings' });
    await page.goto(`/admin/myaccount/preferences?layoutId=${layout.id}`);
    const settings = page.locator('#nav-settings');
    await expect(settings.getByRole('link', { name: 'My preferences shortcut', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(settings.getByRole('button', { name: 'Open subnavigation', exact: true })).toHaveAttribute('aria-expanded', 'true');
  } finally {
    await action(page, 'layout/delete', { id: layout.id });
  }
});
