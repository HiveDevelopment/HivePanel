const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const Module = require('node:module');
const ts = require('typescript');
function load(relative) {
    const source = path.resolve(__dirname, '../../resources/js', relative);
    const loaded = new Module(source, module);
    loaded.filename = source;
    loaded.paths = Module._nodeModulePaths(path.dirname(source));
    loaded._compile(ts.transpileModule(fs.readFileSync(source, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, esModuleInterop: true } }).outputText, source);
    return loaded.exports;
}
test('live stop clears the initial running state and failed polls do not overwrite it', async () => {
    global.window = { setInterval: () => 1, clearInterval: () => {} };
    let data = { running: true, memory_mb: 123 };
    global.fetch = async () => ({ ok: true, json: async () => data });
    const binding = load('composables/useActiveCell.ts').useActiveCell();
    binding.setActiveCell({ id: 'cell', status: 'running', install_status: 'installed' });
    await new Promise(setImmediate);
    assert.equal(binding.activeCellStatus.value, 'running');
    data = { running: false, memory_mb: 0 };
    await binding.refreshActiveCell();
    assert.equal(binding.activeCellStatus.value, 'offline');
    data = { error: 'node unavailable' };
    await binding.refreshActiveCell();
    assert.equal(binding.activeCellStatus.value, 'offline');
    assert.equal(binding.activeCellStats.value.running, false);
    binding.stopActiveCellPolling();
});
test('game menus point to implemented pages and accept scoped Comb shortcuts', () => {
    const { serverNavigation } = load('components/sidebar/navigation.ts');
    const links = manifest => serverNavigation('cell', manifest, true, false).flatMap(group => group.items ?? [group]);
    const minecraft = links('minecraft');
    assert.ok(minecraft.some(link => link.href === '/cells/cell/players'));
    assert.ok(minecraft.some(link => link.href === '/cells/cell/minecraft/plugins'));
    const other = links({ game: 'dragonwilds', name: 'Dragonwilds', sidebar: [{ title: 'Saved settings', type: 'files', path: 'Game/Saved' }, { title: 'Unsafe', type: 'files', path: '../secret' }] });
    assert.ok(!other.some(link => link.title === 'Players' || link.title === 'Plugins' || link.title === 'Unsafe'));
    assert.ok(other.some(link => link.href === '/cells/cell/files?path=Game%2FSaved'));
    for (const link of [...minecraft, ...other]) assert.match(link.href, /^\/cells\/cell(?:$|\/(activity|users|files|databases|network|backups|schedules|importer|startup|config|settings|players|minecraft\/(?:mods|plugins|jars|modpacks|builder))(?:\?|$))/);
});
