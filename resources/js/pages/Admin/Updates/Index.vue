<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
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
} from 'lucide-vue-next'
import { marked } from 'marked'
import { computed, onBeforeUnmount, ref } from 'vue'

interface Release {
    version: string
    name: string
    body: string
    published_at: string | null
    url: string | null
    available: boolean
}

interface UpdateStatus {
    state:
        | 'idle'
        | 'queued'
        | 'backing_up'
        | 'pulling'
        | 'migrating'
        | 'restarting'
        | 'complete'
        | 'failed'
    version?: string
    message?: string
    updated_at?: string
    backup?: string
}

interface WorkerUpdateItem {
    id: string
    name: string
    hostname: string | null
    platform: string | null
    version: string | null
    latest_version: string | null
    online: boolean
    outdated: boolean
    version_available: boolean
    last_seen_at: string | null
}

interface WorkerPagination {
    data: WorkerUpdateItem[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    prev_page_url: string | null
    next_page_url: string | null
}

interface WorkerSummary {
    total: number
    up_to_date: number
    outdated: number
    offline: number
    unknown: number
}

interface WorkerFilters {
    search: string
    status:
        | 'all'
        | 'outdated'
        | 'current'
        | 'offline'
        | 'unknown'
}

const props = defineProps<{
    currentVersion: string
    latestRelease: Release | null
    updateStatus: UpdateStatus
    canInstallUpdates: boolean
    checkError: string | null

    latestWorkerVersion: string | null
    workers: WorkerPagination
    workerSummary: WorkerSummary
    workerFilters: WorkerFilters
}>()

const modalOpen = ref(false)
const installing = ref(false)
const status = ref<UpdateStatus>(props.updateStatus)
const page = usePage()

const workerSearch = ref(props.workerFilters.search)

const workerStatus = ref<WorkerFilters['status']>(
    props.workerFilters.status
)

let pollTimer: number | null = null
let workerSearchTimer: number | null = null

marked.setOptions({
    breaks: true,
    gfm: true,
})

const busy = computed(() =>
    [
        'queued',
        'backing_up',
        'pulling',
        'migrating',
        'restarting',
    ].includes(status.value.state)
)

const updateAvailable = computed(() =>
    Boolean(props.latestRelease?.available)
)

const flashSuccess = computed(() => {
    return (page.props.flash as any)?.success as
        | string
        | undefined
})

const statusTitle = computed(() => {
    switch (status.value.state) {
        case 'queued':
            return 'Update queued'

        case 'backing_up':
            return 'Creating backup'

        case 'pulling':
            return 'Downloading update'

        case 'migrating':
            return 'Updating database'

        case 'restarting':
            return 'Restarting HivePanel'

        case 'complete':
            return 'Update complete'

        case 'failed':
            return 'Update failed'

        default:
            return 'Update status'
    }
})

const formattedReleaseDate = computed(() => {
    if (!props.latestRelease?.published_at) {
        return null
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(
        new Date(
            props.latestRelease.published_at
        )
    )
})

const releaseNotesHtml = computed(() => {
    if (!props.latestRelease?.body) {
        return ''
    }

    const html = marked.parse(
        props.latestRelease.body
    ) as string

    return DOMPurify.sanitize(html, {
        USE_PROFILES: {
            html: true,
        },
    })
})

function openInstallModal() {
    if (
        !props.canInstallUpdates
        || !props.latestRelease
        || busy.value
    ) {
        return
    }

    modalOpen.value = true
}

function installUpdate() {
    if (!props.latestRelease) {
        return
    }

    installing.value = true

    router.post(
        '/admin/updates/install',
        {
            version:
                props.latestRelease.version,
        },
        {
            preserveScroll: true,

            onSuccess: () => {
                modalOpen.value = false

                status.value = {
                    state: 'queued',
                    version:
                        props.latestRelease
                            ?.version,
                    message:
                        'The update has been queued and will begin shortly.',
                }

                startPolling()
            },

            onFinish: () => {
                installing.value = false
            },
        }
    )
}

async function refreshStatus() {
    try {
        const response = await fetch(
            '/admin/updates/status',
            {
                headers: {
                    Accept: 'application/json',
                },

                credentials: 'same-origin',
            }
        )

        if (!response.ok) {
            return
        }

        status.value =
            await response.json()

        if (
            status.value.state ===
                'complete'
            || status.value.state ===
                'failed'
        ) {
            stopPolling()

            if (
                status.value.state ===
                'complete'
            ) {
                window.setTimeout(() => {
                    window.location.reload()
                }, 1200)
            }
        }
    } catch {
        // HivePanel may briefly become unavailable
        // while its containers restart.
    }
}

function startPolling() {
    if (pollTimer !== null) {
        return
    }

    pollTimer = window.setInterval(
        refreshStatus,
        2500
    )
}

function stopPolling() {
    if (pollTimer !== null) {
        window.clearInterval(
            pollTimer
        )

        pollTimer = null
    }
}

function refreshWorkers() {
    router.get(
        '/admin/updates',
        {
            worker_search:
                workerSearch.value.trim()
                || undefined,

            worker_status:
                workerStatus.value !== 'all'
                    ? workerStatus.value
                    : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,

            only: [
                'workers',
                'workerSummary',
                'workerFilters',
                'latestWorkerVersion',
            ],
        }
    )
}

function handleWorkerSearch() {
    if (
        workerSearchTimer !== null
    ) {
        window.clearTimeout(
            workerSearchTimer
        )
    }

    workerSearchTimer =
        window.setTimeout(() => {
            workerSearchTimer = null
            refreshWorkers()
        }, 350)
}

function handleWorkerStatusChange() {
    refreshWorkers()
}

function goToWorkerPage(
    url: string | null
) {
    if (!url) {
        return
    }

    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,

            only: [
                'workers',
                'workerSummary',
                'workerFilters',
                'latestWorkerVersion',
            ],
        }
    )
}

function formatWorkerVersion(
    version: string | null
): string {
    if (!version) {
        return 'Unknown'
    }

    return version.startsWith('v')
        ? version
        : `v${version}`
}

function formatLastSeen(
    value: string | null
): string {
    if (!value) {
        return 'Never'
    }

    return new Intl.DateTimeFormat(
        undefined,
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        }
    ).format(
        new Date(value)
    )
}

if (busy.value) {
    startPolling()
}

onBeforeUnmount(() => {
    stopPolling()

    if (
        workerSearchTimer !== null
    ) {
        window.clearTimeout(
            workerSearchTimer
        )

        workerSearchTimer = null
    }
})
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Updates" />

        <div
            class="min-h-screen bg-surface-dark text-white"
        >
            <main
                class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8"
            >
                <div class="space-y-4">
                    <!-- Page header -->
                    <section
                        class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                    >
                        <div
                            class="flex items-center gap-3"
                        >
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-hive/10 text-hive"
                            >
                                <RefreshCw
                                    class="size-5"
                                />
                            </div>

                            <div>
                                <h1
                                    class="text-2xl font-black"
                                >
                                    Updates
                                </h1>

                                <p
                                    class="mt-1 text-sm text-zinc-500"
                                >
                                    Keep HivePanel and
                                    your Workers up to
                                    date.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Flash messages -->
                    <div
                        v-if="flashSuccess"
                        class="flex items-start gap-3 rounded-panel border border-emerald-500/20 bg-emerald-500/5 p-4"
                    >
                        <CheckCircle2
                            class="mt-0.5 size-4 shrink-0 text-emerald-400"
                        />

                        <p
                            class="text-sm font-medium text-emerald-300"
                        >
                            {{ flashSuccess }}
                        </p>
                    </div>

                    <div
                        v-if="checkError"
                        class="flex items-start gap-3 rounded-panel border border-amber-500/20 bg-amber-500/5 p-4"
                    >
                        <AlertCircle
                            class="mt-0.5 size-4 shrink-0 text-amber-400"
                        />

                        <p
                            class="text-sm text-amber-200"
                        >
                            {{ checkError }}
                        </p>
                    </div>

                    <!-- HivePanel -->
                    <section
                        class="overflow-hidden rounded-panel border border-zinc-800 bg-surface"
                    >
                        <div
                            class="border-b border-zinc-800 px-5 py-4 sm:px-6"
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg bg-hive/10 text-hive"
                                >
                                    <Rocket
                                        class="size-4"
                                    />
                                </div>

                                <div>
                                    <h2
                                        class="font-black"
                                    >
                                        HivePanel
                                    </h2>

                                    <p
                                        class="mt-0.5 text-xs text-zinc-500"
                                    >
                                        Version and
                                        update
                                        information
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="grid lg:grid-cols-2"
                        >
                            <!-- Installed -->
                            <div
                                class="border-b border-zinc-800 p-5 sm:p-6 lg:border-b-0 lg:border-r"
                            >
                                <div
                                    class="text-[11px] font-bold uppercase tracking-wider text-zinc-600"
                                >
                                    Installed version
                                </div>

                                <div
                                    class="mt-3 flex items-center gap-3"
                                >
                                    <span
                                        class="font-mono text-2xl font-black text-white"
                                    >
                                        v{{ currentVersion }}
                                    </span>

                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-zinc-800 bg-[#0d0f11] px-2.5 py-1 text-[11px] font-bold text-zinc-400"
                                    >
                                        <CheckCircle2
                                            class="size-3 text-emerald-400"
                                        />
                                        Installed
                                    </span>
                                </div>

                                <p
                                    class="mt-3 text-sm text-zinc-500"
                                >
                                    The version of
                                    HivePanel currently
                                    running on this
                                    server.
                                </p>
                            </div>

                            <!-- Latest -->
                            <div
                                class="p-5 sm:p-6"
                            >
                                <div
                                    class="text-[11px] font-bold uppercase tracking-wider text-zinc-600"
                                >
                                    Latest version
                                </div>

                                <template
                                    v-if="latestRelease"
                                >
                                    <div
                                        class="mt-3 flex flex-wrap items-center gap-3"
                                    >
                                        <span
                                            class="font-mono text-2xl font-black text-white"
                                        >
                                            v{{ latestRelease.version }}
                                        </span>

                                        <span
                                            v-if="updateAvailable"
                                            class="inline-flex items-center gap-1.5 rounded-full border border-hive/20 bg-hive/10 px-2.5 py-1 text-[11px] font-bold text-hive"
                                        >
                                            <Download
                                                class="size-3"
                                            />
                                            Update available
                                        </span>

                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold text-emerald-400"
                                        >
                                            <CheckCircle2
                                                class="size-3"
                                            />
                                            Up to date
                                        </span>
                                    </div>

                                    <p
                                        v-if="formattedReleaseDate"
                                        class="mt-3 text-sm text-zinc-500"
                                    >
                                        Released
                                        {{ formattedReleaseDate }}
                                    </p>
                                </template>

                                <p
                                    v-else-if="!checkError"
                                    class="mt-3 text-sm text-zinc-500"
                                >
                                    No stable HivePanel
                                    release is currently
                                    available.
                                </p>
                            </div>
                        </div>

                        <!-- Panel update action -->
                        <div
                            v-if="updateAvailable"
                            class="flex flex-col gap-4 border-t border-zinc-800 bg-[#0d0f11]/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >
                            <div>
                                <div
                                    class="text-sm font-bold text-white"
                                >
                                    A new version of
                                    HivePanel is
                                    available.
                                </div>

                                <p
                                    class="mt-1 text-xs text-zinc-500"
                                >
                                    Review the release
                                    information before
                                    installing the
                                    update.
                                </p>
                            </div>

                            <button
                                type="button"
                                :disabled="
                                    !canInstallUpdates
                                    || busy
                                "
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border px-4 py-2.5 text-sm font-black transition disabled:cursor-not-allowed"
                                :class="
                                    canInstallUpdates
                                    && !busy
                                        ? 'border-hive bg-hive text-black hover:bg-hive-light'
                                        : 'border-zinc-800 bg-zinc-900 text-zinc-600'
                                "
                                @click="
                                    openInstallModal
                                "
                            >
                                <RefreshCw
                                    v-if="busy"
                                    class="size-4 animate-spin"
                                />

                                <LockKeyhole
                                    v-else-if="
                                        !canInstallUpdates
                                    "
                                    class="size-4"
                                />

                                <Download
                                    v-else
                                    class="size-4"
                                />

                                {{
                                    busy
                                        ? 'Update in progress'
                                        : canInstallUpdates
                                            ? 'Install update'
                                            : 'Permission required'
                                }}
                            </button>
                        </div>
                    </section>

                    <!-- Permission notice -->
                    <div
                        v-if="
                            updateAvailable
                            && !canInstallUpdates
                        "
                        class="flex items-start gap-3 rounded-panel border border-zinc-800 bg-surface p-4"
                    >
                        <LockKeyhole
                            class="mt-0.5 size-4 shrink-0 text-zinc-500"
                        />

                        <div>
                            <div
                                class="text-sm font-bold text-zinc-300"
                            >
                                Update permission
                                required
                            </div>

                            <p
                                class="mt-1 text-xs leading-5 text-zinc-500"
                            >
                                Your account can view
                                available updates but
                                cannot install them. An
                                administrator with
                                update permissions must
                                approve the
                                installation.
                            </p>
                        </div>
                    </div>

                    <!-- HiveWorker -->
                    <section
                        class="overflow-hidden rounded-panel border border-zinc-800 bg-surface"
                    >
                        <div
                            class="flex flex-col gap-4 border-b border-zinc-800 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg bg-hive/10 text-hive"
                                >
                                    <MonitorCog
                                        class="size-4"
                                    />
                                </div>

                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2
                                            class="font-black"
                                        >
                                            HiveWorker
                                        </h2>

                                        <span
                                            v-if="
                                                latestWorkerVersion
                                            "
                                            class="rounded-full border border-zinc-800 bg-[#0d0f11] px-2.5 py-1 font-mono text-[11px] font-bold text-zinc-400"
                                        >
                                            Latest
                                            {{
                                                formatWorkerVersion(
                                                    latestWorkerVersion
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-0.5 text-xs text-zinc-500"
                                    >
                                        Versions reported
                                        by Worker
                                        heartbeats
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="
                                    !latestWorkerVersion
                                "
                                class="flex items-center gap-2 text-xs font-medium text-amber-400"
                            >
                                <AlertCircle
                                    class="size-4"
                                />
                                Latest version
                                unavailable
                            </div>
                        </div>

                        <!-- Worker summary -->
                        <div
                            class="grid border-b border-zinc-800 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            <div
                                class="border-b border-zinc-800 p-5 sm:border-r xl:border-b-0"
                            >
                                <div
                                    class="text-[11px] font-bold uppercase tracking-wider text-zinc-600"
                                >
                                    Workers
                                </div>

                                <div
                                    class="mt-2 text-2xl font-black"
                                >
                                    {{
                                        workerSummary.total
                                    }}
                                </div>
                            </div>

                            <div
                                class="border-b border-zinc-800 p-5 xl:border-b-0 xl:border-r"
                            >
                                <div
                                    class="text-[11px] font-bold uppercase tracking-wider text-zinc-600"
                                >
                                    Up to date
                                </div>

                                <div
                                    class="mt-2 flex items-center gap-2"
                                >
                                    <span
                                        class="text-2xl font-black"
                                    >
                                        {{
                                            workerSummary.up_to_date
                                        }}
                                    </span>

                                    <CheckCircle2
                                        class="size-4 text-emerald-400"
                                    />
                                </div>
                            </div>

                            <div
                                class="border-b border-zinc-800 p-5 sm:border-b-0 sm:border-r"
                            >
                                <div
                                    class="text-[11px] font-bold uppercase tracking-wider text-zinc-600"
                                >
                                    Updates available
                                </div>

                                <div
                                    class="mt-2 flex items-center gap-2"
                                >
                                    <span
                                        class="text-2xl font-black"
                                    >
                                        {{
                                            workerSummary.outdated
                                        }}
                                    </span>

                                    <Download
                                        v-if="
                                            workerSummary.outdated
                                                > 0
                                        "
                                        class="size-4 text-hive"
                                    />
                                </div>
                            </div>

                            <div class="p-5">
                                <div
                                    class="text-[11px] font-bold uppercase tracking-wider text-zinc-600"
                                >
                                    Offline
                                </div>

                                <div
                                    class="mt-2 flex items-center gap-2"
                                >
                                    <span
                                        class="text-2xl font-black"
                                    >
                                        {{
                                            workerSummary.offline
                                        }}
                                    </span>

                                    <WifiOff
                                        v-if="
                                            workerSummary.offline
                                                > 0
                                        "
                                        class="size-4 text-red-400"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Worker toolbar -->
                        <div
                            class="flex flex-col gap-3 border-b border-zinc-800 bg-[#0d0f11]/30 p-4 sm:flex-row"
                        >
                            <div
                                class="relative min-w-0 flex-1"
                            >
                                <Search
                                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-600"
                                />

                                <input
                                    v-model="
                                        workerSearch
                                    "
                                    type="search"
                                    placeholder="Search workers..."
                                    class="h-10 w-full rounded-button border border-zinc-800 bg-[#0d0f11] pl-9 pr-3 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                                    @input="
                                        handleWorkerSearch
                                    "
                                />
                            </div>

                            <select
                                v-model="
                                    workerStatus
                                "
                                class="h-10 rounded-button border border-zinc-800 bg-[#0d0f11] px-3 text-sm font-medium text-zinc-300 outline-none transition focus:border-hive/50 sm:w-52"
                                @change="
                                    handleWorkerStatusChange
                                "
                            >
                                <option
                                    value="all"
                                >
                                    All workers
                                </option>

                                <option
                                    value="outdated"
                                >
                                    Updates available
                                </option>

                                <option
                                    value="current"
                                >
                                    Up to date
                                </option>

                                <option
                                    value="offline"
                                >
                                    Offline
                                </option>

                                <option
                                    value="unknown"
                                >
                                    Unknown version
                                </option>
                            </select>
                        </div>

                        <!-- Workers -->
                        <div
                            v-if="
                                workers.data.length
                            "
                            class="divide-y divide-zinc-800"
                        >
                            <div
                                v-for="
                                    worker in workers.data
                                "
                                :key="worker.id"
                                class="grid gap-4 p-5 transition hover:bg-white/[0.015] sm:px-6 lg:grid-cols-[minmax(0,1fr)_auto_auto] lg:items-center"
                            >
                                <!-- Worker identity -->
                                <div
                                    class="min-w-0"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Server
                                            class="size-4 shrink-0 text-zinc-600"
                                        />

                                        <span
                                            class="truncate text-sm font-black text-white"
                                        >
                                            {{
                                                worker.name
                                            }}
                                        </span>

                                        <span
                                            v-if="
                                                worker.online
                                            "
                                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-400"
                                        >
                                            <Wifi
                                                class="size-3"
                                            />
                                            Online
                                        </span>

                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 rounded-full border border-red-500/20 bg-red-500/10 px-2 py-0.5 text-[10px] font-bold text-red-400"
                                        >
                                            <WifiOff
                                                class="size-3"
                                            />
                                            Offline
                                        </span>
                                    </div>

                                    <div
                                        class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-600"
                                    >
                                        <span
                                            v-if="
                                                worker.hostname
                                            "
                                        >
                                            {{
                                                worker.hostname
                                            }}
                                        </span>

                                        <span
                                            v-if="
                                                worker.platform
                                            "
                                        >
                                            {{
                                                worker.platform
                                            }}
                                        </span>

                                        <span>
                                            Last seen
                                            {{
                                                formatLastSeen(
                                                    worker.last_seen_at
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Version -->
                                <div
                                    class="lg:min-w-52 lg:text-right"
                                >
                                    <template
                                        v-if="
                                            !worker.version_available
                                        "
                                    >
                                        <div
                                            class="font-mono text-sm font-bold text-zinc-400"
                                        >
                                            Unknown
                                        </div>

                                        <div
                                            class="mt-1 text-[11px] font-bold text-amber-400"
                                        >
                                            Version
                                            unavailable
                                        </div>
                                    </template>

                                    <template
                                        v-else-if="
                                            worker.outdated
                                        "
                                    >
                                        <div
                                            class="flex items-center gap-2 font-mono text-sm font-black lg:justify-end"
                                        >
                                            <span
                                                class="text-zinc-400"
                                            >
                                                {{
                                                    formatWorkerVersion(
                                                        worker.version
                                                    )
                                                }}
                                            </span>

                                            <span
                                                class="text-zinc-700"
                                            >
                                                →
                                            </span>

                                            <span
                                                class="text-white"
                                            >
                                                {{
                                                    formatWorkerVersion(
                                                        worker.latest_version
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            class="mt-1 text-[11px] font-bold text-hive"
                                        >
                                            Update
                                            available
                                        </div>
                                    </template>

                                    <template
                                        v-else
                                    >
                                        <div
                                            class="font-mono text-sm font-black text-white"
                                        >
                                            {{
                                                formatWorkerVersion(
                                                    worker.version
                                                )
                                            }}
                                        </div>

                                        <div
                                            class="mt-1 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-400"
                                        >
                                            <CheckCircle2
                                                class="size-3"
                                            />
                                            Up to date
                                        </div>
                                    </template>
                                </div>

                                <!-- Action -->
                                <div
                                    class="flex lg:w-28 lg:justify-end"
                                >
                                    <button
                                        v-if="
                                            worker.outdated
                                        "
                                        type="button"
                                        disabled
                                        title="Worker self-updates are not enabled yet."
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-button border border-zinc-800 bg-zinc-900 px-3 py-2 text-xs font-black text-zinc-600 disabled:cursor-not-allowed lg:w-auto"
                                    >
                                        <Download
                                            class="size-3.5"
                                        />
                                        Update
                                    </button>

                                    <span
                                        v-else
                                        class="text-xs text-zinc-700"
                                    >
                                        —
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Empty -->
                        <div
                            v-else
                            class="px-5 py-12 text-center sm:px-6"
                        >
                            <Server
                                class="mx-auto size-7 text-zinc-700"
                            />

                            <div
                                class="mt-3 text-sm font-bold text-zinc-400"
                            >
                                No Workers found
                            </div>

                            <p
                                class="mt-1 text-xs text-zinc-600"
                            >
                                No active Workers match
                                the current search and
                                filter.
                            </p>
                        </div>

                        <!-- Pagination -->
                        <div
                            v-if="
                                workers.total > 0
                            "
                            class="flex flex-col gap-3 border-t border-zinc-800 bg-[#0d0f11]/30 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >
                            <div
                                class="text-xs text-zinc-600"
                            >
                                Showing
                                {{
                                    workers.from ?? 0
                                }}
                                –
                                {{
                                    workers.to ?? 0
                                }}
                                of
                                {{
                                    workers.total
                                }}
                                Workers
                            </div>

                            <div
                                class="flex items-center gap-2"
                            >
                                <button
                                    type="button"
                                    :disabled="
                                        !workers.prev_page_url
                                    "
                                    class="inline-flex size-9 items-center justify-center rounded-button border border-zinc-800 bg-[#0d0f11] text-zinc-400 transition hover:border-zinc-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                    @click="
                                        goToWorkerPage(
                                            workers.prev_page_url
                                        )
                                    "
                                >
                                    <ChevronLeft
                                        class="size-4"
                                    />
                                </button>

                                <span
                                    class="min-w-24 text-center text-xs font-bold text-zinc-500"
                                >
                                    Page
                                    {{
                                        workers.current_page
                                    }}
                                    of
                                    {{
                                        workers.last_page
                                    }}
                                </span>

                                <button
                                    type="button"
                                    :disabled="
                                        !workers.next_page_url
                                    "
                                    class="inline-flex size-9 items-center justify-center rounded-button border border-zinc-800 bg-[#0d0f11] text-zinc-400 transition hover:border-zinc-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                    @click="
                                        goToWorkerPage(
                                            workers.next_page_url
                                        )
                                    "
                                >
                                    <ChevronRight
                                        class="size-4"
                                    />
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- HivePanel release notes -->
                    <section
                        v-if="latestRelease"
                        class="overflow-hidden rounded-panel border border-zinc-800 bg-surface"
                    >
                        <div
                            class="flex items-center justify-between gap-4 border-b border-zinc-800 px-5 py-4 sm:px-6"
                        >
                            <div>
                                <h2
                                    class="font-black"
                                >
                                    {{
                                        latestRelease.name
                                        || `HivePanel v${latestRelease.version}`
                                    }}
                                </h2>

                                <p
                                    class="mt-1 text-xs text-zinc-500"
                                >
                                    Release notes for
                                    v{{
                                        latestRelease.version
                                    }}
                                </p>
                            </div>

                            <a
                                v-if="
                                    latestRelease.url
                                "
                                :href="
                                    latestRelease.url
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex shrink-0 items-center gap-2 text-xs font-bold text-zinc-500 transition hover:text-hive"
                            >
                                View release
                                <ExternalLink
                                    class="size-3.5"
                                />
                            </a>
                        </div>

                        <div
                            class="p-5 sm:p-6"
                        >
                            <div
                                v-if="
                                    releaseNotesHtml
                                "
                                class="release-notes"
                                v-html="
                                    releaseNotesHtml
                                "
                            />

                            <p
                                v-else
                                class="text-sm text-zinc-500"
                            >
                                No release notes were
                                provided for this
                                version.
                            </p>
                        </div>
                    </section>

                    <!-- Panel update progress -->
                    <section
                        v-if="
                            status.state !== 'idle'
                        "
                        class="overflow-hidden rounded-panel border border-zinc-800 bg-surface"
                    >
                        <div
                            class="flex items-start gap-4 p-5 sm:p-6"
                        >
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                                :class="
                                    status.state ===
                                    'failed'
                                        ? 'bg-red-500/10 text-red-400'
                                        : status.state
                                              ===
                                              'complete'
                                            ? 'bg-emerald-500/10 text-emerald-400'
                                            : 'bg-hive/10 text-hive'
                                "
                            >
                                <RefreshCw
                                    v-if="busy"
                                    class="size-5 animate-spin"
                                />

                                <CheckCircle2
                                    v-else-if="
                                        status.state
                                        === 'complete'
                                    "
                                    class="size-5"
                                />

                                <AlertCircle
                                    v-else
                                    class="size-5"
                                />
                            </div>

                            <div
                                class="min-w-0 flex-1"
                            >
                                <h2
                                    class="font-black"
                                >
                                    {{
                                        statusTitle
                                    }}
                                </h2>

                                <p
                                    class="mt-1 text-sm text-zinc-500"
                                >
                                    {{
                                        status.message
                                        || status.state
                                    }}
                                </p>

                                <div
                                    v-if="
                                        status.version
                                    "
                                    class="mt-3 flex items-center gap-2 text-xs text-zinc-600"
                                >
                                    <Clock3
                                        class="size-3.5"
                                    />

                                    Target version
                                    v{{
                                        status.version
                                    }}
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="modalOpen"
            title="Install HivePanel update"
            :description="`Update HivePanel from v${currentVersion} to v${latestRelease?.version}? A database backup will be created before the update begins. HivePanel may be unavailable briefly while its services restart.`"
            confirm-text="Install update"
            cancel-text="Cancel"
            :loading="installing"
            @cancel="modalOpen = false"
            @confirm="installUpdate"
        />
    </AppLayout>
</template>

<style scoped>
.release-notes {
    @apply text-sm leading-7 text-zinc-400;
}

.release-notes :deep(p) {
    @apply mb-4 last:mb-0;
}

.release-notes :deep(strong) {
    @apply font-bold text-zinc-200;
}

.release-notes :deep(h1) {
    @apply mb-3 mt-6 text-xl font-black text-white first:mt-0;
}

.release-notes :deep(h2) {
    @apply mb-3 mt-6 text-lg font-black text-white first:mt-0;
}

.release-notes :deep(h3) {
    @apply mb-2 mt-5 text-base font-black text-white first:mt-0;
}

.release-notes :deep(h4),
.release-notes :deep(h5),
.release-notes :deep(h6) {
    @apply mb-2 mt-4 text-sm font-black text-zinc-200 first:mt-0;
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
    @apply text-zinc-600;
}

.release-notes :deep(a) {
    @apply font-medium text-hive underline decoration-hive/30 underline-offset-4 transition hover:decoration-hive;
}

.release-notes :deep(code) {
    @apply rounded-md border border-zinc-800 bg-black/30 px-1.5 py-0.5 font-mono text-xs text-zinc-300;
}

.release-notes :deep(pre) {
    @apply my-4 overflow-x-auto rounded-lg border border-zinc-800 bg-[#0d0f11] p-4;
}

.release-notes :deep(pre code) {
    @apply border-0 bg-transparent p-0 text-xs leading-6;
}

.release-notes :deep(blockquote) {
    @apply my-4 border-l-2 border-hive/40 pl-4 italic text-zinc-500;
}

.release-notes :deep(blockquote p) {
    @apply mb-0;
}

.release-notes :deep(hr) {
    @apply my-6 border-zinc-800;
}

.release-notes :deep(table) {
    @apply my-4 w-full border-collapse text-left text-sm;
}

.release-notes :deep(th) {
    @apply border-b border-zinc-700 px-3 py-2 font-bold text-zinc-200;
}

.release-notes :deep(td) {
    @apply border-b border-zinc-800 px-3 py-2 text-zinc-400;
}
</style>