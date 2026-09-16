import { expect } from '@playwright/test';

export async function login(page) {
  await page.goto('/admin/login');
  await page.getByRole('textbox', { name: 'Username or Email' }).fill('admin');
  await page.getByRole('textbox', { name: 'Password', exact: true }).fill('testing-only-password');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL(url => !url.pathname.endsWith('/login'));
}

export const rowFor = (page, name) => page.locator('[data-tree-row]').filter({ has: page.getByRole('link', { name, exact: true }) });
export const waitAction = (page, action) => page.waitForResponse(response => decodeURIComponent(response.url()).includes(`cp-nav/${action}`) && response.request().method() === 'POST');

export async function switchLayout(page, name) {
  await page.locator('#cpnav-builder-toolbar').getByRole('button').click();
  await page.getByRole('menuitemradio', { name, exact: true }).click();
  await expect(page.locator('#cpnav-builder-toolbar')).toContainText(name);
}

export async function addLink(page, name) {
  await page.getByRole('button', { name: 'New menu item', exact: true }).click();
  await page.locator('pk-input[name="currLabel"] input').fill(name);
  await page.locator('pk-input[name="url"] input').fill('https://example.test/' + name.toLowerCase().replaceAll(' ', '-'));
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(rowFor(page, name)).toBeVisible();
}
