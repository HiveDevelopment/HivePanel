const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');

function bridge(fetch, props = {}) {
    let source = fs.readFileSync(path.resolve(__dirname, '../../resources/js/components/nectar/NectarBridgeFrame.vue'), 'utf8').match(/<script setup lang="ts">([\s\S]*?)<\/script>/)[1];
    source = source.replace(/^import .*;\n/gm, '');
    source += '\n;globalThis.bridge = { frame, handleMessage, nonce, subscriptions, contentHeight };';
    const sent = [], cleanup = [], timers = new Set();
    const context = vm.createContext({
        defineProps: () => ({ extensionId: 'test.example', ...props }), ref: value => ({ value }),
        onMounted: () => {}, onBeforeUnmount: fn => cleanup.push(fn),
        extensionTheme: () => ({}), extensionMessages: () => ({}), locale: { value: 'en' },
        setTimeout, clearTimeout, AbortController,
        crypto: webcrypto, document: { hidden: false, querySelector: () => ({ content: 'csrf' }) },
        window: { addEventListener() {}, removeEventListener() {} }, fetch,
        setInterval: fn => { timers.add(fn); return fn; }, clearInterval: fn => timers.delete(fn),
    });
    vm.runInContext(ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.None } }).outputText, context);
    const frame = { postMessage: value => sent.push(value) };
    context.bridge.frame.value = { contentWindow: frame };
    return { ...context.bridge, sent, cleanup: () => cleanup.forEach(fn => fn()),
        message: (data, source = frame, origin = 'null') => context.bridge.handleMessage({ data: { nectar: 'v1', nonce: context.bridge.nonce, ...data }, source, origin }) };
}
test('SDK event subscription is acknowledged after server authorization and empty subscriptions stop polling', async () => {
    const calls = [];
    const host = bridge(async (url, options) => {
        calls.push({ url, options });
        return { ok: true, status: 200, json: async () => url.endsWith('/events') ? { ok: true } : { cells: [] } };
    });
    try {
        await host.message({ type: 'request', requestId: 'one', method: 'events.subscribe', params: { events: ['cell.updated'] } });
        assert.ok(calls[0].url.endsWith('/events'));
        assert.equal(calls[0].options.headers['X-CSRF-TOKEN'], 'csrf');
        assert.equal(host.sent.at(-1).ok, true);
        assert.equal(host.subscriptions.has('cell.updated'), true);
        await host.message({ type: 'request', requestId: 'two', method: 'events.subscribe', params: { events: [] } });
        assert.equal(host.subscriptions.size, 0);
    } finally { host.cleanup(); }
});
test('denials include HTTP status and do not activate subscriptions', async () => {
    const host = bridge(async () => ({ ok: false, status: 403, json: async () => ({ message: 'Not declared' }) }));
    await host.message({ type: 'request', requestId: 'one', method: 'events.subscribe', params: { events: ['cell.updated'] } });
    assert.equal(host.sent.at(-1).status, 403);
    assert.equal(host.sent.at(-1).ok, false);
    assert.equal(host.subscriptions.size, 0);
    host.cleanup();
});
test('forged source, origin and nonce cannot invoke a capability', async () => {
    let calls = 0;
    const host = bridge(async () => { calls++; throw Error('Unexpected'); });
    const request = { type: 'request', requestId: 'one', method: 'cells.list' };
    await host.message(request, {});
    await host.message(request, undefined, 'https://evil.test');
    await host.message({ ...request, nonce: 'wrong' });
    assert.equal(calls, 0); assert.equal(host.sent.length, 0);
    host.cleanup();
});

test('legacy extension handshake and cells.list keep their original response shape', async () => {
    const host = bridge(async () => ({ ok: true, status: 200, json: async () => ({ cells: [{ id: 'one', name: 'Example' }] }) }));
    await host.message({ type: 'ready' });
    assert.equal(host.sent.at(-1).type, 'init');
    assert.equal(host.sent.at(-1).nonce, host.nonce);
    await host.message({ type: 'request', requestId: 'legacy-1', method: 'cells.list' });
    assert.equal(host.sent.at(-1).data.cells[0].id, 'one');
    assert.equal(host.sent.at(-1).ok, true);
    host.cleanup();
});

test('legacy subscribe messages still work and can clear their event set', async () => {
    const host = bridge(async (url) => ({ ok: true, status: 200, json: async () => url.endsWith('/events') ? { ok: true } : { cells: [] } }));
    await host.message({ type: 'subscribe', events: ['cell.updated'] });
    assert.equal(host.subscriptions.has('cell.updated'), true);
    await host.message({ type: 'subscribe', events: [] });
    assert.equal(host.subscriptions.size, 0);
    host.cleanup();
});

test('closing a frame aborts its requests and ignores late subscription responses', async () => {
    let finish;
    let signal;
    const host = bridge((url, options) => {
        signal = options.signal;
        return new Promise(resolve => { finish = resolve; });
    });
    const pending = host.message({ type: 'request', requestId: 'late', method: 'events.subscribe', params: { events: ['cell.updated'] } });
    host.cleanup();
    assert.equal(signal.aborted, true);
    finish({ ok: true, status: 200, json: async () => ({ ok: true }) });
    await pending;
    assert.equal(host.subscriptions.size, 0);
    assert.equal(host.sent.length, 0);
});

test('content resize is authenticated, bounded and opt-in for full pages', async () => {
    const host = bridge(async () => { throw Error('Resize must not fetch'); }, { autoHeight: true });
    await host.message({ type: 'resize', height: 350, nonce: 'incorrect' });
    assert.equal(host.contentHeight.value, 480);
    await host.message({ type: 'resize', height: 350.5 });
    assert.equal(host.contentHeight.value, 351);
    await host.message({ type: 'resize', height: 9999999 });
    assert.equal(host.contentHeight.value, 12000);
    await host.message({ type: 'resize', height: NaN });
    assert.equal(host.contentHeight.value, 12000);
    await host.message({ type: 'resize', height: 1 });
    assert.equal(host.contentHeight.value, 80);
    host.cleanup();
    const widget = bridge(async () => { throw Error('Unexpected fetch'); });
    await widget.message({ type: 'resize', height: 100 });
    assert.equal(widget.contentHeight.value, 480);
    widget.cleanup();
});
