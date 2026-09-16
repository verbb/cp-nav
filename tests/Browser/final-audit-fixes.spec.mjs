import { test, expect } from '@playwright/test';
import { login, addLink, rowFor } from './helpers.mjs';

const cpPath = url => url.searchParams.get('p') ?? url.pathname.replace(/^\//, '');

async function createLayout(page) {
  return page.evaluate(async () => {
    const response = await Craft.sendActionRequest('POST', 'cp-nav/layout/new', { data: { name: 'Audit owned layout' } });
    return response.data.layout.id;
  });
}

async function deleteLayout(page, id) {
  await page.evaluate(id => Craft.sendActionRequest('POST', 'cp-nav/layout/delete', { data: { id } }), id);
}

for (const destination of ['settings', 'layouts']) {
  test(`drains queued toggles before leaving for ${destination}`, async ({ page }) => {
    await login(page);
    const id = await createLayout(page);
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    let firstPersisted = false;
    let updateRequests = 0;
    try {
      await page.goto(`/admin/cp-nav?layoutId=${id}`);
      const toggle = page.locator('[data-key="craft:dashboard"]').getByRole('switch');
      await expect(toggle).toBeChecked();
      await page.route(url => decodeURIComponent(url.href).includes('cp-nav/api/update-node'), async route => {
        updateRequests++;
        const response = await route.fetch();
        firstPersisted = true;
        await gate;
        await route.fulfill({ response }).catch(() => {});
      });
      await toggle.click();
      await expect.poll(() => firstPersisted).toBe(true);
      await expect(toggle).not.toBeChecked();
      await toggle.click();
      await expect(toggle).toBeChecked();
      expect(updateRequests).toBe(1);
      await page.locator(`#tabs a[href$="/cp-nav/${destination}"]`).click();
      expect(cpPath(new URL(page.url()))).toBe('admin/cp-nav');
      release();
      await page.waitForURL(url => cpPath(url) === `admin/cp-nav/${destination}`);
      await page.unrouteAll({ behavior: 'wait' });
      await page.goto(`/admin/cp-nav?layoutId=${id}`);
      await expect(toggle).toBeChecked();
      expect(updateRequests).toBe(2);
    } finally {
      release();
      await page.unrouteAll({ behavior: 'wait' });
      await deleteLayout(page, id);
    }
  });
}

for (const mode of ['create', 'edit']) {
  for (const dismissal of ['Escape', 'outside click']) {
    test(`retains ${mode} layout draft after ${dismissal} during a rejected save`, async ({ page }) => {
      await login(page);
      const id = mode === 'edit' ? await createLayout(page) : null;
      let release;
      const gate = new Promise(resolve => { release = resolve; });
      let received = false;
      const pageErrors = [];
      page.on('pageerror', error => pageErrors.push(error.message));
      try {
        await page.goto('/admin/cp-nav/layouts');
        if (id) {
          await page.locator(`tr[data-id="${id}"] .edit-layout`).click();
        } else {
          await page.locator('.add-new-layout').click();
        }
        const hud = page.locator('.hud:visible');
        await hud.locator('input[name="name"]').fill('Recoverable audit draft');
        await hud.locator('label').filter({ hasText: /^\s*Browser editors\s*$/ }).click();
        const action = mode === 'edit' ? 'save' : 'new';
        await page.route(url => decodeURIComponent(url.href).includes(`cp-nav/layout/${action}`), async route => {
          received = true;
          await gate;
          await route.fulfill({ status: 400, contentType: 'application/json', body: JSON.stringify({ message: 'Audit layout save rejected' }) });
        });
        await hud.getByRole('button', { name: 'Save', exact: true }).click();
        await expect.poll(() => received).toBe(true);
        const rejected = page.waitForResponse(response => decodeURIComponent(response.url()).includes(`cp-nav/layout/${action}`) && response.request().method() === 'POST');
        if (dismissal === 'Escape') {
          await page.keyboard.press('Escape');
        } else {
          await page.locator('.hud-shade:visible').click({ position: { x: 5, y: 5 } });
        }
        await expect(hud).toBeVisible();
        release();
        await page.unrouteAll({ behavior: 'wait' });
        expect((await rejected).status()).toBe(400);
        await expect(hud.locator('input[name="name"]')).toHaveValue('Recoverable audit draft');
        await expect(hud.getByRole('checkbox', { name: 'Browser editors', exact: true })).toBeChecked();
        await expect(hud.getByRole('button', { name: 'Save', exact: true })).toBeEnabled();
        expect(pageErrors).toEqual([]);
        // Retry the same retained draft successfully, then clean up its saved layout.
        const saved = page.waitForResponse(response => decodeURIComponent(response.url()).includes(`cp-nav/layout/${action}`) && response.request().method() === 'POST');
        await hud.getByRole('button', { name: 'Save', exact: true }).click();
        const result = await (await saved).json();
        await expect(hud).toHaveCount(0);
        expect(result.layout.name).toBe('Recoverable audit draft');
        if (!id) await deleteLayout(page, result.layout.id);
      } finally {
        release();
        await page.unrouteAll({ behavior: 'wait' });
        if (id) await deleteLayout(page, id);
      }
    });
  }
}

test('settings entry, common tabs and settings save keep the expected destinations', async ({ page }) => {
  await login(page);
  await page.goto('/admin/settings/plugins/cp-nav');
  await page.waitForURL(url => cpPath(url) === 'admin/cp-nav');
  for (const label of ['Navigation', 'Layouts', 'Settings']) {
    await expect(page.locator('#tabs').getByRole('tab', { name: label, exact: true })).toBeVisible();
  }
  await page.locator('#tabs').getByRole('tab', { name: 'Settings', exact: true }).click();
  await page.waitForURL(url => cpPath(url) === 'admin/cp-nav/settings');
  const input = page.locator('input[name="settings[iconsPath]"]');
  const value = await input.inputValue();
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.locator('#notifications')).toContainText('saved');
  await expect(input).toHaveValue(value);
  expect(cpPath(new URL(page.url()))).toBe('admin/cp-nav/settings');
});

const requestIs = (response, action) => decodeURIComponent(response.url()).includes(`cp-nav/${action}`) && response.request().method() === 'POST';

test('reorder controls wait for deletion and then persist the remaining nodes', async ({ page }) => {
  await login(page);
  const id = await createLayout(page);
  let release;
  const gate = new Promise(resolve => { release = resolve; });
  let persistedDelete = false;
  try {
    await page.goto(`/admin/cp-nav?layoutId=${id}`);
    for (const name of ['Audit A', 'Audit B', 'Audit C']) await addLink(page, name);
    const aKey = await rowFor(page, 'Audit A').getAttribute('data-key');
    const bKey = await rowFor(page, 'Audit B').getAttribute('data-key');
    const cKey = await rowFor(page, 'Audit C').getAttribute('data-key');
    await page.route(url => decodeURIComponent(url.href).includes('cp-nav/api/delete-node'), async route => {
      const response = await route.fetch();
      persistedDelete = true;
      await gate;
      await route.fulfill({ response });
    });
    await rowFor(page, 'Audit A').getByRole('button', { name: 'Actions for Audit A', exact: true }).click();
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('menuitem', { name: 'Delete', exact: true }).click();
    await expect.poll(() => persistedDelete).toBe(true);
    const moveButton = rowFor(page, 'Audit C').getByRole('button', { name: 'Actions for Audit C', exact: true });
    await expect(moveButton).toBeDisabled();
    release();
    await page.unrouteAll({ behavior: 'wait' });
    await expect(rowFor(page, 'Audit A')).toHaveCount(0);
    await expect(moveButton).toBeEnabled();
    await moveButton.click();
    const reordered = page.waitForResponse(response => requestIs(response, 'api/reorder-nodes'));
    await page.getByRole('menuitem', { name: 'Move up', exact: true }).click();
    const response = await reordered;
    expect(response.status()).toBe(200);
    expect(JSON.parse(response.request().postData()).items.some(item => item.key === aKey)).toBe(false);
    await page.reload();
    const keys = () => page.locator('[data-tree-row]').evaluateAll(rows => rows.map(row => row.dataset.key));
    await expect.poll(async () => (await keys()).filter(key => [bKey, cKey].includes(key))).toEqual([cKey, bKey]);
  } finally {
    release();
    await page.unrouteAll({ behavior: 'wait' });
    await deleteLayout(page, id);
  }
});

test('layout controls support keyboard create edit reorder duplicate and delete', async ({ page }) => {
  await login(page);
  let id;
  let copyId;
  try {
    await page.goto('/admin/cp-nav/layouts');
    await page.locator('.add-new-layout').focus();
    await page.keyboard.press('Enter');
    const hud = page.locator('.hud:visible');
    await hud.locator('input[name="name"]').fill('Keyboard audit layout');
    await hud.getByRole('button', { name: 'Save', exact: true }).focus();
    const created = page.waitForResponse(response => requestIs(response, 'layout/new'));
    await page.keyboard.press('Enter');
    id = (await (await created).json()).layout.id;
    const row = page.locator(`tr[data-id="${id}"]`);
    await expect(hud).toHaveCount(0);
    await row.locator('.edit-layout').focus();
    await page.keyboard.press('Enter');
    await hud.locator('input[name="name"]').fill('Renamed keyboard layout');
    await hud.getByRole('button', { name: 'Save', exact: true }).focus();
    await page.keyboard.press('Enter');
    await expect(row).toHaveAttribute('data-name', 'Renamed keyboard layout');
    await row.locator('.move').focus();
    const previous = await page.locator('#layoutItems tbody tr').evaluateAll(rows => rows.map(row => row.dataset.id));
    const reordered = page.waitForResponse(response => requestIs(response, 'layout/reorder'));
    await page.keyboard.press('ArrowUp');
    expect((await reordered).status()).toBe(200);
    const expected = [...previous];
    const index = expected.indexOf(String(id));
    [expected[index - 1], expected[index]] = [expected[index], expected[index - 1]];
    await expect(row.locator('.move')).toBeFocused();
    await page.reload();
    expect(await page.locator('#layoutItems tbody tr').evaluateAll(rows => rows.map(row => row.dataset.id))).toEqual(expected);
    await row.locator('.duplicate').focus();
    const duplicated = page.waitForResponse(response => requestIs(response, 'layout/duplicate'));
    await page.keyboard.press('Enter');
    copyId = (await (await duplicated).json()).layout.id;
    const copy = page.locator(`tr[data-id="${copyId}"]`);
    await copy.locator('.duplicate').focus();
    await page.keyboard.press('Tab');
    await expect(copy.locator('.delete')).toBeFocused();
    page.once('dialog', dialog => dialog.accept());
    await page.keyboard.press('Enter');
    await expect(copy).toHaveCount(0);
    copyId = null;
  } finally {
    if (id) await deleteLayout(page, id);
    if (copyId) await deleteLayout(page, copyId);
  }
});

test('a native page moved under another root keeps its active sidebar state', async ({ page }) => {
  await login(page);
  const id = await createLayout(page);
  try {
    await page.goto(`/admin/users?layoutId=${id}`);
    await expect(page.getByRole('navigation', { name: 'Primary', exact: true }).getByRole('link', { name: 'Users', exact: true })).toHaveAttribute('aria-current', /^(true|page)$/);
    await page.goto(`/admin/cp-nav?layoutId=${id}`);
    await page.evaluate(async layoutId => {
      await Craft.sendActionRequest('POST', 'cp-nav/api/reparent-node', {
        data: { layoutId, key: 'craft:users', parentKey: 'craft:dashboard' },
      });
    }, id);
    await page.goto(`/admin/users?layoutId=${id}`);
    const sidebar = page.getByRole('navigation', { name: 'Primary', exact: true });
    const dashboard = sidebar.locator('#nav-dashboard');
    await expect(dashboard.getByRole('button', { name: 'Open subnavigation', exact: true })).toHaveAttribute('aria-expanded', 'true');
    await expect(dashboard.locator('#nav-dashboard-subnav')).toHaveAttribute('data-state', 'expanded');
    await expect(dashboard.locator('#nav-dashboard-subnav a').filter({ hasText: 'Users' })).toHaveAttribute('aria-current', 'page');
    await expect(dashboard.locator('#nav-dashboard-subnav a').filter({ hasText: 'Users' })).toHaveCount(1);
  } finally {
    await deleteLayout(page, id);
  }
});

test('reload warns while writes are queued and a rejected save cancels tab navigation', async ({ page }) => {
  await login(page);
  const id = await createLayout(page);
  let release;
  const gate = new Promise(resolve => { release = resolve; });
  let received = false;
  try {
    await page.goto(`/admin/cp-nav?layoutId=${id}`);
    const toggle = page.locator('[data-key="craft:dashboard"]').getByRole('switch');
    await expect(toggle).toBeChecked();
    await page.route(url => decodeURIComponent(url.href).includes('cp-nav/api/update-node'), async route => {
      received = true;
      await gate;
      await route.fulfill({ status: 400, contentType: 'application/json', body: JSON.stringify({ message: 'Save rejected for regression test' }) });
    });
    await toggle.click();
    await expect.poll(() => received).toBe(true);
    const warning = page.waitForEvent('dialog');
    // A dismissed reload has no load event for Playwright’s page.reload() to await.
    await page.evaluate(() => { setTimeout(() => window.location.reload(), 0); });
    const dialog = await warning;
    expect(dialog.type()).toBe('beforeunload');
    await dialog.dismiss();
    await page.locator('#tabs').getByRole('tab', { name: 'Settings', exact: true }).click();
    const rejected = page.waitForResponse(response => requestIs(response, 'api/update-node'));
    release();
    expect((await rejected).status()).toBe(400);
    await page.unrouteAll({ behavior: 'wait' });
    await expect(page.locator('#notifications')).toContainText('Couldn’t leave this page');
    expect(cpPath(new URL(page.url()))).toBe('admin/cp-nav');
    await expect(toggle).toBeChecked();
    await page.locator('#tabs').getByRole('tab', { name: 'Settings', exact: true }).click();
    await page.waitForURL(url => cpPath(url) === 'admin/cp-nav/settings');
  } finally {
    release();
    await page.unrouteAll({ behavior: 'wait' });
    await deleteLayout(page, id);
  }
});
