import { test, expect } from '@playwright/test';

async function login(page) {
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: 'Username or Email' }).fill('admin');
    await page.getByRole('textbox', { name: 'Password', exact: true }).fill('testing-only-password');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    await page.goto('/admin/cp-nav');
    await expect(page.locator('[data-key="craft:dashboard"]')).toBeVisible();
    return page.evaluate(() => window.CpNavBuilderConfig);
}

const mutations = ['update-node', 'create-node', 'delete-node', 'reorder-nodes', 'indent-node', 'outdent-node', 'reparent-node', 'refresh-sources', 'acknowledge-new-items', 'reset-layout'];
const endpoint = action => `/actions/cp-nav/api/${action}`;

test('contains SVG scripts and rejects filesystem traversal over HTTP', async ({ page }) => {
    await login(page);
    const catalogResponse = await page.request.get('/admin/actions/cp-nav/static-icons/index', { headers: { Accept: 'application/json' } });
    expect(catalogResponse.status()).toBe(200);
    const catalog = await catalogResponse.json();
    const url = catalog.options.find(option => option.value === 'script-probe.svg').url;
    const response = await page.goto(url);
    expect(response.status()).toBe(200);
    expect(await page.evaluate(() => window.__cpnavSvgScript)).toBeUndefined();
    for (const file of ['../config/general.php', '..\\config\\general.php', '%2e%2e/general.php', 'missing.svg']) {
        const target = new URL(url);
        target.searchParams.set('file', file);
        expect((await page.request.get(target.href)).status()).toBe(404);
    }
});

test('rejects unauthenticated requests and enforces CSRF and methods over HTTP', async ({ page, playwright }) => {
    const anonymous = await playwright.request.newContext({ baseURL: 'https://cp-nav-react-tests.ddev.site', ignoreHTTPSErrors: true });
    try {
        for (const action of ['layout-tree', ...mutations]) {
            const response = await anonymous.post(endpoint(action), { headers: { Accept: 'application/json' }, data: { layoutId: 1 } });
            expect(response.status(), `anonymous ${action}`).toBeGreaterThanOrEqual(400);
            expect(response.status()).toBeLessThan(500);
        }
    } finally {
        await anonymous.dispose();
    }
    const config = await login(page);
    const before = await (await page.request.get(endpoint('layout-tree'), { params: { layoutId: config.layoutId }, headers: { Accept: 'application/json' } })).json();
    for (const action of mutations) {
        const response = await page.request.post(endpoint(action), { headers: { Accept: 'application/json' }, data: { layoutId: config.layoutId, [config.csrfTokenName]: 'invalid' } });
        expect(response.status(), `CSRF ${action}`).toBe(400);
        const get = await page.request.get(endpoint(action), { headers: { Accept: 'application/json' }, params: { layoutId: config.layoutId } });
        expect(get.status(), `GET ${action}`).toBe(405);
    }
    const after = await (await page.request.get(endpoint('layout-tree'), { params: { layoutId: config.layoutId }, headers: { Accept: 'application/json' } })).json();
    expect(after).toEqual(before);
});

test('keeps hostile labels inert in the builder and the rendered Craft sidebar', async ({ page }) => {
    const config = await login(page);
    const layouts = await page.locator('#cpnav-builder-app').getAttribute('data-layouts');
    const layoutId = JSON.parse(layouts).find(layout => layout.name === 'Browser managers').id;
    const label = '<img src=x onerror="window.__cpnavXss=1">';
    const post = (action, data) => page.request.post(endpoint(action), { headers: { Accept: 'application/json' }, data: { ...data, layoutId, [config.csrfTokenName]: config.csrfTokenValue } });
    const created = await post('create-node', { data: { type: 'manual', currLabel: label, url: 'dashboard' } });
    expect(created.status()).toBe(200);
    const key = (await created.json()).node.key;
    try {
        await page.goto(`/admin/cp-nav?layoutId=${layoutId}`);
        await expect(page.locator(`[data-key="${key}"]`)).toContainText(label);
        expect(await page.evaluate(() => window.__cpnavXss)).toBeUndefined();
        await page.goto(`/admin/dashboard?layoutId=${layoutId}`);
        await expect(page.getByRole('navigation', { name: 'Primary', exact: true })).toContainText(label);
        expect(await page.evaluate(() => window.__cpnavXss)).toBeUndefined();
    } finally {
        expect((await post('delete-node', { key })).status()).toBe(200);
    }
});

test('measures browser rendering and editor interaction for synthetic large trees', async ({ page }, testInfo) => {
    await login(page);
    await page.addInitScript(() => {
        document.addEventListener('click', event => {
            if (!event.composedPath().some(node => node instanceof Element && node.hasAttribute('data-cpnav-editor-anchor'))) return;
            const start = performance.now();
            const measure = () => {
                const input = document.querySelector('#cpnav-builder-app')?.shadowRoot?.querySelector('pk-input[name="currLabel"]');
                if (input?.getBoundingClientRect().width) {
                    requestAnimationFrame(() => { window.__cpnavEditorPaintMs = performance.now() - start; });
                } else if (performance.now() - start < 10000) {
                    requestAnimationFrame(measure);
                }
            };
            requestAnimationFrame(measure);
        }, true);
    });
    const results = [];
    for (const count of [50, 250, 1000]) {
        await page.route(url => decodeURIComponent(url.href).includes('cp-nav/api/layout-tree'), async route => {
            const response = await route.fetch();
            const tree = await response.json();
            const prototype = tree.nodes.find(node => node.key === 'craft:dashboard');
            tree.nodes = Array.from({ length: count }, (_, index) => ({ ...prototype, key: `craft:audit-${index}`, builderId: index + 1, label: `Audit ${index}`, title: `Audit ${index}`, defaultLabel: `Audit ${index}`, hasDescendants: false, parentKey: null, parentId: null, level: 1, sort: index * 10 }));
            await route.fulfill({ response, json: tree });
        });
        const started = Date.now();
        await page.reload();
        await expect(page.locator('[data-tree-row]')).toHaveCount(count);
        await expect(page.locator('[data-key="craft:audit-0"]')).toBeVisible();
        const readyMs = Date.now() - started;
        const editStarted = Date.now();
        await page.locator('[data-key="craft:audit-0"] [data-cpnav-editor-anchor]').click();
        await expect(page.locator('pk-input[name="currLabel"] input')).toBeVisible();
        const editorMs = Date.now() - editStarted;
        await expect.poll(() => page.evaluate(() => window.__cpnavEditorPaintMs)).toBeGreaterThan(0);
        const editorPaintMs = await page.evaluate(() => window.__cpnavEditorPaintMs);
        await page.getByRole('button', { name: 'Cancel', exact: true }).click();
        if (count === 50) {
            const trigger = page.getByRole('button', { name: 'Actions for Audit 0', exact: true });
            await trigger.focus();
            await trigger.press('Enter');
            const edit = page.getByRole('menuitem', { name: 'Edit', exact: true });
            await expect(edit).toBeVisible();
            await edit.click();
            await expect(page.locator('pk-input[name="currLabel"] input')).toBeVisible();
            await page.getByRole('button', { name: 'Cancel', exact: true }).click();
        }
        results.push({ count, readyMs, editorMs, editorPaintMs, ...await page.evaluate(() => ({ domContentLoadedMs: performance.getEntriesByType('navigation')[0].domContentLoadedEventEnd })) });
        await page.unrouteAll({ behavior: 'wait' });
    }
    await testInfo.attach('browser-performance.json', { body: JSON.stringify(results, null, 2), contentType: 'application/json' });
    console.log('Browser audit timings:', JSON.stringify(results));
});
