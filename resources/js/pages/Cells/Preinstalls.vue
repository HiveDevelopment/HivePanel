<script setup lang="ts">
import InstallJobProgress from '@/components/InstallJobProgress.vue';
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { DownloadCloud, LoaderCircle, Package, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ cell: any; canInstall: boolean }>();
const base = `/cells/${props.cell.id}/preinstalls`;
const presets = ref<any[]>([]);
const versions = ref<Record<string, string>>({});
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
        preview.value = await api('/preview', { preset: preset.id, version: versions.value[preset.id] });
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
            <header class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-white/[0.08] bg-surface p-5 sm:p-6">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/5">
                        <Package class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-zinc-100">{{ showJob ? 'Installation' : 'Preinstalls' }}</h1>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{
                                showJob
                                    ? 'Follow installation progress in your Cell.'
                                    : 'Install applications offered by your administrator through HiveRegistry.'
                            }}
                        </p>
                    </div>
                </div>
                <button v-if="!showJob && job.state !== 'idle'" class="text-xs text-hive" @click="showJob = true">View last installation</button>
            </header>
            <p v-if="error" role="alert" class="rounded-panel border border-status-danger/20 bg-status-danger/5 p-4 text-sm text-status-danger">
                {{ error }}
            </p>
            <template v-if="showJob || installing">
                <InstallJobProgress :job="job" />
                <div v-if="!installing" class="flex flex-wrap gap-4">
                    <button class="rounded-button bg-hive px-5 py-3 text-xs font-bold text-black" @click="back">Back to preinstalls</button
                    ><Link :href="`/cells/${cell.id}/startup`" class="self-center text-sm text-hive">Configure startup variables</Link
                    ><Link :href="`/cells/${cell.id}`" class="self-center text-xs text-zinc-400">Open console</Link>
                </div>
            </template>
            <template v-else>
                <p class="text-xs leading-5 text-zinc-500">
                    Stop your Cell before installing. Installation replaces its files and keeps the previous files in a Cell installation backup.
                </p>
                <div class="flex max-w-md items-center gap-3 rounded-button border border-white/[0.08] bg-surface px-4 py-3">
                    <Search class="size-4 text-zinc-500" /><input
                        v-model="query"
                        aria-label="Search preinstalls"
                        placeholder="Search preinstalls…"
                        class="w-full bg-transparent text-sm text-zinc-200 outline-none"
                    />
                </div>
                <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="preset in filtered"
                        :key="preset.id"
                        class="flex flex-col rounded-panel border border-white/[0.08] bg-surface p-6"
                    >
                        <h2 class="text-base font-bold text-zinc-100">{{ preset.name }}</h2>
                        <p class="mt-2 text-xs leading-5 text-zinc-400">{{ preset.description }}</p>
                        <a :href="preset.source" target="_blank" rel="noopener noreferrer" class="mt-3 text-xs text-hive"
                            >Project source · {{ preset.license }}</a
                        >
                        <label :for="`version-${preset.id}`" class="mt-5 text-xs text-zinc-500">Version</label
                        ><select
                            :id="`version-${preset.id}`"
                            v-model="versions[preset.id]"
                            class="mt-2 rounded-button border border-white/[0.08] bg-surface-dark p-3 text-xs text-zinc-200"
                        >
                            <option v-for="version in preset.versions" :key="version.id" :value="version.id">{{ version.id }}</option>
                        </select>
                        <ul class="my-4 space-y-2 text-[11px] leading-5 text-zinc-500">
                            <li v-for="warning in preset.warnings" :key="warning">{{ warning }}</li>
                        </ul>
                        <button
                            :disabled="busy || !canInstall"
                            class="mt-auto flex items-center justify-center gap-2 rounded-button bg-hive px-4 py-3 text-xs font-bold text-black disabled:opacity-50"
                            @click="review(preset)"
                        >
                            <LoaderCircle v-if="busy" class="size-4 animate-spin" /><DownloadCloud v-else class="size-4" />Review installation
                        </button>
                    </article>
                </div>
                <p v-if="!filtered.length && !error" class="p-6 text-center text-xs text-zinc-500">
                    No preinstalls are available for this selection.
                </p>
                <p v-if="!canInstall" class="text-xs text-zinc-500">
                    Installing requires file upload, write and delete permissions, plus permission to change startup settings.
                </p>
            </template>
        </main>
        <ConfirmationModal
            :open="confirm"
            :title="`Install ${preview?.plan?.name ?? 'application'}?`"
            description="This replaces the files in your Cell and changes its runtime and startup command. Previous files are retained in the Cell’s installation backup. Stop your Cell first. Configure private startup variables after installation."
            confirm-text="Replace files and install"
            danger
            :loading="busy"
            @cancel="confirm = false"
            @confirm="install"
        />
    </AppLayout>
</template>
