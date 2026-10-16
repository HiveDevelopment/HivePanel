<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Box, Check, Copy, Eye, EyeOff, Rocket, RotateCcw, Save, Search, SlidersHorizontal, Terminal } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const props = defineProps<{
    cell: any;
    schema: Array<any>;
    variables: Record<string, string>;
    canEdit: boolean;
    images: Array<{ label: string; image: string }>;
    selectedImage: string;
}>();
const editable = computed(() =>
    props.schema.filter((variable) => variable.name && !['server_ip', 'server_port'].includes(variable.name) && variable.user_editable !== false),
);
const form = useForm({
    image: props.selectedImage,
    variables: Object.fromEntries(
        editable.value.map((variable) => [variable.name, String(props.variables[variable.name] ?? variable.default ?? '')]),
    ),
});
const search = ref('');
const revealed = ref<Record<string, boolean>>({});
const templateMode = ref(false);
const copied = ref(false);
const copyError = ref('');
function label(variable: any) {
    return (
        variable.label ||
        String(variable.name)
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, (value: string) => value.toUpperCase())
    );
}
function secret(variable: any) {
    return variable.type === 'password' || Boolean(variable.secret);
}
const filtered = computed(() =>
    editable.value.filter((variable) =>
        [label(variable), variable.name, variable.description ?? ''].join(' ').toLowerCase().includes(search.value.toLowerCase()),
    ),
);
const command = computed(() => {
    const startup = props.cell.startup?.command ?? props.cell.comb_data?.startup;
    return typeof startup === 'string' ? startup : (startup?.command ?? '');
});
const image = computed(() => form.image || props.cell.comb_data?.image || 'Defined by the Comb');
const segments = computed(() =>
    command.value
        .split(/(\{\{\s*[A-Za-z_][A-Za-z0-9_]*\s*\}\})/g)
        .filter(Boolean)
        .map((text: string) => {
            const match = text.match(/^\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}$/);
            if (!match) return { text, key: '' };
            const key = match[1];
            const definition = props.schema.find((variable) => variable.name === key);
            const value = form.variables[key] ?? props.variables[key];
            return { key, text: templateMode.value || value === undefined ? text : definition && secret(definition) ? '••••••••' : String(value) };
        }),
);
async function copyCommand() {
    try {
        await navigator.clipboard.writeText(segments.value.map((segment) => segment.text).join(''));
        copied.value = true;
        copyError.value = '';
    } catch {
        copyError.value = 'Select the command and copy it manually.';
    }
}
</script>

<template>
    <AppLayout context="server" :active-cell="cell">
        <Head :title="`${cell.name} - Startup`" />
        <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <section
                class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface px-5 py-5 sm:px-6"
            >
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/[0.06]">
                        <Rocket class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-foreground">{{ t('Startup') }}</h1>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ t('ui.configure_how_your_cell_launches_and_review_its_startup_command') }}
                        </p>
                    </div>
                </div>
                <span class="rounded-full border border-foreground/[0.08] px-3 py-1.5 text-[11px] text-muted-foreground"
                    >{{ editable.length }} {{ t('ui.editable_variables') }}</span
                >
            </section>
            <div class="grid items-stretch gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
                <section class="min-w-0 overflow-hidden rounded-panel border border-foreground/[0.08] bg-surface">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-foreground/[0.06] px-5 py-4">
                        <h2 class="flex items-center gap-2 text-xs font-semibold text-foreground">
                            <Terminal class="size-4 text-hive" /> {{ t('ui.startup_command_2b0d42d') }}
                        </h2>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="text-[11px] text-muted-foreground hover:text-foreground"
                                @click="
                                    templateMode = !templateMode;
                                    copied = false;
                                "
                            >
                                {{ templateMode ? 'Show preview' : 'Show template' }}</button
                            ><button
                                type="button"
                                :disabled="!command"
                                class="flex items-center gap-1.5 text-[11px] text-muted-foreground hover:text-hive disabled:opacity-40"
                                @click="copyCommand"
                            >
                                <Check v-if="copied" class="size-3" /><Copy v-else class="size-3" />{{ copied ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="rounded-button border border-foreground/[0.04] bg-surface-dark p-4">
                            <pre
                                class="whitespace-pre-wrap break-all font-mono text-xs leading-7 text-foreground"
                            ><span class="mr-2 select-none text-muted-foreground">$</span><template v-for="(segment, index) in segments" :key="index"><span :title="segment.key ? `Variable: ${segment.key}` : undefined" :class="segment.key ? 'rounded bg-hive/10 px-1 py-0.5 text-hive' : ''">{{ segment.text }}</span></template><span v-if="!command" class="text-muted-foreground">Startup is defined by the Comb.</span></pre>
                        </div>
                        <p class="mt-3 text-[11px] leading-5 text-muted-foreground">
                            {{
                                templateMode
                                    ? 'Highlighted placeholders are filled by the Worker when the Cell starts.'
                                    : 'Preview uses the values below. Secrets stay masked; the Worker performs the final substitution.'
                            }}
                        </p>
                        <p v-if="copyError" role="status" class="mt-2 text-[11px] text-status-warning">{{ copyError }}</p>
                    </div>
                </section>
                <section class="overflow-hidden rounded-panel border border-foreground/[0.08] bg-surface">
                    <h2 class="flex items-center gap-2 border-b border-foreground/[0.06] px-5 py-4 text-xs font-semibold text-foreground">
                        <Box class="size-4 text-muted-foreground" /> {{ t('ui.runtime_image') }}
                    </h2>
                    <div class="p-5">
                        <span class="rounded border border-hive/15 bg-hive/5 px-2 py-1 text-[10px] font-semibold text-hive">{{
                            t('ui.comb_managed')
                        }}</span>
                        <p class="mt-4 break-all font-mono text-xs leading-6 text-foreground">{{ image }}</p>
                        <label v-if="images.length > 1" class="mt-4 block text-xs text-muted-foreground"
                            >{{ t('ui.runtime_version')
                            }}<select
                                v-model="form.image"
                                :disabled="!canEdit || form.processing"
                                class="mt-2 w-full rounded-button border border-foreground/[0.08] bg-surface-dark p-3 text-xs text-foreground"
                            >
                                <option v-for="option in images" :key="option.image" :value="option.image">{{ option.label }}</option>
                            </select></label
                        >
                        <p v-if="form.errors.image" class="mt-2 text-xs text-status-danger">{{ form.errors.image }}</p>
                        <p class="mt-3 text-[11px] leading-5 text-muted-foreground">
                            {{ t('ui.the_container_image_is_selected_by_the_cell_s_comb_and_administra') }}
                        </p>
                    </div>
                </section>
            </div>
            <p
                v-if="cell.configuration_pending"
                class="rounded-button border border-status-warning/20 bg-status-warning/5 px-4 py-3 text-xs text-status-warning"
            >
                {{ t('ui.saved_configuration_changes_are_waiting_for_the_cell_s_next_start') }}
            </p>
            <form class="space-y-5" @submit.prevent="form.patch(`/cells/${cell.id}/startup`, { preserveScroll: true })">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <SlidersHorizontal class="size-4 text-muted-foreground" /> {{ t('ui.startup_variables') }}
                        </h2>
                        <p class="mt-1 text-[11px] text-muted-foreground">{{ t('ui.changes_are_saved_for_the_next_start') }}</p>
                    </div>
                    <div class="flex w-full items-center gap-2 rounded-button border border-foreground/[0.08] bg-surface px-3 py-2.5 sm:w-64">
                        <Search class="size-3.5 text-muted-foreground" /><input
                            v-model="search"
                            :aria-label="t('ui.search_startup_variables')"
                            :placeholder="t('ui.search_variables')"
                            class="w-full min-w-0 bg-transparent text-xs text-foreground outline-none placeholder:text-muted-foreground"
                        />
                    </div>
                </div>
                <fieldset :disabled="!canEdit || form.processing" class="grid items-stretch gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <section
                        v-for="variable in filtered"
                        :key="variable.name"
                        class="flex flex-col overflow-hidden rounded-panel border border-foreground/[0.08] bg-surface"
                    >
                        <div class="flex items-center justify-between gap-2 border-b border-foreground/[0.06] px-5 py-3">
                            <label :for="`variable-${variable.name}`" class="text-xs font-semibold text-foreground">{{ label(variable) }}</label
                            ><span
                                class="rounded bg-foreground/[0.035] px-2 py-0.5 text-[9px] font-medium uppercase tracking-wide text-muted-foreground"
                                >{{ variable.required ? 'Required' : 'Optional' }}</span
                            >
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <div class="relative">
                                <input
                                    :id="`variable-${variable.name}`"
                                    v-model="form.variables[variable.name]"
                                    :type="secret(variable) && !revealed[variable.name] ? 'password' : 'text'"
                                    :inputmode="['number', 'integer'].includes(variable.type) ? 'decimal' : 'text'"
                                    :required="Boolean(variable.required)"
                                    maxlength="4096"
                                    autocomplete="off"
                                    :placeholder="String(variable.default ?? '')"
                                    class="h-11 w-full rounded-button border border-foreground/[0.08] bg-surface-dark pl-3 pr-10 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/40 disabled:opacity-60"
                                /><button
                                    v-if="secret(variable)"
                                    type="button"
                                    :aria-label="revealed[variable.name] ? 'Hide value' : 'Show value'"
                                    class="absolute right-3 top-3.5 text-muted-foreground"
                                    @click="revealed[variable.name] = !revealed[variable.name]"
                                >
                                    <EyeOff v-if="revealed[variable.name]" class="size-4" /><Eye v-else class="size-4" />
                                </button>
                            </div>
                            <p v-if="variable.description" class="mt-3 text-[11px] leading-5 text-muted-foreground">{{ variable.description }}</p>
                            <p v-if="form.errors[`variables.${variable.name}`]" role="alert" class="mt-2 text-[11px] text-status-danger">
                                {{ form.errors[`variables.${variable.name}`] }}
                            </p>
                            <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                                <code class="truncate text-[10px] text-muted-foreground">{{ variable.name }}</code
                                ><button
                                    v-if="canEdit"
                                    type="button"
                                    class="flex shrink-0 items-center gap-1 text-[10px] text-muted-foreground hover:text-hive"
                                    @click="form.variables[variable.name] = String(variable.default ?? '')"
                                >
                                    <RotateCcw class="size-3" /> {{ t('ui.default') }}
                                </button>
                            </div>
                        </div>
                    </section>
                </fieldset>
                <p
                    v-if="!filtered.length"
                    class="rounded-panel border border-foreground/[0.08] bg-surface p-8 text-center text-xs text-muted-foreground"
                >
                    {{ t('ui.no_variables_match_your_search') }}
                </p>
                <div
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="rounded-panel border border-status-danger/20 bg-status-danger/5 p-4 text-xs text-status-danger"
                >
                    <p v-for="(message, key) in form.errors" :key="key">{{ message }}</p>
                </div>
                <footer class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface px-5 py-4">
                    <div>
                        <p class="text-xs font-medium text-foreground">{{ form.isDirty ? 'Unsaved startup changes' : 'Startup settings' }}</p>
                        <p class="mt-1 text-[11px] text-muted-foreground">{{ t('ui.restart_the_cell_after_saving_to_apply_the_new_values') }}</p>
                    </div>
                    <button
                        v-if="canEdit"
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 rounded-button bg-hive px-5 py-2.5 text-xs font-bold text-black disabled:opacity-50"
                    >
                        <Save class="size-4" />{{ form.processing ? 'Saving…' : 'Save changes' }}</button
                    ><span v-else class="text-xs text-muted-foreground">{{ t('ui.view_only') }}</span>
                    <p v-if="form.recentlySuccessful" role="status" class="w-full text-xs text-status-success">
                        {{ t('ui.startup_variables_saved') }}
                    </p>
                </footer>
            </form>
        </main>
    </AppLayout>
</template>
