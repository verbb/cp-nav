import { test, expect } from '@playwright/test';
import { login, rowFor, waitAction, switchLayout, addLink } from './helpers.mjs';

const structure = page => page.locator('[data-tree-row]').evaluateAll(rows => rows.map(row => [row.dataset.key, row.dataset.level]));

async function drag(page, source, target, edge = 'middle') {
  await source.scrollIntoViewIfNeeded();
  await target.scrollIntoViewIfNeeded();
  const box = await target.boundingBox();
  await source.getByRole('button', { name: 'Drag to reorder', exact: true }).dragTo(target, {
    targetPosition: { x: box.width / 2, y: edge === 'top' ? 2 : box.height * 0.6 },
  });
}

test('drags a child into a parent and reorders the collapsed subtree without losing descendants', async ({ page }) => {
  await login(page);
  await page.goto('/admin/cp-nav');
  await switchLayout(page, 'Browser drag');
  page.once('dialog', dialog => dialog.accept());
  const reset = waitAction(page, 'api/reset-layout');
  await page.getByRole('button', { name: 'Reset navigation', exact: true }).click();
  expect((await reset).status()).toBe(200);
  await expect(page.locator('[data-key^="manual:"]')).toHaveCount(0);
  await addLink(page, 'Drag parent');
  await addLink(page, 'Drag child');
  await addLink(page, 'Drag sibling');
  await page.getByRole('button', { name: 'More options', exact: true }).click();
  await page.getByRole('menuitem', { name: 'New divider item', exact: true }).click();
  await page.locator('pk-input[name="currLabel"] input').fill('Drag divider');
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  const divider = page.locator('[data-key^="divider:"]');
  await expect(divider).toBeVisible();
  const parent = rowFor(page, 'Drag parent');
  const child = rowFor(page, 'Drag child');
  const parentKey = await parent.getAttribute('data-key');
  const childKey = await child.getAttribute('data-key');
  const before = await structure(page);
  const nested = waitAction(page, 'api/reorder-nodes');
  await drag(page, child, parent);
  expect((await nested).status()).toBe(200);
  const expectedNested = before.map(([key, level]) => [key, key === childKey ? '2' : level]);
  await expect.poll(() => structure(page)).toEqual(expectedNested);
  await page.reload();
  await expect.poll(() => structure(page)).toEqual(expectedNested);
  await expect(parent.locator('button[aria-expanded]')).toHaveAccessibleName('Collapse Drag parent');
  await parent.getByRole('button', { name: 'Collapse Drag parent', exact: true }).focus();
  await page.keyboard.press('Enter');
  await expect(child).toHaveCount(0);
  await expect(parent.locator('button[aria-expanded]')).toHaveAccessibleName('Expand Drag parent');
  const reordered = waitAction(page, 'api/reorder-nodes');
  await drag(page, parent, page.locator('[data-key="craft:dashboard"]'), 'top');
  expect((await reordered).status()).toBe(200);
  await parent.getByRole('button', { name: 'Expand Drag parent', exact: true }).focus();
  await page.keyboard.press('Space');
  const expectedMoved = [[parentKey, '1'], [childKey, '2'], ...before.filter(([key]) => key !== parentKey && key !== childKey)];
  await expect.poll(() => structure(page)).toEqual(expectedMoved);
  await page.reload();
  await expect.poll(() => structure(page)).toEqual(expectedMoved);
  // A divider cannot own children. Its middle drop zone falls back to a sibling insertion.
  const sibling = rowFor(page, 'Drag sibling');
  const siblingKey = await sibling.getAttribute('data-key');
  const besideDivider = waitAction(page, 'api/reorder-nodes');
  await drag(page, sibling, divider);
  expect((await besideDivider).status()).toBe(200);
  const expectedBesideDivider = [...expectedMoved.filter(([key]) => key !== siblingKey), [siblingKey, '1']];
  await expect.poll(() => structure(page)).toEqual(expectedBesideDivider);
  await page.reload();
  await expect.poll(() => structure(page)).toEqual(expectedBesideDivider);
});
