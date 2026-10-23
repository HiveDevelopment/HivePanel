const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const Module = require('node:module');
const ts = require('typescript');
const source = path.resolve(__dirname, '../../resources/js/composables/useConfirm.ts');
const loaded = new Module(source, module);
loaded.filename = source;
loaded.paths = module.paths;
loaded._compile(ts.transpileModule(fs.readFileSync(source, 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText, source);
const { useConfirm } = loaded.exports;

test('only explicit acceptance approves the action; cancellation allows another request', async () => {
    const first = useConfirm();
    const host = useConfirm();
    const cancelled = first.confirm({ title: 'Remove?', description: 'Selected extension', danger: true });
    assert.equal(host.state.open, true);
    assert.equal(host.state.description, 'Selected extension');
    host.cancel();
    assert.equal(await cancelled, false);
    const accepted = first.confirm({ title: 'Uninstall?', confirmText: 'Uninstall', danger: true });
    host.accept();
    assert.equal(await accepted, true);
    assert.equal(host.state.open, false);
    assert.equal(host.state.resolve, null);
});

test('a second click cannot replace the pending action or leave its promise unresolved', async () => {
    const { confirm, state, cancel } = useConfirm();
    const original = confirm({ title: 'Original target' });
    assert.equal(await confirm({ title: 'Different target' }), false);
    assert.equal(state.title, 'Original target');
    cancel();
    assert.equal(await original, false);
});
