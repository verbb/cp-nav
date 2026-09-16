import { test, expect } from '@playwright/test';
import { login } from './helpers.mjs';

test('releases temporary previews after repeated cancelled drags', async ({ page }) => {
  await login(page);
  await page.goto('/admin/cp-nav');
  const row = page.locator('[data-key="craft:dashboard"]');
  await expect(row).toBeVisible();
  const previewCount = () => page.evaluate(() => [...document.body.children].filter(element => (
    element.style.position === 'fixed' && element.style.top === '-9999px'
    && element.style.width === '1px' && element.style.height === '1px'
  )).length);
  const before = await previewCount();

  for (let attempt = 0; attempt < 12; attempt++) {
    const handle = row.getByRole('button', { name: 'Drag to reorder', exact: true });
    await handle.scrollIntoViewIfNeeded();
    const box = await handle.boundingBox();
    const x = box.x + box.width / 2;
    const y = box.y + box.height / 2;
    await page.mouse.move(x, y);
    await page.mouse.down();
    await page.mouse.move(x + 18, y + 8, { steps: 5 });
    await page.keyboard.press('Escape');
    await page.mouse.up();
  }

  await expect.poll(previewCount).toBe(before);
});
