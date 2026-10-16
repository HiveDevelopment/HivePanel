<script setup lang="ts">
import { themePresets, validateTokens, type DesignTokens } from '@/composables/useDesignTokens';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, ChevronDown, Copy, Download, Eye, Layers, Palette, Plus, Save, ShieldCheck, Sparkles, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Theme = { id: string; name: string; enabled: boolean; tokens: DesignTokens };
const props = defineProps<{ catalog: { default: string; themes: Theme[] }; canEdit: boolean }>();
const form = useForm({ default: props.catalog.default, themes: JSON.parse(JSON.stringify(props.catalog.themes)) as Theme[] });
const selectedId = ref(form.themes[0]?.id ?? '');
const selected = computed(() => form.themes.find((theme) => theme.id === selectedId.value));
const offered = computed(() => form.themes.filter((theme) => theme.enabled));
const defaultName = computed(() => form.themes.find((theme) => theme.id === form.default)?.name ?? 'Choose a default');
const locked = computed(() => !props.canEdit || form.processing);
const pendingDelete = ref('');
const importError = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const activeGroup = ref('Brand');
const groups: Array<{ name: string; description: string; fields: Array<{ key: keyof DesignTokens; label: string }> }> = [
    {
        name: 'Brand',
        description: 'The accent colours used for primary actions and highlights.',
        fields: [
            { key: 'accent', label: 'Primary accent' },
            { key: 'accentLight', label: 'Light accent' },
            { key: 'accentDark', label: 'Dark accent' },
        ],
    },
    {
        name: 'Surfaces',
        description: 'Backgrounds for the panel, cards and interactive elements.',
        fields: [
            { key: 'surfaceDark', label: 'Page background' },
            { key: 'surface', label: 'Card background' },
            { key: 'surfaceLight', label: 'Light surface' },
            { key: 'surfaceHover', label: 'Hover background' },
            { key: 'surfaceElevated', label: 'Raised surface' },
        ],
    },
    {
        name: 'Text & borders',
        description: 'Readable text and subtle separation between elements.',
        fields: [
            { key: 'foreground', label: 'Primary text' },
            { key: 'muted', label: 'Secondary text' },
            { key: 'border', label: 'Borders' },
        ],
    },
    {
        name: 'Status',
        description: 'Colours for success, warning and error messages.',
        fields: [
            { key: 'success', label: 'Success' },
            { key: 'warning', label: 'Warning' },
            { key: 'danger', label: 'Error' },
        ],
    },
    {
        name: 'Shape',
        description: 'Control how rounded cards and buttons appear.',
        fields: [
            { key: 'panelRadius', label: 'Card corners' },
            { key: 'buttonRadius', label: 'Button corners' },
        ],
    },
];
const currentGroup = computed(() => groups.find((group) => group.name === activeGroup.value)!);
const canRemove = computed(() => selected.value && form.themes.length > 1 && !(selected.value.enabled && offered.value.length === 1));

function newId() {
    return `theme-${crypto.randomUUID()}`;
}
function add(copy = false) {
    if (locked.value || form.themes.length >= 30) return;
    const source = copy ? selected.value : null;
    const theme = {
        id: newId(),
        name: source ? `${source.name} copy` : 'Untitled theme',
        enabled: false,
        tokens: { ...(source?.tokens ?? themePresets.hive) },
    };
    form.themes.push(theme);
    selectedId.value = theme.id;
    pendingDelete.value = '';
    importError.value = '';
}
function select(theme: Theme) {
    selectedId.value = theme.id;
    pendingDelete.value = '';
    importError.value = '';
}
function toggleOffered() {
    if (!selected.value || locked.value) return;
    if (selected.value.enabled && offered.value.length === 1) return;
    selected.value.enabled = !selected.value.enabled;
    if (!selected.value.enabled && form.default === selected.value.id) form.default = offered.value[0]?.id ?? '';
}
function remove() {
    if (!selected.value || !canRemove.value || locked.value) return;
    const id = selected.value.id;
    form.themes = form.themes.filter((theme) => theme.id !== id);
    if (form.default === id) form.default = offered.value[0]?.id ?? '';
    selectedId.value = form.themes[0]?.id ?? '';
    pendingDelete.value = '';
}
function preset(name: keyof typeof themePresets) {
    if (selected.value && !locked.value) selected.value.tokens = { ...themePresets[name] };
}
function exportTheme() {
    if (!selected.value) return;
    const url = URL.createObjectURL(
        new Blob([JSON.stringify({ name: selected.value.name, tokens: selected.value.tokens }, null, 4)], { type: 'application/json' }),
    );
    const link = document.createElement('a');
    link.href = url;
    link.download = `${selected.value.id}.json`;
    link.click();
    URL.revokeObjectURL(url);
}
async function importTheme(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file || locked.value || !selected.value) return;
    const targetId = selected.value.id;
    try {
        if (file.size > 8192) throw new Error();
        const parsed = JSON.parse(await file.text());
        const tokens = validateTokens(parsed.tokens ?? parsed);
        const target = form.themes.find((theme) => theme.id === targetId);
        if (target) target.tokens = tokens;
        importError.value = '';
    } catch {
        importError.value = 'Choose a valid theme JSON file containing supported colour and radius tokens (up to 8 KB).';
    } finally {
        input.value = '';
    }
}
</script>

<template>
    <AppLayout context="admin">
        <Head :title="t('Themes')" />
        <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <section
                class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface px-5 py-5 sm:px-6"
            >
                <div class="flex items-center gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-hive/20 bg-hive/[0.06]">
                        <Palette class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-foreground sm:text-2xl">{{ t('Themes') }}</h1>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            {{ t('ui.make_hivepanel_your_own_choose_the_looks_your_users_can_select') }}
                        </p>
                    </div>
                </div>
                <button
                    v-if="canEdit"
                    type="button"
                    :disabled="locked || form.themes.length >= 30"
                    class="flex items-center gap-2 rounded-button border border-foreground/[0.08] bg-foreground/[0.025] px-4 py-2.5 text-xs font-semibold text-foreground transition hover:bg-foreground/[0.05] disabled:opacity-40"
                    @click="add()"
                >
                    <Plus class="size-4" /> {{ t('ui.create_theme') }}
                </button>
            </section>

            <form class="space-y-5" @submit.prevent="form.put('/admin/themes', { preserveScroll: true })">
                <section
                    class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface px-5 py-4 sm:px-6"
                >
                    <div class="flex items-center gap-3">
                        <ShieldCheck class="size-4 text-hive" />
                        <div>
                            <label for="default-theme" class="text-xs font-semibold text-foreground">{{ t('theme.default') }}</label>
                            <p class="mt-1 text-[11px] text-muted-foreground">{{ t('ui.used_for_new_users_and_anyone_following_the_default') }}</p>
                        </div>
                    </div>
                    <div class="relative w-full sm:w-56">
                        <select
                            id="default-theme"
                            v-model="form.default"
                            :disabled="locked"
                            class="h-10 w-full appearance-none rounded-button border border-foreground/[0.08] bg-surface-dark pl-3 pr-9 text-xs text-foreground outline-none focus:border-hive/50 disabled:opacity-60"
                        >
                            <option v-for="theme in offered" :key="theme.id" :value="theme.id">{{ theme.name }}</option></select
                        ><ChevronDown class="pointer-events-none absolute right-3 top-3 size-4 text-muted-foreground" />
                    </div>
                </section>

                <div class="grid items-start gap-5 xl:grid-cols-[280px_minmax(0,1fr)]">
                    <aside class="overflow-hidden rounded-panel border border-foreground/[0.08] bg-surface">
                        <div class="flex items-center justify-between border-b border-foreground/[0.06] px-4 py-4">
                            <span class="flex items-center gap-2 text-xs font-semibold text-foreground"
                                ><Layers class="size-4 text-muted-foreground" /> {{ t('ui.your_themes') }}</span
                            ><span class="text-[11px] text-muted-foreground">{{ form.themes.length }}</span>
                        </div>
                        <div class="space-y-2 p-3">
                            <button
                                v-for="theme in form.themes"
                                :key="theme.id"
                                type="button"
                                :aria-pressed="selectedId === theme.id"
                                class="w-full rounded-button border p-3 text-left transition"
                                :class="
                                    selectedId === theme.id
                                        ? 'border-hive/25 bg-hive/[0.06]'
                                        : 'border-transparent hover:border-foreground/[0.06] hover:bg-foreground/[0.025]'
                                "
                                @click="select(theme)"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-foreground/10"
                                        :style="{ background: theme.tokens.surfaceDark }"
                                        ><span class="size-4 rounded-full" :style="{ background: theme.tokens.accent }"
                                    /></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-semibold text-foreground">{{ theme.name || 'Untitled theme' }}</p>
                                        <p class="mt-1 text-[10px] text-muted-foreground">
                                            {{ theme.id === form.default ? 'Default · ' : ''
                                            }}{{ theme.enabled ? 'Available to users' : 'Not offered' }}
                                        </p>
                                    </div>
                                    <Check v-if="selectedId === theme.id" class="size-4 shrink-0 text-hive" />
                                </div>
                            </button>
                        </div>
                        <p class="border-t border-foreground/[0.06] px-4 py-4 text-[11px] leading-5 text-muted-foreground">
                            {{ offered.length }} {{ t('ui.offered_to_users_changes_take_effect_when_you_save') }}
                        </p>
                    </aside>

                    <section v-if="selected" class="min-w-0 overflow-hidden rounded-panel border border-foreground/[0.08] bg-surface">
                        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-foreground/[0.06] px-5 py-4 sm:px-6">
                            <div>
                                <h2 class="text-sm font-semibold text-foreground">{{ t('ui.theme_editor') }}</h2>
                                <p class="mt-1 text-[11px] text-muted-foreground">{{ t('ui.preview_your_changes_before_offering_this_theme') }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    :disabled="locked || form.themes.length >= 30"
                                    class="flex items-center gap-1.5 rounded-lg border border-foreground/[0.08] px-3 py-2 text-[11px] text-muted-foreground disabled:opacity-40"
                                    @click="add(true)"
                                >
                                    <Copy class="size-3.5" /> {{ t('ui.duplicate') }}</button
                                ><button
                                    type="button"
                                    class="flex items-center gap-1.5 rounded-lg border border-foreground/[0.08] px-3 py-2 text-[11px] text-muted-foreground"
                                    @click="exportTheme"
                                >
                                    <Download class="size-3.5" /> {{ t('ui.export') }}
                                </button>
                            </div>
                        </div>

                        <div class="space-y-6 p-5 sm:p-6">
                            <div class="grid items-end gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="theme-name" class="block text-xs font-medium text-foreground">{{ t('ui.theme_name') }}</label
                                    ><input
                                        id="theme-name"
                                        v-model="selected.name"
                                        :disabled="locked"
                                        maxlength="80"
                                        :placeholder="t('ui.give_your_theme_a_name')"
                                        class="mt-2 h-10 w-full rounded-button border border-foreground/[0.08] bg-surface-dark px-3 text-xs text-foreground outline-none focus:border-hive/50 disabled:opacity-60"
                                    />
                                </div>
                                <div class="flex h-10 items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-medium text-foreground">{{ t('ui.available_to_users') }}</p>
                                        <p class="mt-1 text-[11px] text-muted-foreground">{{ t('ui.show_in_their_theme_selector') }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="selected.enabled"
                                        :aria-label="t('ui.offer_this_theme_to_users')"
                                        :disabled="locked || (selected.enabled && offered.length === 1)"
                                        class="relative h-6 w-11 shrink-0 rounded-full border transition disabled:opacity-50"
                                        :class="selected.enabled ? 'border-hive/30 bg-hive' : 'border-foreground/10 bg-surface-light'"
                                        @click="toggleOffered"
                                    >
                                        <span
                                            class="absolute top-0.5 h-[18px] w-[18px] rounded-full bg-white transition-transform"
                                            :class="selected.enabled ? 'left-0.5 translate-x-5' : 'left-0.5'"
                                        />
                                    </button>
                                </div>
                            </div>

                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="flex items-center gap-2 text-xs font-medium text-foreground">
                                        <Eye class="size-3.5 text-muted-foreground" /> {{ t('ui.live_preview') }}
                                    </p>
                                    <span class="text-[10px] text-muted-foreground">{{ t('ui.sample_components') }}</span>
                                </div>
                                <div
                                    class="overflow-hidden border"
                                    :style="{
                                        background: selected.tokens.surfaceDark,
                                        color: selected.tokens.foreground,
                                        borderColor: selected.tokens.border,
                                        borderRadius: `${selected.tokens.panelRadius}px`,
                                    }"
                                >
                                    <div
                                        class="flex items-center justify-between border-b px-4 py-3"
                                        :style="{ background: selected.tokens.surface, borderColor: selected.tokens.border }"
                                    >
                                        <span class="flex items-center gap-2 text-xs font-semibold"
                                            ><span class="size-2 rounded-full" :style="{ background: selected.tokens.accent }" />
                                            {{ t('ui.hivepanel_6123007') }}</span
                                        ><span class="text-[10px]" :style="{ color: selected.tokens.muted }">{{ t('ui.your_workspace') }}</span>
                                    </div>
                                    <div class="grid gap-4 p-4 sm:grid-cols-[1fr_auto]">
                                        <div
                                            class="border p-4"
                                            :style="{
                                                background: selected.tokens.surface,
                                                borderColor: selected.tokens.border,
                                                borderRadius: `${selected.tokens.panelRadius}px`,
                                            }"
                                        >
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-semibold">{{ t('ui.minecraft_server') }}</span
                                                ><span class="flex items-center gap-1.5 text-[10px]" :style="{ color: selected.tokens.success }"
                                                    ><span class="size-1.5 rounded-full" :style="{ background: selected.tokens.success }" />
                                                    {{ t('ui.running') }}</span
                                                >
                                            </div>
                                            <p class="mt-2 text-[11px]" :style="{ color: selected.tokens.muted }">
                                                {{ t('ui.everything_you_need_in_one_place') }}
                                            </p>
                                            <div class="mt-4 flex items-center gap-2">
                                                <span
                                                    class="px-3 py-2 text-[11px] font-semibold text-black"
                                                    :style="{ background: selected.tokens.accent, borderRadius: `${selected.tokens.buttonRadius}px` }"
                                                    >{{ t('ui.open_console') }}</span
                                                ><span
                                                    class="border px-3 py-2 text-[11px]"
                                                    :style="{
                                                        borderColor: selected.tokens.border,
                                                        borderRadius: `${selected.tokens.buttonRadius}px`,
                                                    }"
                                                    >{{ t('ui.manage') }}</span
                                                >
                                            </div>
                                        </div>
                                        <div class="flex gap-3 sm:flex-col sm:justify-center">
                                            <span class="text-[10px]" :style="{ color: selected.tokens.success }">{{ t('ui.success') }}</span
                                            ><span class="text-[10px]" :style="{ color: selected.tokens.warning }">{{ t('ui.warning_88bd0dc') }}</span
                                            ><span class="text-[10px]" :style="{ color: selected.tokens.danger }">{{ t('ui.error') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <fieldset :disabled="locked">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                    <p class="flex items-center gap-2 text-xs font-medium text-foreground">
                                        <Sparkles class="size-3.5 text-muted-foreground" /> {{ t('ui.start_from_a_preset') }}
                                    </p>
                                    <div class="flex gap-2">
                                        <button
                                            v-for="(tokens, name) in themePresets"
                                            :key="name"
                                            type="button"
                                            class="flex items-center gap-1.5 rounded-lg border border-foreground/[0.08] px-2.5 py-1.5 text-[11px] capitalize text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                            @click="preset(name)"
                                        >
                                            <span class="size-2 rounded-full" :style="{ background: tokens.accent }" />{{ name }}
                                        </button>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1 border-b border-foreground/[0.06] pb-3">
                                    <button
                                        v-for="group in groups"
                                        :key="group.name"
                                        type="button"
                                        :aria-pressed="activeGroup === group.name"
                                        class="rounded-lg px-3 py-2 text-[11px] font-medium transition"
                                        :class="
                                            activeGroup === group.name
                                                ? 'bg-hive/10 text-hive'
                                                : 'text-muted-foreground hover:bg-foreground/[0.025] hover:text-primary-foreground'
                                        "
                                        @click="activeGroup = group.name"
                                    >
                                        {{ group.name }}
                                    </button>
                                </div>
                                <p class="my-4 text-[11px] leading-5 text-muted-foreground">{{ currentGroup.description }}</p>
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    <div
                                        v-for="field in currentGroup.fields"
                                        :key="field.key"
                                        class="rounded-button border border-foreground/[0.06] bg-surface-dark p-3"
                                    >
                                        <label :for="`token-${field.key}`" class="mb-2 block text-[11px] font-medium text-muted-foreground">{{
                                            field.label
                                        }}</label>
                                        <div v-if="field.key === 'panelRadius' || field.key === 'buttonRadius'" class="flex items-center gap-2">
                                            <input
                                                :id="`token-${field.key}`"
                                                v-model.number="selected.tokens[field.key]"
                                                type="number"
                                                min="0"
                                                max="24"
                                                class="w-full bg-transparent text-sm text-foreground outline-none focus:ring-1 focus:ring-hive/50"
                                            /><span class="text-[11px] text-muted-foreground">{{ t('ui.px') }}</span>
                                        </div>
                                        <div v-else class="flex items-center gap-2">
                                            <input
                                                :id="`token-${field.key}`"
                                                v-model="selected.tokens[field.key]"
                                                type="color"
                                                class="size-8 shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0"
                                            /><input
                                                v-model="selected.tokens[field.key]"
                                                :aria-label="`${field.label} hex colour`"
                                                type="text"
                                                maxlength="7"
                                                pattern="#[0-9a-fA-F]{6}"
                                                class="w-full min-w-0 bg-transparent font-mono text-xs uppercase text-foreground outline-none focus:ring-1 focus:ring-hive/50"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-foreground/[0.06] pt-4">
                                <div>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept="application/json,.json"
                                        class="hidden"
                                        :aria-label="t('ui.import_theme_tokens')"
                                        @change="importTheme"
                                    /><button
                                        type="button"
                                        :disabled="locked"
                                        class="flex items-center gap-2 text-[11px] text-muted-foreground hover:text-foreground disabled:opacity-40"
                                        @click="fileInput?.click()"
                                    >
                                        <Upload class="size-3.5" /> {{ t('ui.import_tokens') }}
                                    </button>
                                </div>
                                <button
                                    v-if="pendingDelete !== selected.id"
                                    type="button"
                                    :disabled="locked || !canRemove"
                                    class="flex items-center gap-1.5 text-[11px] text-status-danger disabled:opacity-30"
                                    @click="pendingDelete = selected.id"
                                >
                                    <Trash2 class="size-3.5" /> {{ t('ui.delete_theme') }}
                                </button>
                                <div v-else class="flex flex-wrap items-center gap-3 text-[11px]">
                                    <span class="text-muted-foreground">{{ t('ui.delete') }} {{ selected.name }}?</span
                                    ><button type="button" :disabled="locked" class="text-status-danger" @click="remove">{{ t('ui.delete') }}</button
                                    ><button type="button" class="text-muted-foreground" @click="pendingDelete = ''">{{ t('ui.cancel') }}</button>
                                </div>
                            </div>
                            <p v-if="importError" role="alert" class="text-xs text-status-danger">{{ importError }}</p>
                        </div>
                    </section>
                </div>

                <div
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="rounded-panel border border-status-danger/20 bg-status-danger/5 p-4 text-xs text-status-danger"
                >
                    <p v-for="(message, key) in form.errors" :key="key">{{ message }}</p>
                </div>
                <footer
                    class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface px-5 py-4 sm:px-6"
                >
                    <div>
                        <p class="text-xs font-medium text-foreground">{{ form.isDirty ? 'You have unsaved changes' : 'Theme configuration' }}</p>
                        <p class="mt-1 text-[11px] text-muted-foreground">{{ offered.length }} {{ t('ui.offered_default') }} {{ defaultName }}</p>
                    </div>
                    <button
                        v-if="canEdit"
                        type="submit"
                        :disabled="locked || !form.isDirty"
                        class="flex items-center gap-2 rounded-button bg-hive px-5 py-2.5 text-xs font-bold text-black transition hover:brightness-110 disabled:opacity-40"
                    >
                        <Save class="size-4" />{{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                    <span v-else class="text-[11px] text-muted-foreground">{{ t('ui.view_only_editing_requires_settings_permission') }}</span>
                    <p v-if="form.recentlySuccessful" role="status" class="w-full text-xs text-status-success">{{ t('ui.themes_saved') }}</p>
                </footer>
            </form>
        </main>
    </AppLayout>
</template>
