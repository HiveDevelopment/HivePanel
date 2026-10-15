const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const Module = require('node:module');
const ts = require('typescript');
const source = path.resolve(__dirname, '../../resources/js/composables/combCreation.ts');
const loaded = new Module(source, module);
loaded.filename=source;loaded.paths=module.paths;
loaded._compile(ts.transpileModule(fs.readFileSync(source,'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022}}).outputText,source);
const {combGroupLabel,resourceRecommendations,nextResources,defaultResources} = loaded.exports;
test('web and runtime Combs are not grouped using the legacy unknown game value',()=>{
 assert.equal(combGroupLabel({game:'unknown',data:{category:'web',group:'static'}}),'Web Hosting');
 assert.equal(combGroupLabel({game:'unknown',data:{category:'runtime',group:'nodejs'}}),'Runtimes');
 assert.equal(combGroupLabel({game:'unknown',data:{category:'bot',group:'discord'}}),'Bots');
 assert.equal(combGroupLabel({game:'unknown',data:{category:'database',group:'mysql'}}),'Databases');
 assert.equal(combGroupLabel({game:'minecraft',data:{}}),'Minecraft');
 assert.equal(combGroupLabel({game:'unknown',data:{category:'game',group:'custom-game'}}),'Custom Game');
 assert.equal(combGroupLabel({game:'unknown',data:{}}),'Applications');
});
test('resource values come from the manifest, preserving zero and filtering invalid limits',()=>{
 assert.deepEqual(resourceRecommendations({recommended_resources:{memory_mb:128,disk_mb:1024,cpu_percent:25}}),{memory_mb:128,disk_mb:1024,cpu_percent:25});
 assert.deepEqual(resourceRecommendations({recommended_resources:{memory_mb:'128',cpu_percent:1001,disk_mb:-1,io_weight:0,swap_mb:-1,overhead_memory_mb:0}}),{swap_mb:-1,overhead_memory_mb:0});
 assert.deepEqual(resourceRecommendations({}),{});
});
test('switching templates updates automatic values while retaining manual edits',()=>{
 const first=nextResources(defaultResources,defaultResources,{memory_mb:128,disk_mb:1024,cpu_percent:25});
 assert.equal(first.values.memory_mb,128);
 const manual={...first.values,memory_mb:768};
 const second=nextResources(manual,first.automatic,{memory_mb:512,disk_mb:2048,cpu_percent:100});
 assert.equal(second.values.memory_mb,768);assert.equal(second.values.disk_mb,2048);assert.equal(second.values.cpu_percent,100);
 const reset=nextResources(second.values,second.automatic,{memory_mb:512,disk_mb:2048,cpu_percent:100},true);
 assert.equal(reset.values.memory_mb,512);
});
test('unconfigured templates return to generic defaults without discarding overrides',()=>{
 const a=nextResources(defaultResources,defaultResources,{memory_mb:128,disk_mb:1024});
 const b=nextResources({...a.values,cpu_percent:50},a.automatic,{});
 assert.equal(b.values.memory_mb,defaultResources.memory_mb);assert.equal(b.values.disk_mb,defaultResources.disk_mb);assert.equal(b.values.cpu_percent,50);
});
