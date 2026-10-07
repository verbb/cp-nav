import { test, expect } from '@playwright/test';
import { login } from './helpers.mjs';

const action = (page, name, data) => page.evaluate(async ({ name, data }) =>
  (await Craft.sendActionRequest('POST', `cp-nav/${name}`, { data })).data, { name, data });
const labelField = page => page.locator('pk-input[name="currLabel"] input');
const dashboardTrigger = page => page.locator('[data-key="craft:dashboard"] [data-cpnav-editor-anchor]');
const createTrigger = page => page.getByRole('button', { name: 'New menu item', exact: true });

let layoutId;
test.beforeEach(async ({ page }) => {
  await login(page);
  const { layout } = await action(page, 'layout/new', { name: 'Editor accessibility fixture' });
  layoutId = layout.id;
  await page.goto(`/admin/cp-nav?layoutId=${layoutId}`);
  await expect(dashboardTrigger(page)).toBeVisible();
});
test.afterEach(async ({ page }) => {
  await action(page, 'layout/delete', { id: layoutId });
});

async function expectAdjacentEditor(page, trigger) {
  await expect(labelField(page)).toBeVisible();
  await expect(trigger).toBeFocused();
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  // Assert DOM/reading order as well as actual keyboard traversal through shadow roots.
  await expect(trigger).toHaveAttribute('aria-controls', /.+/);
  expect(await trigger.evaluate(el => {
    const mount = el.nextElementSibling;
    return mount?.hasAttribute('data-cpnav-editor-mount')
      && mount.id === el.getAttribute('aria-controls') && Boolean(mount.querySelector('pk-popover'));
  })).toBe(true);
  await page.keyboard.press('Tab');
  await expect(labelField(page)).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(trigger).toBeFocused();
  await page.keyboard.press('Tab');
}

for (const dismissal of ['Escape', 'Cancel', 'Save']) {
  test(`edits in sequential order and returns focus after ${dismissal}`, async ({ page }) => {
    const trigger = dashboardTrigger(page);
    await expect(trigger).toHaveRole('button');
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await trigger.focus();
    await page.keyboard.press('Enter');
    await expectAdjacentEditor(page, trigger);
    await labelField(page).fill('Keyboard dashboard');
    if (dismissal === 'Escape') {
      await page.keyboard.press('Escape');
    } else {
      const button = page.getByRole('button', { name: dismissal, exact: true });
      await button.focus();
      await page.keyboard.press('Enter');
    }
    await expect(labelField(page)).toHaveCount(0);
    await expect(trigger).toBeFocused();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await expect(trigger).toHaveText(dismissal === 'Save' ? 'Keyboard dashboard' : 'Dashboard');
  });
}

test('allows tabbing out and does not steal focus after pointer dismissal', async ({ page }) => {
  const trigger = dashboardTrigger(page);
  const actions = page.locator('[data-key="craft:dashboard"]').getByRole('button', { name: 'Actions for Dashboard', exact: true });
  await trigger.focus();
  await page.keyboard.press('Space');
  await expectAdjacentEditor(page, trigger);
  await page.getByRole('button', { name: 'Save', exact: true }).focus();
  await page.keyboard.press('Tab');
  await expect(actions).toBeFocused();
  await actions.click();
  await expect(labelField(page)).toHaveCount(0);
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await page.keyboard.press('Escape');
  await expect(actions).toBeFocused();
});

test('hands Actions → Edit over to the adjacent row editor', async ({ page }) => {
  const trigger = dashboardTrigger(page);
  const actions = page.locator('[data-key="craft:dashboard"]').getByRole('button', { name: 'Actions for Dashboard', exact: true });
  await actions.focus();
  await page.keyboard.press('Enter');
  const edit = page.getByRole('menuitem', { name: 'Edit', exact: true });
  await expect(edit).toBeVisible();
  await page.keyboard.press('ArrowDown');
  await expect(edit.getByRole('button', { name: 'Edit', exact: true })).toBeFocused();
  await page.keyboard.press('Enter');
  await expectAdjacentEditor(page, trigger);
  await page.keyboard.press('Escape');
  await expect(labelField(page)).toHaveCount(0);
  await expect(trigger).toBeFocused();
});

for (const type of ['manual', 'divider']) {
  test(`creates a ${type} in sequential order and restores a logical trigger`, async ({ page }) => {
    const trigger = createTrigger(page);
    if (type === 'manual') {
      await trigger.focus();
      await page.keyboard.press('Enter');
    } else {
      const more = page.getByRole('button', { name: 'More options', exact: true });
      await more.focus();
      await page.keyboard.press('Enter');
      await page.getByRole('menuitem', { name: 'New divider item', exact: true }).press('Enter');
    }
    await expect(labelField(page)).toBeFocused();
    await expect(page.getByRole('dialog', { name: 'New menu item', exact: true })).toBeVisible();
    await labelField(page).fill(`Keyboard ${type}`);
    if (type === 'manual') await page.locator('pk-input[name="url"] input').fill('https://example.test/keyboard');
    await page.getByRole('button', { name: 'Save', exact: true }).press('Enter');
    await expect(labelField(page)).toHaveCount(0);
    await expect(trigger).toBeFocused();
    const rowTrigger = page.locator('[data-tree-row]').getByRole('button', { name: `Keyboard ${type}`, exact: true });
    await rowTrigger.focus();
    await page.keyboard.press('Space');
    await expectAdjacentEditor(page, rowTrigger);
    await page.getByRole('button', { name: 'Cancel', exact: true }).press('Enter');
    await expect(labelField(page)).toHaveCount(0);
    await expect(rowTrigger).toBeFocused();
  });
}

test('keeps the editor open while Escape dismisses the nested icon picker', async ({ page }) => {
  const trigger = dashboardTrigger(page);
  await trigger.focus();
  await page.keyboard.press('Enter');
  await expectAdjacentEditor(page, trigger);
  const icon = page.locator('pk-image-browser').getByRole('button', { name: /Custom Icon/ });
  await page.keyboard.press('Tab');
  await expect(icon).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(icon).toHaveAttribute('aria-expanded', 'true');
  await page.keyboard.press('Escape');
  await expect(icon).toHaveAttribute('aria-expanded', 'false');
  await expect(labelField(page)).toBeVisible();
  await expect(icon).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(labelField(page)).toHaveCount(0);
  await expect(trigger).toBeFocused();
});
