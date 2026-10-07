import { test, expect } from '@playwright/test';

const rowFor = (page, name) => page.locator('[data-tree-row]').filter({ has: page.getByRole('button', { name, exact: true }) });
const waitAction = (page, action) => page.waitForResponse(response => decodeURIComponent(response.url()).includes(`cp-nav/api/${action}`) && response.request().method() === 'POST');
async function switchLayout(page, name) {
  await page.locator('#cpnav-builder-toolbar').getByRole('button').click();
  await page.getByRole('menuitemradio', { name, exact: true }).click();
  await expect(page.locator('#cpnav-builder-toolbar')).toContainText(name);
}
async function reset(page) {
  page.once('dialog', dialog => dialog.accept());
  const response = waitAction(page, 'reset-layout');
  await page.getByRole('button', { name: 'Reset navigation', exact: true }).click();
  expect((await response).status()).toBe(200);
  await expect(page.locator('[data-key^="manual:"]')).toHaveCount(0);
}
async function move(page, name, direction) {
  await rowFor(page, name).getByRole('button', { name: `Actions for ${name}` }).click();
  await page.getByRole('menuitem', { name: direction, exact: true }).click();
}

test('persists edits moves and visibility, isolates layouts, and recovers a rejected reorder', async ({ page }) => {
  await page.goto('/admin/login');
  await page.getByRole('textbox', { name: 'Username or Email' }).fill('admin');
  await page.getByRole('textbox', { name: 'Password', exact: true }).fill('testing-only-password');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL(url => !url.pathname.endsWith('/login'));
  await page.goto('/admin/cp-nav');
  await expect(page.locator('[data-key="craft:dashboard"]')).toBeVisible();
  await switchLayout(page, 'Browser editors');
  await reset(page);
  await page.getByRole('button', { name: 'New menu item', exact: true }).click();
  await page.locator('pk-input[name="currLabel"] input').fill('Browser docs');
  await page.locator('pk-input[name="url"] input').fill('https://example.test/docs');
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(rowFor(page, 'Browser docs')).toBeVisible();
  await rowFor(page, 'Browser docs').getByRole('button', { name: 'Browser docs', exact: true }).click();
  await page.locator('pk-input[name="currLabel"] input').fill('Edited browser docs');
  await page.locator('pk-input[name="url"] input').fill('https://example.test/edited');
  await page.locator('pk-image-browser').getByRole('button', { name: /Custom Icon/ }).click();
  await page.getByRole('option', { name: 'mark.svg', exact: true }).click();
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(rowFor(page, 'Edited browser docs')).toContainText('https://example.test/edited');
  await rowFor(page, 'Edited browser docs').getByRole('button', { name: 'Edited browser docs', exact: true }).click();
  await expect(page.locator('pk-image-browser')).toContainText('mark.svg');
  await page.getByRole('button', { name: 'Cancel', exact: true }).click();
  const rows = page.locator('[data-tree-row]');
  const before = await rows.evaluateAll(nodes => nodes.map(node => node.dataset.key));
  const key = await rowFor(page, 'Edited browser docs').getAttribute('data-key');
  const index = before.indexOf(key);
  expect(index).toBeGreaterThan(0);
  const saved = waitAction(page, 'reorder-nodes');
  await move(page, 'Edited browser docs', 'Move up');
  expect((await saved).status()).toBe(200);
  const expected = [...before];
  [expected[index - 1], expected[index]] = [expected[index], expected[index - 1]];
  await expect.poll(() => rows.evaluateAll(nodes => nodes.map(node => node.dataset.key))).toEqual(expected);
  await page.reload();
  await expect.poll(() => rows.evaluateAll(nodes => nodes.map(node => node.dataset.key))).toEqual(expected);
  await page.route(url => decodeURIComponent(url.href).includes('actions/cp-nav/api/reorder-nodes'), route => route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Deliberate test failure' }) }), { times: 1 });
  const rejected = waitAction(page, 'reorder-nodes');
  await move(page, 'Edited browser docs', 'Move down');
  expect((await rejected).status()).toBe(500);
  await expect(page.getByText('Deliberate test failure', { exact: true })).toBeVisible();
  await expect.poll(() => rows.evaluateAll(nodes => nodes.map(node => node.dataset.key))).toEqual(expected);
  const toggle = waitAction(page, 'update-node');
  await rowFor(page, 'Edited browser docs').getByRole('switch').click();
  expect((await toggle).status()).toBe(200);
  await page.reload();
  await expect(rowFor(page, 'Edited browser docs').getByRole('switch')).not.toBeChecked();
  await switchLayout(page, 'Browser managers');
  await expect(rowFor(page, 'Edited browser docs')).toHaveCount(0);
  await switchLayout(page, 'Browser editors');
  await expect(rowFor(page, 'Edited browser docs')).toContainText('https://example.test/edited');
  // Delete a persisted manual item and prove the next load cannot resurrect it.
  await rowFor(page, 'Edited browser docs').getByRole('button', { name: 'Actions for Edited browser docs' }).click();
  page.once('dialog', dialog => dialog.accept());
  const deleted = waitAction(page, 'delete-node');
  await page.getByRole('menuitem', { name: 'Delete', exact: true }).click();
  expect((await deleted).status()).toBe(200);
  await expect(rowFor(page, 'Edited browser docs')).toHaveCount(0);
  // Reset must also clear a meaningful canonical override.
  await page.locator('[data-key="craft:dashboard"] [data-cpnav-editor-anchor]').click();
  await page.locator('pk-input[name="currLabel"] input').fill('Temporary home');
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(rowFor(page, 'Temporary home')).toBeVisible();
  await reset(page);
  await page.reload();
  await expect(rowFor(page, 'Dashboard')).toBeVisible();
  await expect(rowFor(page, 'Temporary home')).toHaveCount(0);
  await page.goto('/admin/graphql/schemas');
  const graphql = page.getByRole('navigation', { name: 'Primary', exact: true }).getByRole('link', { name: /^GraphQL/ });
  await expect(graphql).toHaveAttribute('aria-current', 'true');
  await expect(page.getByRole('link', { name: 'Schemas', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(rowFor(page, 'Edited browser docs')).toHaveCount(0);
});
