const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const Module = require('node:module');
const ts = require('typescript');
const { reactive } = require('vue');
const source = path.resolve(__dirname, '../../resources/js/composables/useCombManifest.ts');
const compiled = ts.transpileModule(fs.readFileSync(source, 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;
const loaded = new Module(source, module);
loaded.filename = source;
loaded.paths = module.paths;
loaded._compile(compiled, source);
const { useCombManifest } = loaded.exports;

function formFor(manifest) {
    const form = reactive({
        external_id: '',
        name: '',
        category: 'game',
        group: '',
        game: '',
        manifest: JSON.stringify(manifest),
        console_commands_text: '',
        stop_command: 'stop',
        stop_timeout: 30,
        stop_signal: 'SIGTERM',
    });
    const binding = useCombManifest(form);
    return { form, binding };
}

test('pasting a custom Comb fills all metadata, commands and shutdown fields', () => {
    const { form } = formFor({ id: 'minecraft', name: 'Minecraft', game: 'minecraft', group: 'minecraft', category: 'game' });
    form.manifest = JSON.stringify({
        id: 'dragonwilds',
        name: 'RuneScape Dragonwilds',
        game: 'dragonwilds',
        group: 'steam',
        category: 'game',
        image: 'custom/image',
        console_commands: ['listplayers', 'say '],
        stop: { command: 'quit', timeout: 90, signal: 'SIGINT' },
    });
    assert.equal(form.external_id, 'dragonwilds');
    assert.equal(form.name, 'RuneScape Dragonwilds');
    assert.equal(form.group, 'steam');
    assert.equal(form.game, 'dragonwilds');
    assert.equal(form.console_commands_text, 'listplayers\nsay ');
    assert.equal(form.stop_command, 'quit');
    assert.equal(form.stop_timeout, 90);
    assert.equal(form.stop_signal, 'SIGINT');
    form.name = 'My Dragonwilds Comb';
    const output = JSON.parse(form.manifest);
    assert.equal(output.name, 'My Dragonwilds Comb');
    assert.equal(output.image, 'custom/image');
    assert.deepEqual(output.console_commands, ['listplayers', 'say ']);
});

test('field edits preserve unrelated manifest keys and signal-only shutdown', () => {
    const { form, binding } = formFor({
        id: 'custom',
        name: 'Custom',
        category: 'application',
        group: 'custom',
        environment: { SECRET_VAR: '{{value}}' },
        custom: true,
    });
    form.console_commands_text = 'listplayers\n\nhelp';
    form.stop_command = '';
    form.stop_timeout = 60;
    binding.syncManifestFromFields();
    const output = JSON.parse(form.manifest);
    assert.deepEqual(output.console_commands, ['listplayers', 'help']);
    assert.deepEqual(output.stop, { command: '', timeout: 60, signal: 'SIGTERM' });
    assert.equal(output.custom, true);
    assert.equal(output.environment.SECRET_VAR, '{{value}}');
});

test('invalid JSON is left untouched and a valid paste clears absent old fields', () => {
    const { form } = formFor({ id: 'minecraft', name: 'Minecraft', game: 'minecraft', group: 'minecraft' });
    form.manifest = '{ invalid';
    form.name = 'Changed';
    assert.equal(form.manifest, '{ invalid');
    form.manifest = JSON.stringify({ id: 'generic', name: 'Generic' });
    assert.equal(form.game, '');
    assert.equal(form.group, 'other');
    assert.equal(form.category, 'application');
    assert.equal(form.external_id, 'generic');
});

test('unchanged imported JSON retains absent fields and unrelated shutdown options', () => {
    const manifest = {
        id: 'custom',
        name: 'Custom',
        category: 'web',
        group: 'static',
        console_commands: ['status', 'say '],
        stop: { command: '', signal: 'SIGINT', timeout: 45, custom_flag: true },
        extension: { keep: true },
    };
    const { form, binding } = formFor(manifest);
    binding.syncManifestFromFields();
    assert.deepEqual(JSON.parse(form.manifest), manifest);
    form.name = 'Updated';
    const result = JSON.parse(form.manifest);
    assert.deepEqual(result.console_commands, manifest.console_commands);
    assert.deepEqual(result.stop, manifest.stop);
    assert.equal(result.game, undefined);
    const empty = formFor({ id: 'empty', name: 'Empty' });
    empty.binding.syncManifestFromFields();
    assert.deepEqual(JSON.parse(empty.form.manifest), { id: 'empty', name: 'Empty' });
});

test('an imported null shutdown command displays as signal-only without changing the JSON', () => {
    const manifest = { id: 'signal', name: 'Signal only', stop: { command: null, timeout: 30, signal: 'SIGINT' } };
    const { form, binding } = formFor(manifest);
    assert.equal(form.stop_command, '');
    binding.syncManifestFromFields();
    assert.deepEqual(JSON.parse(form.manifest), manifest);
});
