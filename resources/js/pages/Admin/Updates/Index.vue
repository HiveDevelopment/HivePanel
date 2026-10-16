<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import DOMPurify from 'dompurify';
import {
    AlertCircle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Download,
    ExternalLink,
    LockKeyhole,
    MonitorCog,
    RefreshCw,
    Rocket,
    Search,
    Server,
    Wifi,
    WifiOff,
} from 'lucide-vue-next';
import { marked } from 'marked';
import { computed, onBeforeUnmount, ref } from 'vue';

interface Release {
    version: string;
    name: string;
    body: string;
    published_at: string | null;
    url: string | null;
    available: boolean;
}

interface UpdateStatus {
    state: 'idle' | 'queued' | 'backing_up' | 'pulling' | 'migrating' | 'restarting' | 'complete' | 'failed';
    version?: string;
    message?: string;
    updated_at?: string;
    backup?: string;
}

interface WorkerUpdateItem {
    id: string;
    name: string;
    hostname: string | null;
    platform: string | null;
    version: string | null;
    latest_version: string | null;
    online: boolean;
    outdated: boolean;
    version_available: boolean;
    last_seen_at: string | null;
}

interface WorkerPagination {
    data: WorkerUpdateItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface WorkerSummary {
    total: number;
    up_to_date: number;
    outdated: number;
    offline: number;
    unknown: number;
}

interface WorkerFilters {
    search: string;
    status: 'all' | 'outdated' | 'current' | 'offline' | 'unknown';
}

interface WorkerUpdateStatusItem {
    id: number;
    node_id: string;
    node_name: string | null;
    from_version: string | null;
    target_version: string;
    current_version: string | null;
    status: 'queued' | 'dispatching' | 'restarting' | 'complete' | 'failed';
    error: string | null;
    started_at: string | null;
    dispatched_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
}

type WorkerModalMode = 'single' | 'selected' | 'all';

const props = defineProps<{
    currentVersion: string;
    latestRelease: Release | null;
    updateStatus: UpdateStatus;
    canInstallUpdates: boolean;
    checkError: string | null;
    latestWorkerVersion: string | null;
    workers: WorkerPagination;
    workerSummary: WorkerSummary;
    workerFilters: WorkerFilters;
}>();

const modalOpen = ref(false);
const installing = ref(false);
const status = ref<UpdateStatus>(props.updateStatus);
const page = usePage();
const workerSearch = ref(props.workerFilters.search);
const workerStatus = ref<WorkerFilters['status']>(props.workerFilters.status);
const selectedWorkers = ref<string[]>([]);
const workerUpdateStatuses = ref<Record<string, WorkerUpdateStatusItem>>({});
const workerModalOpen = ref(false);
const workerModalMode = ref<WorkerModalMode>('single');
const workerModalWorker = ref<WorkerUpdateItem | null>(null);
const workerSubmitting = ref(false);

let pollTimer: number | null = null;
let workerPollTimer: number | null = null;
let workerSearchTimer: number | null = null;

marked.setOptions({ breaks: true, gfm: true });

const busy = computed(() => ['queued', 'backing_up', 'pulling', 'migrating', 'restarting'].includes(status.value.state));
const updateAvailable = computed(() => Boolean(props.latestRelease?.available));
const flashSuccess = computed(() => (page.props.flash as any)?.success as string | undefined);
const flashError = computed(() => (page.props.flash as any)?.error as string | undefined);

const statusTitle = computed(() => {
    switch (status.value.state) {
        case 'queued':
            return 'Update queued';
        case 'backing_up':
            return 'Creating backup';
        case 'pulling':
            return 'Downloading update';
        case 'migrating':
            return 'Updating database';
        case 'restarting':
            return 'Restarting HivePanel';
        case 'complete':
            return 'Update complete';
        case 'failed':
            return 'Update failed';
        default:
            return 'Update status';
    }
});

const formattedReleaseDate = computed(() => {
    if (!props.latestRelease?.published_at) return null;
    return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(props.latestRelease.published_at));
});

const releaseNotesHtml = computed(() => {
    if (!props.latestRelease?.body) return '';
    return DOMPurify.sanitize(marked.parse(props.latestRelease.body) as string, { USE_PROFILES: { html: true } });
});

const selectableWorkers = computed(() => props.workers.data.filter((worker) => canUpdateWorker(worker)));
const allVisibleSelectableSelected = computed(
    () => selectableWorkers.value.length > 0 && selectableWorkers.value.every((worker) => selectedWorkers.value.includes(worker.id)),
);

const workerModalTitle = computed(() => {
    if (workerModalMode.value === 'single') return `Update ${workerModalWorker.value?.name ?? 'Worker'}`;
    if (workerModalMode.value === 'selected')
        return `Update ${selectedWorkers.value.length} selected Worker${selectedWorkers.value.length === 1 ? '' : 's'}`;
    return 'Update all outdated Workers';
});

const workerModalDescription = computed(() => {
    const target = formatWorkerVersion(props.latestWorkerVersion);
    if (workerModalMode.value === 'single' && workerModalWorker.value) {
        return `Update ${workerModalWorker.value.name} from ${formatWorkerVersion(workerModalWorker.value.version)} to ${target}? The Worker will restart briefly. Running Cells are not restarted by this action.`;
    }
    if (workerModalMode.value === 'selected') {
        return `Queue updates for ${selectedWorkers.value.length} selected Worker${selectedWorkers.value.length === 1 ? '' : 's'} to ${target}? Each Worker will restart briefly as its update is installed.`;
    }
    return `Queue an update to ${target} for every eligible outdated online Worker? Updates are processed through the Worker update queue with controlled concurrency.`;
});

function openInstallModal() {
    if (!props.canInstallUpdates || !props.latestRelease || busy.value) return;
    modalOpen.value = true;
}

function installUpdate() {
    if (!props.latestRelease) return;
    installing.value = true;
    router.post(
        '/admin/updates/install',
        { version: props.latestRelease.version },
        {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                status.value = {
                    state: 'queued',
                    version: props.latestRelease?.version,
                    message: 'The update has been queued and will begin shortly.',
                };
                startPolling();
            },
            onFinish: () => {
                installing.value = false;
            },
        },
    );
}

async function refreshStatus() {
    try {
        const response = await fetch('/admin/updates/status', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) return;
        status.value = await response.json();
        if (status.value.state === 'complete' || status.value.state === 'failed') {
            stopPolling();
            if (status.value.state === 'complete') window.setTimeout(() => window.location.reload(), 1200);
        }
    } catch {
        /* HivePanel may briefly become unavailable while its containers restart. */
    }
}

function startPolling() {
    if (pollTimer !== null) return;
    pollTimer = window.setInterval(refreshStatus, 2500);
}

function stopPolling() {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function refreshWorkerUpdateStatuses() {
    try {
        const response = await fetch('/admin/updates/workers/status', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) return;
        const payload = (await response.json()) as { updates?: WorkerUpdateStatusItem[] };
        const next: Record<string, WorkerUpdateStatusItem> = {};
        for (const update of payload.updates ?? []) {
            if (!next[update.node_id]) next[update.node_id] = update;
        }
        workerUpdateStatuses.value = next;
        const hasActive = Object.values(next).some((update) => ['queued', 'dispatching', 'restarting'].includes(update.status));
        if (!hasActive) stopWorkerPolling();
        router.reload({ only: ['workers', 'workerSummary', 'latestWorkerVersion'], preserveScroll: true });
    } catch {
        /* A Worker may be restarting; keep the current UI state. */
    }
}

function startWorkerPolling() {
    if (workerPollTimer !== null) return;
    refreshWorkerUpdateStatuses();
    workerPollTimer = window.setInterval(refreshWorkerUpdateStatuses, 2500);
}

function stopWorkerPolling() {
    if (workerPollTimer !== null) {
        window.clearInterval(workerPollTimer);
        workerPollTimer = null;
    }
}

function workerUpdateFor(worker: WorkerUpdateItem): WorkerUpdateStatusItem | undefined {
    return workerUpdateStatuses.value[worker.id];
}

function workerUpdateActive(worker: WorkerUpdateItem): boolean {
    const update = workerUpdateFor(worker);
    return Boolean(update && ['queued', 'dispatching', 'restarting'].includes(update.status));
}

function workerUpdateLabel(worker: WorkerUpdateItem): string {
    const update = workerUpdateFor(worker);
    if (!update) return 'Update';
    switch (update.status) {
        case 'queued':
            return 'Queued';
        case 'dispatching':
            return 'Dispatching';
        case 'restarting':
            return 'Restarting';
        case 'failed':
            return 'Retry';
        default:
            return 'Update';
    }
}

function canUpdateWorker(worker: WorkerUpdateItem): boolean {
    return (
        props.canInstallUpdates &&
        worker.online &&
        worker.outdated &&
        worker.version_available &&
        Boolean(worker.latest_version) &&
        !workerUpdateActive(worker)
    );
}

function openWorkerUpdateModal(worker: WorkerUpdateItem) {
    if (!canUpdateWorker(worker)) return;
    workerModalMode.value = 'single';
    workerModalWorker.value = worker;
    workerModalOpen.value = true;
}

function openSelectedWorkerModal() {
    if (!props.canInstallUpdates || selectedWorkers.value.length === 0) return;
    workerModalMode.value = 'selected';
    workerModalWorker.value = null;
    workerModalOpen.value = true;
}

function openAllWorkerModal() {
    if (!props.canInstallUpdates || props.workerSummary.outdated === 0) return;
    workerModalMode.value = 'all';
    workerModalWorker.value = null;
    workerModalOpen.value = true;
}

function closeWorkerModal() {
    if (workerSubmitting.value) return;
    workerModalOpen.value = false;
    workerModalWorker.value = null;
}

function confirmWorkerUpdate() {
    let url = '';
    let data: Record<string, unknown> = {};
    if (workerModalMode.value === 'single') {
        if (!workerModalWorker.value) return;
        url = `/admin/updates/workers/${workerModalWorker.value.id}`;
    } else if (workerModalMode.value === 'selected') {
        if (selectedWorkers.value.length === 0) return;
        url = '/admin/updates/workers-selected';
        data = { nodes: selectedWorkers.value };
    } else {
        url = '/admin/updates/workers-outdated';
    }

    workerSubmitting.value = true;
    router.post(url, data, {
        preserveScroll: true,
        onSuccess: () => {
            workerModalOpen.value = false;
            workerModalWorker.value = null;
            selectedWorkers.value = [];
            startWorkerPolling();
        },
        onFinish: () => {
            workerSubmitting.value = false;
        },
    });
}

function toggleWorkerSelection(worker: WorkerUpdateItem) {
    if (!canUpdateWorker(worker)) return;
    selectedWorkers.value = selectedWorkers.value.includes(worker.id)
        ? selectedWorkers.value.filter((id) => id !== worker.id)
        : [...selectedWorkers.value, worker.id];
}

function toggleAllVisibleWorkers() {
    const ids = selectableWorkers.value.map((worker) => worker.id);
    if (allVisibleSelectableSelected.value) {
        selectedWorkers.value = selectedWorkers.value.filter((id) => !ids.includes(id));
    } else {
        selectedWorkers.value = Array.from(new Set([...selectedWorkers.value, ...ids]));
    }
}

function refreshWorkers() {
    selectedWorkers.value = [];
    router.get(
        '/admin/updates',
        {
            worker_search: workerSearch.value.trim() || undefined,
            worker_status: workerStatus.value !== 'all' ? workerStatus.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['workers', 'workerSummary', 'workerFilters', 'latestWorkerVersion'],
        },
    );
}

function handleWorkerSearch() {
    if (workerSearchTimer !== null) window.clearTimeout(workerSearchTimer);
    workerSearchTimer = window.setTimeout(() => {
        workerSearchTimer = null;
        refreshWorkers();
    }, 350);
}

function handleWorkerStatusChange() {
    refreshWorkers();
}

function goToWorkerPage(url: string | null) {
    if (!url) return;
    selectedWorkers.value = [];
    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['workers', 'workerSummary', 'workerFilters', 'latestWorkerVersion'],
        },
    );
}

function formatWorkerVersion(version: string | null): string {
    if (!version) return 'Unknown';
    return version.startsWith('v') ? version : `v${version}`;
}

function formatLastSeen(value: string | null): string {
    if (!value) return 'Never';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

if (busy.value) startPolling();
refreshWorkerUpdateStatuses();

onBeforeUnmount(() => {
    stopPolling();
    stopWorkerPolling();
    if (workerSearchTimer !== null) {
        window.clearTimeout(workerSearchTimer);
        workerSearchTimer = null;
    }
});
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="t('Updates')" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="space-y-4">
                    <!-- Page header -->

                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-hive/10 text-hive">
                                <RefreshCw class="size-5" />
                            </div>

                            <div>
                                <h1 class="text-2xl font-black">
                                    {{ t('Updates') }}
                                </h1>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.keep_hivepanel_and_your_workers_up_to_date') }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Flash messages -->

                    <div v-if="flashSuccess" class="flex items-start gap-3 rounded-panel border border-status-success/20 bg-status-success/5 p-4">
                        <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-status-success" />

                        <p class="text-sm font-medium text-status-success">
                            {{ flashSuccess }}
                        </p>
                    </div>

                    <div v-if="flashError" class="flex items-start gap-3 rounded-panel border border-status-danger/20 bg-status-danger/5 p-4">
                        <AlertCircle class="mt-0.5 size-4 shrink-0 text-status-danger" />
                        <p class="text-sm font-medium text-status-danger">{{ flashError }}</p>
                    </div>

                    <div v-if="checkError" class="flex items-start gap-3 rounded-panel border border-status-warning/20 bg-status-warning/5 p-4">
                        <AlertCircle class="mt-0.5 size-4 shrink-0 text-status-warning" />

                        <p class="text-sm text-status-warning">
                            {{ checkError }}
                        </p>
                    </div>

                    <!-- HivePanel -->

                    <section class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div class="border-b border-border px-5 py-4 sm:px-6">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-lg bg-hive/10 text-hive">
                                    <Rocket class="size-4" />
                                </div>

                                <div>
                                    <h2 class="font-black">
                                        {{ t('ui.hivepanel_6123007') }}
                                    </h2>

                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ t('ui.version_and_update_information') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid lg:grid-cols-2">
                            <!-- Installed -->

                            <div class="border-b border-border p-5 sm:p-6 lg:border-b-0 lg:border-r">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    {{ t('ui.installed_version') }}
                                </div>

                                <div class="mt-3 flex items-center gap-3">
                                    <span class="font-mono text-2xl font-black text-foreground"> v{{ currentVersion }} </span>

                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-surface-dark px-2.5 py-1 text-[11px] font-bold text-muted-foreground"
                                    >
                                        <CheckCircle2 class="size-3 text-status-success" />

                                        {{ t('ui.installed') }}
                                    </span>
                                </div>

                                <p class="mt-3 text-sm text-muted-foreground">
                                    {{ t('ui.the_version_of_hivepanel_currently_running_on_this_server') }}
                                </p>
                            </div>

                            <!-- Latest -->

                            <div class="p-5 sm:p-6">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    {{ t('ui.latest_version') }}
                                </div>

                                <template v-if="latestRelease">
                                    <div class="mt-3 flex flex-wrap items-center gap-3">
                                        <span class="font-mono text-2xl font-black text-foreground"> v{{ latestRelease.version }} </span>

                                        <span
                                            v-if="updateAvailable"
                                            class="inline-flex items-center gap-1.5 rounded-full border border-hive/20 bg-hive/10 px-2.5 py-1 text-[11px] font-bold text-hive"
                                        >
                                            <Download class="size-3" />

                                            {{ t('ui.update_available') }}
                                        </span>

                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 rounded-full border border-status-success/20 bg-status-success/10 px-2.5 py-1 text-[11px] font-bold text-status-success"
                                        >
                                            <CheckCircle2 class="size-3" />

                                            {{ t('ui.up_to_date') }}
                                        </span>
                                    </div>

                                    <p v-if="formattedReleaseDate" class="mt-3 text-sm text-muted-foreground">
                                        {{ t('ui.released') }}

                                        {{ formattedReleaseDate }}
                                    </p>
                                </template>

                                <p v-else-if="!checkError" class="mt-3 text-sm text-muted-foreground">
                                    {{ t('ui.no_stable_hivepanel_release_is_currently_available') }}
                                </p>
                            </div>
                        </div>

                        <!-- Panel update action -->

                        <div
                            v-if="updateAvailable"
                            class="flex flex-col gap-4 border-t border-border bg-surface-dark/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >
                            <div>
                                <div class="text-sm font-bold text-foreground">
                                    {{ t('ui.a_new_version_of_hivepanel_is_available') }}
                                </div>

                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ t('ui.review_the_release_information_before_installing_the_update') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                :disabled="!canInstallUpdates || busy"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border px-4 py-2.5 text-sm font-black transition disabled:cursor-not-allowed"
                                :class="
                                    canInstallUpdates && !busy
                                        ? 'border-hive bg-hive text-black hover:bg-hive-light'
                                        : 'border-border bg-surface text-muted-foreground'
                                "
                                @click="openInstallModal"
                            >
                                <RefreshCw v-if="busy" class="size-4 animate-spin" />

                                <LockKeyhole v-else-if="!canInstallUpdates" class="size-4" />

                                <Download v-else class="size-4" />

                                {{ busy ? 'Update in progress' : canInstallUpdates ? 'Install update' : 'Permission required' }}
                            </button>
                        </div>
                    </section>

                    <!-- Permission notice -->

                    <div
                        v-if="updateAvailable && !canInstallUpdates"
                        class="flex items-start gap-3 rounded-panel border border-border bg-surface p-4"
                    >
                        <LockKeyhole class="mt-0.5 size-4 shrink-0 text-muted-foreground" />

                        <div>
                            <div class="text-sm font-bold text-foreground">
                                {{ t('ui.update_permission_required') }}
                            </div>

                            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                {{ t('ui.your_account_can_view_available_updates_but_cannot_install_them_a') }}
                            </p>
                        </div>
                    </div>

                    <!-- HiveWorker -->
                    <section class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div class="flex flex-col gap-4 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-lg bg-hive/10 text-hive">
                                    <MonitorCog class="size-4" />
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="font-black">{{ t('ui.hiveworker') }}</h2>
                                        <span
                                            v-if="latestWorkerVersion"
                                            class="rounded-full border border-border bg-surface-dark px-2.5 py-1 font-mono text-[11px] font-bold text-muted-foreground"
                                            >{{ t('ui.latest') }} {{ formatWorkerVersion(latestWorkerVersion) }}</span
                                        >
                                    </div>
                                    <p class="mt-0.5 text-xs text-muted-foreground">{{ t('ui.versions_reported_by_worker_heartbeats') }}</p>
                                </div>
                            </div>
                            <div v-if="!latestWorkerVersion" class="flex items-center gap-2 text-xs font-medium text-status-warning">
                                <AlertCircle class="size-4" />{{ t('ui.latest_version_unavailable') }}
                            </div>
                        </div>

                        <div class="grid border-b border-border sm:grid-cols-2 xl:grid-cols-4">
                            <div class="border-b border-border p-5 sm:border-r xl:border-b-0">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ t('ui.workers') }}</div>
                                <div class="mt-2 text-2xl font-black">{{ workerSummary.total }}</div>
                            </div>
                            <div class="border-b border-border p-5 xl:border-b-0 xl:border-r">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ t('ui.up_to_date') }}</div>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="text-2xl font-black">{{ workerSummary.up_to_date }}</span
                                    ><CheckCircle2 class="size-4 text-status-success" />
                                </div>
                            </div>
                            <div class="border-b border-border p-5 sm:border-b-0 sm:border-r">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    {{ t('ui.updates_available') }}
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="text-2xl font-black">{{ workerSummary.outdated }}</span
                                    ><Download v-if="workerSummary.outdated > 0" class="size-4 text-hive" />
                                </div>
                            </div>
                            <div class="p-5">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ t('ui.offline') }}</div>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="text-2xl font-black">{{ workerSummary.offline }}</span
                                    ><WifiOff v-if="workerSummary.offline > 0" class="size-4 text-status-danger" />
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-b border-border bg-surface-dark/30 p-4 xl:flex-row xl:items-center">
                            <div class="relative min-w-0 flex-1">
                                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    v-model="workerSearch"
                                    type="search"
                                    :placeholder="t('ui.search_workers')"
                                    class="h-10 w-full rounded-button border border-border bg-surface-dark pl-9 pr-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                    @input="handleWorkerSearch"
                                />
                            </div>
                            <select
                                v-model="workerStatus"
                                class="h-10 rounded-button border border-border bg-surface-dark px-3 text-sm font-medium text-foreground outline-none transition focus:border-hive/50 sm:w-52"
                                @change="handleWorkerStatusChange"
                            >
                                <option value="all">{{ t('ui.all_workers') }}</option>
                                <option value="outdated">{{ t('ui.updates_available') }}</option>
                                <option value="current">{{ t('ui.up_to_date') }}</option>
                                <option value="offline">{{ t('ui.offline') }}</option>
                                <option value="unknown">{{ t('ui.unknown_version') }}</option>
                            </select>
                            <div v-if="canInstallUpdates && workerSummary.outdated > 0" class="flex flex-wrap items-center gap-2">
                                <button
                                    v-if="selectedWorkers.length > 0"
                                    type="button"
                                    class="inline-flex h-10 items-center justify-center gap-2 rounded-button border border-border bg-surface px-3 text-xs font-black text-foreground transition hover:border-border hover:text-foreground"
                                    @click="openSelectedWorkerModal"
                                >
                                    <Download class="size-3.5" />{{ t('ui.update_selected') }}{{ selectedWorkers.length }})
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-10 items-center justify-center gap-2 rounded-button border border-hive bg-hive px-3 text-xs font-black text-black transition hover:bg-hive-light"
                                    @click="openAllWorkerModal"
                                >
                                    <Download class="size-3.5" />{{ t('ui.update_all_outdated') }}
                                </button>
                            </div>
                        </div>

                        <div v-if="workers.data.length" class="divide-y divide-border">
                            <div
                                v-if="selectableWorkers.length > 0 && canInstallUpdates"
                                class="flex items-center gap-3 bg-surface-dark/20 px-5 py-2.5 sm:px-6"
                            >
                                <input
                                    type="checkbox"
                                    :checked="allVisibleSelectableSelected"
                                    class="size-4 rounded border-border bg-surface accent-hive"
                                    :aria-label="t('ui.select_all_updateable_workers_on_this_page')"
                                    @change="toggleAllVisibleWorkers"
                                />
                                <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{
                                    t('ui.select_updateable_workers_on_this_page')
                                }}</span>
                            </div>

                            <div
                                v-for="worker in workers.data"
                                :key="worker.id"
                                class="flex flex-col gap-4 p-5 transition hover:bg-foreground/[0.015] sm:px-6 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div class="flex min-w-0 flex-1 items-start gap-3">
                                    <input
                                        v-if="canUpdateWorker(worker)"
                                        type="checkbox"
                                        :checked="selectedWorkers.includes(worker.id)"
                                        class="mt-0.5 size-4 shrink-0 rounded border-border bg-surface accent-hive"
                                        :aria-label="`Select ${worker.name}`"
                                        @change="toggleWorkerSelection(worker)"
                                    />
                                    <div v-else-if="canInstallUpdates && worker.outdated" class="mt-0.5 size-4 shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Server class="size-4 shrink-0 text-muted-foreground" /><span
                                                class="truncate text-sm font-black text-foreground"
                                                >{{ worker.name }}</span
                                            ><span
                                                v-if="worker.online"
                                                class="inline-flex items-center gap-1.5 rounded-full border border-status-success/20 bg-status-success/10 px-2 py-0.5 text-[10px] font-bold text-status-success"
                                                ><Wifi class="size-3" />{{ t('ui.online') }}</span
                                            ><span
                                                v-else
                                                class="inline-flex items-center gap-1.5 rounded-full border border-status-danger/20 bg-status-danger/10 px-2 py-0.5 text-[10px] font-bold text-status-danger"
                                                ><WifiOff class="size-3" />{{ t('ui.offline') }}</span
                                            >
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            <span v-if="worker.hostname">{{ worker.hostname }}</span
                                            ><span v-if="worker.platform">{{ worker.platform }}</span
                                            ><span>{{ t('ui.last_seen') }} {{ formatLastSeen(worker.last_seen_at) }}</span>
                                        </div>
                                        <div
                                            v-if="workerUpdateFor(worker)?.status === 'failed'"
                                            class="mt-2 flex max-w-2xl items-start gap-1.5 text-xs text-status-danger"
                                        >
                                            <AlertCircle class="mt-0.5 size-3.5 shrink-0" /><span>{{
                                                workerUpdateFor(worker)?.error || 'The Worker update failed.'
                                            }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center justify-between gap-5 lg:justify-end">
                                    <div v-if="!worker.version_available" class="lg:text-right">
                                        <div class="font-mono text-sm font-black text-muted-foreground">{{ t('ui.unknown') }}</div>
                                        <div class="mt-1 inline-flex items-center gap-1 text-[11px] font-bold text-status-warning">
                                            <AlertCircle class="size-3" />{{ t('ui.version_unavailable') }}
                                        </div>
                                    </div>
                                    <template v-else-if="worker.outdated">
                                        <div class="lg:text-right">
                                            <div class="flex items-center gap-2 font-mono text-sm font-black">
                                                <span class="text-muted-foreground">{{ formatWorkerVersion(worker.version) }}</span
                                                ><span class="text-muted-foreground">→</span
                                                ><span class="text-foreground">{{ formatWorkerVersion(worker.latest_version) }}</span>
                                            </div>
                                            <div
                                                class="mt-1 inline-flex items-center gap-1 text-[11px] font-bold"
                                                :class="
                                                    workerUpdateFor(worker)?.status === 'failed'
                                                        ? 'text-status-danger'
                                                        : workerUpdateActive(worker)
                                                          ? 'text-hive'
                                                          : worker.online
                                                            ? 'text-hive'
                                                            : 'text-muted-foreground'
                                                "
                                            >
                                                <RefreshCw v-if="workerUpdateActive(worker)" class="size-3 animate-spin" /><AlertCircle
                                                    v-else-if="workerUpdateFor(worker)?.status === 'failed'"
                                                    class="size-3"
                                                /><Download v-else class="size-3" />{{
                                                    workerUpdateActive(worker)
                                                        ? workerUpdateLabel(worker)
                                                        : workerUpdateFor(worker)?.status === 'failed'
                                                          ? 'Update failed'
                                                          : worker.online
                                                            ? 'Update available'
                                                            : 'Worker offline'
                                                }}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            :disabled="!canUpdateWorker(worker)"
                                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border px-3 py-2 text-xs font-black transition disabled:cursor-not-allowed"
                                            :class="
                                                canUpdateWorker(worker)
                                                    ? 'border-hive bg-hive text-black hover:bg-hive-light'
                                                    : 'border-border bg-surface text-muted-foreground'
                                            "
                                            @click="openWorkerUpdateModal(worker)"
                                        >
                                            <RefreshCw v-if="workerUpdateActive(worker)" class="size-3.5 animate-spin" /><LockKeyhole
                                                v-else-if="!canInstallUpdates"
                                                class="size-3.5"
                                            /><Download v-else class="size-3.5" />{{
                                                workerUpdateActive(worker)
                                                    ? workerUpdateLabel(worker)
                                                    : workerUpdateFor(worker)?.status === 'failed'
                                                      ? 'Retry'
                                                      : !worker.online
                                                        ? 'Offline'
                                                        : !canInstallUpdates
                                                          ? 'Locked'
                                                          : 'Update'
                                            }}
                                        </button>
                                    </template>
                                    <div v-else class="lg:text-right">
                                        <div class="font-mono text-sm font-black text-foreground">{{ formatWorkerVersion(worker.version) }}</div>
                                        <div class="mt-1 inline-flex items-center gap-1 text-[11px] font-bold text-status-success">
                                            <CheckCircle2 class="size-3" />{{ t('ui.up_to_date') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="px-5 py-12 text-center sm:px-6">
                            <Server class="mx-auto size-7 text-muted-foreground" />
                            <div class="mt-3 text-sm font-bold text-muted-foreground">{{ t('ui.no_workers_found') }}</div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ t('ui.no_active_workers_match_the_current_search_and_filter') }}</p>
                        </div>

                        <div
                            v-if="workers.total > 0"
                            class="flex flex-col gap-3 border-t border-border bg-surface-dark/30 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >
                            <div class="text-xs text-muted-foreground">
                                {{ t('ui.showing') }} {{ workers.from ?? 0 }}–{{ workers.to ?? 0 }} {{ t('ui.of') }} {{ workers.total }}
                                {{ t('ui.workers') }}
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    :disabled="!workers.prev_page_url"
                                    class="inline-flex size-9 items-center justify-center rounded-button border border-border bg-surface-dark text-muted-foreground transition hover:border-border hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
                                    @click="goToWorkerPage(workers.prev_page_url)"
                                >
                                    <ChevronLeft class="size-4" /></button
                                ><span class="min-w-24 text-center text-xs font-bold text-muted-foreground"
                                    >{{ t('ui.page') }} {{ workers.current_page }} {{ t('ui.of') }} {{ workers.last_page }}</span
                                ><button
                                    type="button"
                                    :disabled="!workers.next_page_url"
                                    class="inline-flex size-9 items-center justify-center rounded-button border border-border bg-surface-dark text-muted-foreground transition hover:border-border hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
                                    @click="goToWorkerPage(workers.next_page_url)"
                                >
                                    <ChevronRight class="size-4" />
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- HivePanel release notes -->

                    <section v-if="latestRelease" class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                            <div>
                                <h2 class="font-black">
                                    {{ latestRelease.name || `HivePanel v${latestRelease.version}` }}
                                </h2>

                                <p class="mt-1 text-xs text-muted-foreground">{{ t('ui.release_notes_for_v') }}{{ latestRelease.version }}</p>
                            </div>

                            <a
                                v-if="latestRelease.url"
                                :href="latestRelease.url"
                                target="\_blank"
                                rel="noopener noreferrer"
                                class="inline-flex shrink-0 items-center gap-2 text-xs font-bold text-muted-foreground transition hover:text-hive"
                            >
                                {{ t('ui.view_release') }}

                                <ExternalLink class="size-3.5" />
                            </a>
                        </div>

                        <div class="p-5 sm:p-6">
                            <div v-if="releaseNotesHtml" class="release-notes" v-html="releaseNotesHtml"></div>

                            <p v-else class="text-sm text-muted-foreground">
                                {{ t('ui.no_release_notes_were_provided_for_this_version') }}
                            </p>
                        </div>
                    </section>

                    <!-- Panel update progress -->

                    <section v-if="status.state !== 'idle'" class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div class="flex items-start gap-4 p-5 sm:p-6">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                                :class="
                                    status.state === 'failed'
                                        ? 'bg-status-danger/10 text-status-danger'
                                        : status.state === 'complete'
                                          ? 'bg-status-success/10 text-status-success'
                                          : 'bg-hive/10 text-hive'
                                "
                            >
                                <RefreshCw v-if="busy" class="size-5 animate-spin" />

                                <CheckCircle2 v-else-if="status.state === 'complete'" class="size-5" />

                                <AlertCircle v-else class="size-5" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <h2 class="font-black">
                                    {{ statusTitle }}
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ status.message || status.state }}
                                </p>

                                <div v-if="status.version" class="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                                    <Clock3 class="size-3.5" />

                                    {{ t('ui.target_version_v') }}{{ status.version }}
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="modalOpen"
            :title="t('ui.install_hivepanel_update')"
            :description="`Update HivePanel from v${currentVersion} to v${latestRelease?.version}? A database backup will be created before the update begins. HivePanel may be unavailable briefly while its services restart.`"
            :confirm-text="t('ui.install_update')"
            :cancel-text="t('Cancel')"
            :loading="installing"
            @cancel="modalOpen = false"
            @confirm="installUpdate"
        />

        <ConfirmationModal
            :open="workerModalOpen"
            :title="workerModalTitle"
            :description="workerModalDescription"
            :confirm-text="t('ui.queue_update')"
            :cancel-text="t('Cancel')"
            :loading="workerSubmitting"
            @cancel="closeWorkerModal"
            @confirm="confirmWorkerUpdate"
        />
    </AppLayout>
</template>

<style scoped>
.release-notes {
    @apply text-sm leading-7 text-muted-foreground;
}

.release-notes :deep(p) {
    @apply mb-4 last:mb-0;
}

.release-notes :deep(strong) {
    @apply font-bold text-foreground;
}

.release-notes :deep(h1) {
    @apply mb-3 mt-6 text-xl font-black text-foreground first:mt-0;
}

.release-notes :deep(h2) {
    @apply mb-3 mt-6 text-lg font-black text-foreground first:mt-0;
}

.release-notes :deep(h3) {
    @apply mb-2 mt-5 text-base font-black text-foreground first:mt-0;
}

.release-notes :deep(h4),
.release-notes :deep(h5),
.release-notes :deep(h6) {
    @apply mb-2 mt-4 text-sm font-black text-foreground first:mt-0;
}

.release-notes :deep(ul) {
    @apply my-4 list-disc space-y-1.5 pl-6;
}

.release-notes :deep(ol) {
    @apply my-4 list-decimal space-y-1.5 pl-6;
}

.release-notes :deep(li) {
    @apply pl-1;
}

.release-notes :deep(li::marker) {
    @apply text-muted-foreground;
}

.release-notes :deep(a) {
    @apply font-medium text-hive underline decoration-hive/30 underline-offset-4 transition hover:decoration-hive;
}

.release-notes :deep(code) {
    @apply rounded-md border border-border bg-black/30 px-1.5 py-0.5 font-mono text-xs text-foreground;
}

.release-notes :deep(pre) {
    @apply my-4 overflow-x-auto rounded-lg border border-border bg-surface-dark p-4;
}

.release-notes :deep(pre code) {
    @apply border-0 bg-transparent p-0 text-xs leading-6;
}

.release-notes :deep(blockquote) {
    @apply my-4 border-l-2 border-hive/40 pl-4 italic text-muted-foreground;
}

.release-notes :deep(blockquote p) {
    @apply mb-0;
}

.release-notes :deep(hr) {
    @apply my-6 border-border;
}

.release-notes :deep(table) {
    @apply my-4 w-full border-collapse text-left text-sm;
}

.release-notes :deep(th) {
    @apply border-b border-border px-3 py-2 font-bold text-foreground;
}

.release-notes :deep(td) {
    @apply border-b border-border px-3 py-2 text-muted-foreground;
}
</style>
