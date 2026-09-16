import assert from 'node:assert/strict';
import { test } from 'node:test';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import ts from 'typescript';

const root = fileURLToPath(new URL('../../', import.meta.url));
const sourceRoot = join(root, 'src/web/assets/builder/src');
const flush = () => new Promise(setImmediate);

async function fixture(t, key = 'craft:dashboard', initialNodes = null) {
    mkdirSync(join(root, '.cache'), { recursive: true });
    const output = mkdtempSync(join(root, '.cache/store-tests-'));
    t.after(() => rmSync(output, { recursive: true, force: true }));
    // Transpile the actual store and its local imports using the existing TypeScript dependency.
    // Each fixture gets fresh module state; no copied implementation or new test runtime is needed.
    const compiled = new Set();
    function compile(file) {
        if (compiled.has(file)) return;
        compiled.add(file);
        const result = ts.transpileModule(readFileSync(file, 'utf8'), {
            compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
        });
        const code = result.outputText.replace(/from (['"])(\.[^'"]+)\1/g, (_match, quote, path) => {
            compile(resolve(dirname(file), path + '.ts'));
            return `from ${quote}${path}.js${quote}`;
        });
        const destination = join(output, relative(sourceRoot, file).replace(/\.ts$/, '.js'));
        mkdirSync(dirname(destination), { recursive: true });
        writeFileSync(destination, code);
    }
    compile(join(sourceRoot, 'store.ts'));
    const pending = [];
    let serverNodes = initialNodes ?? [{ key, enabled: true, parentKey: null, level: 1, builderId: 1 }];
    const tree = () => ({ layout: { id: 1, maxLevels: 2 }, nodes: structuredClone(serverNodes), meta: {} });
    globalThis.window = { Craft: {
        t: (_category, message) => message,
        getUrl: path => path,
        cp: { displayNotice() {}, displayError() {} },
        sendActionRequest: (_method, action, options) => new Promise((resolve, reject) => {
            pending.push({ action, data: options.data, resolve, reject });
        }),
    } };
    const { useBuilderStore: store } = await import(pathToFileURL(join(output, 'store.js')));
    const api = await import(pathToFileURL(join(output, 'api.js')));
    window.location = { href: '' };
    store.setState({ layoutId: 1, loading: false, nodes: tree().nodes });
    async function succeed() {
        const request = pending.shift();
        assert.ok(request, 'expected an in-flight request');
        if (request.action.endsWith('/update-node')) {
            serverNodes = serverNodes.map(node => node.key === request.data.key ? { ...node, ...request.data.data } : node);
        }
        if (request.action.endsWith('/reorder-nodes')) {
            const byKey = new Map(serverNodes.map(node => [node.key, node]));
            assert.equal(request.data.items.length, serverNodes.length);
            serverNodes = request.data.items.map(item => ({ ...byKey.get(item.key), parentKey: item.parentKey, level: item.parentKey ? 2 : 1 }));
        }
        if (request.action.endsWith('/delete-node')) {
            serverNodes = serverNodes.filter(node => node.key !== request.data.key);
        }
        request.resolve({ data: request.action.endsWith('/layout-tree') ? tree() : { tree: tree() } });
        await flush();
        return request;
    }
    async function fail() {
        const request = pending.shift();
        assert.ok(request, 'expected an in-flight request');
        request.reject(new Error('Request failed'));
        await flush();
    }
    return { store, api, pending, tree, succeed, fail };
}

test('rapid toggles persist in intent order, including writes queued after a failure', async t => {
    const { store, pending, tree, succeed, fail } = await fixture(t);
    const first = store.getState().toggleEnabled('craft:dashboard', false);
    const second = store.getState().toggleEnabled('craft:dashboard', true);
    assert.equal(pending.length, 1, 'newer write waits for the older write');
    await fail();
    assert.equal(pending.length, 1);
    assert.equal(pending[0].data.data.enabled, true);
    await succeed();
    await Promise.all([first, second]);
    assert.equal(tree().nodes[0].enabled, true);
    assert.equal(store.getState().nodes[0].enabled, true);

    const third = store.getState().toggleEnabled('craft:dashboard', false);
    const fourth = store.getState().toggleEnabled('craft:dashboard', true);
    assert.equal(pending.length, 1);
    await succeed();
    assert.equal(pending[0].data.data.enabled, true);
    await succeed();
    await Promise.all([third, fourth]);
    assert.equal(tree().nodes[0].enabled, store.getState().nodes[0].enabled);
});

const orderedFixture = () => [
    { key: 'manual:a', parentKey: null, enabled: true, level: 1, builderId: 11 },
    { key: 'manual:child', parentKey: 'manual:a', enabled: true, level: 2, builderId: 12 },
    { key: 'manual:b', parentKey: null, enabled: true, level: 1, builderId: 13 },
    { key: 'manual:c', parentKey: null, enabled: true, level: 1, builderId: 14 },
];

test('reorder persists exact canonical identities and parent changes', async t => {
    const { store, pending, succeed, tree } = await fixture(t, undefined, orderedFixture());
    const [a, child, b, c] = store.getState().nodes;
    const moved = [c, b, { ...child, parentKey: b.key, level: 2 }, a];
    store.getState().setNodes(moved);
    assert.deepEqual(pending[0].data, { layoutId: 1, items: [
        { key: 'manual:c', parentKey: null },
        { key: 'manual:b', parentKey: null },
        { key: 'manual:child', parentKey: 'manual:b' },
        { key: 'manual:a', parentKey: null },
    ] });
    await succeed();
    assert.deepEqual(tree().nodes, moved);
    assert.deepEqual(store.getState().nodes, moved);
    const reload = store.getState().refresh();
    await flush();
    await succeed();
    await reload;
    assert.deepEqual(store.getState().nodes, moved);
});

test('failed reorder restores saved order and parentage and clears busy state', async t => {
    const original = orderedFixture();
    const { store, pending, succeed, fail } = await fixture(t, undefined, original);
    const moved = [...original].reverse().map(node => ({ ...node, parentKey: null, level: 1 }));
    store.getState().setNodes(moved);
    assert.deepEqual(store.getState().nodes.map(({ key, parentKey }) => ({ key, parentKey })), moved.map(({ key, parentKey }) => ({ key, parentKey })));
    assert.equal(store.getState().reordering, true);
    await fail();
    assert.ok(pending[0].action.endsWith('/layout-tree'));
    await succeed();
    assert.deepEqual(store.getState().nodes, original);
    assert.equal(pending.length, 0);
    assert.equal(store.getState().reordering, false);
});

test('an older reorder cannot clear a newer pending reorder', async t => {
    const { store, pending, succeed } = await fixture(t);
    store.getState().setNodes(store.getState().nodes);
    store.getState().setNodes(store.getState().nodes);
    assert.equal(pending.length, 1);
    await succeed();
    assert.equal(store.getState().reordering, true);
    await succeed();
    assert.equal(store.getState().reordering, false);
});

test('failed toggles reload saved state instead of rolling back to a failed optimistic value', async t => {
    const { store, pending, tree, succeed, fail } = await fixture(t);
    const first = store.getState().toggleEnabled('craft:dashboard', false);
    const second = store.getState().toggleEnabled('craft:dashboard', true);
    await fail();
    await fail();
    assert.ok(pending[0].action.endsWith('/layout-tree'));
    await succeed();
    await Promise.all([first, second]);
    assert.equal(store.getState().nodes[0].enabled, tree().nodes[0].enabled);
});

test('failure from a previous layout cannot roll back or refresh the new layout', async t => {
    const { store, pending, fail } = await fixture(t);
    const first = store.getState().toggleEnabled('craft:dashboard', false);
    store.setState({ layoutId: 2, nodes: [{ key: 'craft:dashboard', enabled: false }] });
    await fail();
    await first;
    assert.equal(store.getState().nodes[0].enabled, false);
    assert.equal(pending.length, 0);
});

test('reset busy state settles even if a refresh supersedes its response', async t => {
    const { store, pending, succeed } = await fixture(t);
    const reset = store.getState().resetLayout();
    const refresh = store.getState().refresh();
    assert.equal(pending.length, 1, 'refresh waits for the queued write');
    await succeed();
    await reset;
    assert.equal(store.getState().resettingLayout, false);
    await succeed();
    await refresh;
});

test('reorder responses do not replace a newer optimistic toggle', async t => {
    const { store, pending, succeed } = await fixture(t);
    store.getState().setNodes(store.getState().nodes);
    const toggle = store.getState().toggleEnabled('craft:dashboard', false);
    assert.equal(pending.length, 1);
    await succeed();
    assert.equal(store.getState().nodes[0].enabled, false);
    await succeed();
    await toggle;
    assert.equal(store.getState().nodes[0].enabled, false);
    assert.equal(store.getState().reordering, false);
});

for (const action of ['updateNode', 'createNode', 'deleteNode', 'resetLayout', 'acknowledgeNewItems']) {
    test(`${action} waits until deletion settles before accepting another mutation`, async t => {
        const key = 'manual:audit';
        const { store, pending, tree, succeed, fail } = await fixture(t, key);
        const deletion = store.getState().deleteNode(key);
        const args = {
            updateNode: [key, { currLabel: 'Renamed' }],
            createNode: [{ type: 'manual', currLabel: 'Docs', url: 'https://example.test' }],
            deleteNode: [key],
            resetLayout: [],
            acknowledgeNewItems: [],
        };
        await store.getState()[action](...args[action]);
        assert.equal(pending.length, 1, 'only the deletion is queued');
        assert.equal(store.getState().changingNodeSet, true);
        await succeed();
        await deletion;
        assert.equal(store.getState().changingNodeSet, false);
        const later = store.getState()[action](...args[action]);
        await fail();
        assert.ok(pending[0]?.action.endsWith('/layout-tree'), 'reload after a failed superseding mutation');
        await succeed();
        await later;
        assert.deepEqual(store.getState().nodes, tree().nodes);
        assert.equal(store.getState().resettingLayout, false);
    });
}

test('layout navigation waits for all saves, including edits added while waiting', async t => {
    const { store, api, succeed } = await fixture(t);
    const first = store.getState().toggleEnabled('craft:dashboard', false);
    const second = store.getState().toggleEnabled('craft:dashboard', true);
    const navigation = api.navigateToLayout(1, 2);
    assert.equal(window.location.href, '');
    await succeed();
    const third = store.getState().toggleEnabled('craft:dashboard', false);
    await succeed();
    assert.equal(window.location.href, '');
    await succeed();
    assert.equal(await navigation, true);
    await Promise.all([first, second, third]);
    assert.equal(window.location.href, 'cp-nav?layoutId=2');
});

test('a failed save cancels layout navigation without poisoning a later switch', async t => {
    const { store, api, succeed, fail } = await fixture(t);
    const first = store.getState().toggleEnabled('craft:dashboard', false);
    const second = store.getState().toggleEnabled('craft:dashboard', true);
    const navigation = api.navigateToLayout(1, 2);
    await fail();
    await succeed();
    await Promise.all([first, second]);
    assert.equal(await navigation, false);
    assert.equal(window.location.href, '');
    assert.equal(await api.navigateToLayout(1, 2), true);
    assert.equal(window.location.href, 'cp-nav?layoutId=2');
});

test('completion of an older editor session cannot close a newer draft', async t => {
    const { store } = await fixture(t);
    store.getState().openCreateEditor('manual');
    const savingSession = store.getState().editorSession;
    store.getState().openEditEditor('craft:dashboard');
    const newerSession = store.getState().editorSession;
    store.getState().closeEditor(savingSession);
    assert.equal(store.getState().editorSession, newerSession);
    store.getState().closeEditor(newerSession);
    assert.equal(store.getState().editorSession, null);
});

test('deleting an edited item does not close a different draft opened while waiting', async t => {
    const { store, succeed } = await fixture(t, 'manual:delete');
    store.getState().openEditEditor('manual:delete');
    const deletion = store.getState().deleteNode('manual:delete');
    store.getState().openCreateEditor('divider');
    const newerSession = store.getState().editorSession;
    await succeed();
    await deletion;
    assert.equal(store.getState().editorSession, newerSession);
});

test('reorder cannot submit a stale node set while deletion is pending', async t => {
    const original = orderedFixture().filter(node => node.parentKey === null);
    const { store, pending, tree, succeed } = await fixture(t, undefined, original);
    const deletion = store.getState().deleteNode('manual:a');
    store.getState().moveNodeUp('manual:c');
    assert.equal(pending.length, 1);
    assert.deepEqual(store.getState().nodes, original);
    await succeed();
    await deletion;
    store.getState().moveNodeUp('manual:c');
    assert.deepEqual(pending[0].data.items.map(item => item.key), ['manual:c', 'manual:b']);
    await succeed();
    assert.deepEqual(tree().nodes.map(node => node.key), ['manual:c', 'manual:b']);
});

test('a refresh started during deletion cannot unlock reordering with stale nodes', async t => {
    const original = orderedFixture().filter(node => node.parentKey === null);
    const { store, pending, succeed } = await fixture(t, undefined, original);
    const deletion = store.getState().deleteNode('manual:a');
    const refresh = store.getState().refresh();
    await succeed();
    await deletion;
    assert.equal(store.getState().changingNodeSet, false);
    assert.deepEqual(store.getState().nodes.map(node => node.key), ['manual:b', 'manual:c']);
    store.getState().moveNodeUp('manual:c');
    const reorder = pending.find(request => request.action.endsWith('/reorder-nodes'));
    assert.deepEqual(reorder.data.items.map(item => item.key), ['manual:c', 'manual:b']);
    await succeed();
    await refresh;
    await succeed();
});
