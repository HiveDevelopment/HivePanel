<script setup lang="ts">
import InstallJobProgress from '@/components/InstallJobProgress.vue';
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { DownloadCloud, LoaderCircle, Package, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ cell: any; canInstall: boolean }>();
const base = `/cells/${props.cell.id}/preinstalls`;
const presets = ref<any[]>([]);
const versions = ref<Record<string, string>>({});
const parameters = ref<Record<string, Record<string, string>>>({});
const query = ref('');
const busy = ref(false);
const error = ref('');
const preview = ref<any>(null);
const confirm = ref(false);
const job = ref<any>({ state: 'idle', logs: [] });
const showJob = ref(false);
const installing = computed(() => job.value.state === 'installing');
const filtered = computed(() =>
    presets.value.filter((preset) => `${preset.name} ${preset.description}`.toLowerCase().includes(query.value.toLowerCase())),
);
const requests = new Set<AbortController>();
let timer: ReturnType<typeof setTimeout> | undefined;
let disposed = false;

async function api(path: string, body?: any) {
    const controller = new AbortController();
    requests.add(controller);
    try {
        const csrf = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.slice(11) ?? '',
        );
        const response = await fetch(base + path, {
            method: body ? 'POST' : 'GET',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf },
            body: body ? JSON.stringify(body) : undefined,
        });
        const result = await response.json();
        if (!response.ok)
            throw new Error(
                Object.values(result.errors ?? {})
                    .flat()
                    .join(' ') ||
                    result.message ||
                    'The request failed. Please try again.',
            );
        return result;
    } finally {
        requests.delete(controller);
    }
}
async function load() {
    const result = await api('/catalogue');
    presets.value = result.presets;
    for (const preset of presets.value) versions.value[preset.id] ??= preset.versions[0].id;
    for (const preset of presets.value)
        parameters.value[preset.id] = Object.fromEntries(
            (preset.variables ?? [])
                .filter((variable: any) => variable.install && !variable.secret)
                .map((variable: any) => [variable.name, String(props.cell.variables?.[variable.name] ?? variable.default ?? '')]),
        );
}
async function poll() {
    try {
        job.value = await api('/status');
        if (installing.value) showJob.value = true;
    } catch (exception: any) {
        if (!disposed) error.value = exception.message;
    }
    if (!disposed) timer = setTimeout(poll, installing.value ? 2000 : 5000);
}
async function review(preset: any) {
    if (busy.value || installing.value) return;
    busy.value = true;
    error.value = '';
    try {
        preview.value = await api('/preview', {
            preset: preset.id,
            version: versions.value[preset.id],
            variables: parameters.value[preset.id] ?? {},
        });
        confirm.value = true;
    } catch (exception: any) {
        if (!disposed) error.value = exception.message;
    } finally {
        busy.value = false;
    }
}
async function install() {
    if (busy.value || !preview.value) return;
    busy.value = true;
    error.value = '';
    try {
        job.value = await api('/install', { ticket: preview.value.ticket, confirm_wipe: true });
        showJob.value = true;
        confirm.value = false;
        preview.value = null;
    } catch (exception: any) {
        if (!disposed) error.value = exception.message;
        confirm.value = false;
    } finally {
        busy.value = false;
    }
}
onMounted(async () => {
    await poll();
    if (!disposed && !installing.value) {
        try {
            await load();
        } catch (exception: any) {
            if (!disposed) error.value = exception.message;
        }
    }
});
async function back() {
    showJob.value = false;
    if (!presets.value.length) {
        try {
            await load();
        } catch (exception: any) {
            error.value = exception.message;
        }
    }
}
onBeforeUnmount(() => {
    disposed = true;
    clearTimeout(timer);
    for (const controller of requests) controller.abort();
});
</script>

<template>
    <AppLayout context="server" :active-cell="cell">
        <Head :title="`${cell.name} - Preinstalls`" />
        <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <header class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface p-5 sm:p-6">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/5">
                        <Package class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-foreground">{{ showJob ? 'Installation' : 'Preinstalls' }}</h1>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                showJob
                                    ? 'Follow installation progress in your Cell.'
                                    : 'Install applications offered by your administrator through HiveRegistry.'
                            }}
                        </p>
                    </div>
                </div>
                <button v-if="!showJob && job.state !== 'idle'" class="text-xs text-hive" @click="showJob = true">
                    {{ t('ui.view_last_installation') }}
                </button>
            </header>
            <p v-if="error" role="alert" class="rounded-panel border border-status-danger/20 bg-status-danger/5 p-4 text-sm text-status-danger">
                {{ error }}
            </p>
            <template v-if="showJob || installing">
                <InstallJobProgress :job="job" />
                <div v-if="!installing" class="flex flex-wrap gap-4">
                    <button class="rounded-button bg-hive px-5 py-3 text-xs font-bold text-black" @click="back">
                        {{ t('ui.back_to_preinstalls') }}</button
                    ><Link :href="`/cells/${cell.id}/startup`" class="self-center text-sm text-hive">{{ t('ui.configure_startup_variables') }}</Link
                    ><Link :href="`/cells/${cell.id}`" class="self-center text-xs text-muted-foreground">{{ t('ui.open_console') }}</Link>
                </div>
            </template>
            <template v-else>
                <p class="text-xs leading-5 text-muted-foreground">
                    {{ t('ui.stop_your_cell_before_installing_installation_replaces_its_files_') }}
                </p>
                <div class="flex max-w-md items-center gap-3 rounded-button border border-foreground/[0.08] bg-surface px-4 py-3">
                    <Search class="size-4 text-muted-foreground" /><input
                        v-model="query"
                        :aria-label="t('ui.search_preinstalls')"
                        :placeholder="t('ui.search_preinstalls_3ca2f45')"
                        class="w-full bg-transparent text-sm text-foreground outline-none"
                    />
                </div>
                <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="preset in filtered"
                        :key="preset.id"
                        class="flex flex-col rounded-panel border border-foreground/[0.08] bg-surface p-6"
                    >
                        <h2 class="text-base font-bold text-foreground">{{ preset.name }}</h2>
                        <p class="mt-2 text-xs leading-5 text-muted-foreground">{{ preset.description }}</p>
                        <a :href="preset.source" target="_blank" rel="noopener noreferrer" class="mt-3 text-xs text-hive"
                            >{{ t('ui.project_source') }} {{ preset.license }}</a
                        >
                        <label :for="`version-${preset.id}`" class="mt-5 text-xs text-muted-foreground">{{ t('ui.version') }}</label
                        ><select
                            :id="`version-${preset.id}`"
                            v-model="versions[preset.id]"
                            class="mt-2 rounded-button border border-foreground/[0.08] bg-surface-dark p-3 text-xs text-foreground"
                        >
                            <option v-for="version in preset.versions" :key="version.id" :value="version.id">{{ version.id }}</option>
                        </select>
                        <label
                            v-for="variable in (preset.variables ?? []).filter((entry: any) => entry.install && !entry.secret)"
                            :key="variable.name"
                            class="mt-4 block text-xs text-muted-foreground"
                            >{{ variable.name
                            }}<input
                                v-model="parameters[preset.id][variable.name]"
                                class="mt-2 w-full rounded-button border border-border bg-surface-dark p-3 text-xs text-foreground"
                            /><span class="mt-1 block text-[11px] text-muted-foreground">{{ variable.description }}</span></label
                        >
                        <ul class="my-4 space-y-2 text-[11px] leading-5 text-muted-foreground">
                            <li v-for="warning in preset.warnings" :key="warning">{{ warning }}</li>
                        </ul>
                        <button
                            :disabled="busy || !canInstall"
                            class="mt-auto flex items-center justify-center gap-2 rounded-button bg-hive px-4 py-3 text-xs font-bold text-black disabled:opacity-50"
                            @click="review(preset)"
                        >
                            <LoaderCircle v-if="busy" class="size-4 animate-spin" /><DownloadCloud v-else class="size-4" />{{
                                t('ui.review_installation')
                            }}
                        </button>
                    </article>
                </div>
                <p v-if="!filtered.length && !error" class="p-6 text-center text-xs text-muted-foreground">
                    {{ t('ui.no_preinstalls_are_available_for_this_selection') }}
                </p>
                <p v-if="!canInstall" class="text-xs text-muted-foreground">
                    {{ t('ui.installing_requires_file_upload_write_and_delete_permissions_plus') }}
                </p>
            </template>
        </main>
        <ConfirmationModal
            :open="confirm"
            :title="`Install ${preview?.plan?.name ?? 'application'}?`"
            :description="t('ui.this_replaces_the_files_in_your_cell_and_changes_its_runtime_and_')"
            :confirm-text="t('ui.replace_files_and_install')"
            danger
            :loading="busy"
            @cancel="confirm = false"
            @confirm="install"
        />
    </AppLayout>
</template>
