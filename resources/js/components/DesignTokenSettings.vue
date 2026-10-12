<script setup lang="ts">
import { ref } from 'vue';
import { applyTokens, designTokens, themePresets } from '@/composables/useDesignTokens';
import { availableLanguages, locale, setLanguage, t } from '@/composables/useLanguage';

const draft = ref(JSON.stringify(designTokens.value, null, 4));
const error = ref('');
function apply() {
    try { applyTokens(JSON.parse(draft.value)); error.value = ''; } catch { error.value = t('theme.invalid'); }
}
function preset(name: keyof typeof themePresets) {
    applyTokens(themePresets[name]); draft.value = JSON.stringify(designTokens.value, null, 4); error.value = '';
}
function exportTheme() {
    const url = URL.createObjectURL(new Blob([JSON.stringify(designTokens.value, null, 4)], { type: 'application/json' }));
    const link = document.createElement('a'); link.href = url; link.download = 'hivepanel-theme.json'; link.click(); URL.revokeObjectURL(url);
}
async function importTheme(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (file.size > 8192) { error.value = t('theme.invalid'); return; }
    try { draft.value = await file.text(); apply(); } catch { error.value = t('theme.invalid'); }
}
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5 text-white">
        <h2 class="font-bold">{{ t('theme.preset') }}</h2>
        <p class="mt-2 text-sm text-zinc-400">{{ t('theme.description') }}</p>
        <div class="mt-4 flex gap-2">
            <button v-for="(_, name) in themePresets" :key="name" type="button" class="rounded-button border border-zinc-700 px-4 py-2 capitalize" @click="preset(name)">{{ name }}</button>
        </div>
        <label class="mt-5 block text-sm font-bold">{{ t('theme.custom') }}<textarea v-model="draft" spellcheck="false" rows="15" class="mt-2 block w-full rounded-button border border-zinc-700 bg-surface-dark p-3 font-mono text-xs" /></label>
        <p v-if="error" role="alert" class="mt-2 text-sm text-status-danger">{{ error }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" class="rounded-button bg-hive px-4 py-2 font-bold text-black" @click="apply">{{ t('theme.apply') }}</button>
            <button type="button" class="rounded-button border border-zinc-700 px-3 py-2" @click="preset('hive')">{{ t('theme.reset') }}</button>
            <button type="button" class="rounded-button border border-zinc-700 px-3 py-2" @click="exportTheme">{{ t('theme.export') }}</button>
            <input type="file" accept="application/json,.json" aria-label="Import theme JSON" class="text-xs" @change="importTheme" />
        </div>
        <label class="mt-6 block font-bold">{{ t('language.title') }}
            <select :value="locale" class="mt-2 block rounded-button border border-zinc-700 bg-surface-dark px-3 py-2" @change="setLanguage(($event.target as HTMLSelectElement).value)">
                <option v-for="language in availableLanguages" :key="language.id" :value="language.id">{{ language.name }}</option>
            </select>
        </label>
        <p class="mt-2 text-sm text-zinc-400">{{ t('language.description') }}</p>
    </section>
</template>
