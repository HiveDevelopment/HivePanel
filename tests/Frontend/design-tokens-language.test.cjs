const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const Module = require('node:module');
const ts = require('typescript');
function load(name) {
 const source = path.resolve(__dirname, '../../resources/js/composables', name + '.ts');
 const compiled = ts.transpileModule(fs.readFileSync(source, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, esModuleInterop: true } }).outputText;
 const loaded = new Module(source, module); loaded.filename = source; loaded.paths = Module._nodeModulePaths(path.dirname(source)); loaded._compile(compiled, source); return loaded.exports;
}
const themes = load('useDesignTokens'); const language = load('useLanguage');
test('rejects unsafe theme values and produces shared CSS tokens', () => {
 for (const input of [{accent:'url(example)'},{unexpected:'#ffffff'},{panelRadius:-1},{buttonRadius:25},JSON.parse('{"__proto__":"#ffffff"}')]) assert.throws(() => themes.validateTokens(input));
 assert.equal(themes.validateTokens({accent:'#123456'}).surface, themes.themePresets.hive.surface);
 themes.applyTokens({accent:'#123456',panelRadius:8});
 assert.equal(themes.extensionTheme()['--hp-accent'],'18 52 86'); assert.equal(themes.extensionTheme()['--hp-panelRadius'],'8px');
});
test('translations fall back to English and reject unsupported locales', () => {
 language.setLanguage('de'); assert.equal(language.t('Console'),'Konsole');
 const en = require('../../resources/lang/en.json'); const de = require('../../resources/lang/de.json');
 const key = Object.keys(en).find(key => !(key in de)); assert.ok(key); assert.equal(language.t(key),en[key]);
 language.setLanguage('unknown'); assert.equal(language.locale.value,'de'); assert.equal(language.t('unknown.key'),'unknown.key');
});
