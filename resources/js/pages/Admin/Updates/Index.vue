<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import {
    AlertCircle,
    CheckCircle2,
    Clock3,
    Download,
    ExternalLink,
    LockKeyhole,
    RefreshCw,
    Rocket,
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
    state: 'idle' | 'queued' | 'backing_up' | 'pulling' | 'migrating' | 'restarting' | 'complete' | 'failed'
    version?: string
    message?: string
    updated_at?: string
    backup?: string
}

const props = defineProps<{
    currentVersion: string
    latestRelease: Release | null
    updateStatus: UpdateStatus
    canInstallUpdates: boolean
    checkError: string | null
}>()

const modalOpen = ref(false)
const installing = ref(false)
const status = ref<UpdateStatus>(props.updateStatus)
const page = usePage()
let pollTimer: number | null = null

marked.setOptions({
    breaks: true,
    gfm: true,
})

const busy = computed(() => [
    'queued',
    'backing_up',
    'pulling',
    'migrating',
    'restarting',
].includes(status.value.state))

const updateAvailable = computed(() => Boolean(props.latestRelease?.available))

const flashSuccess = computed(() => {
    return (page.props.flash as any)?.success as string | undefined
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
    }).format(new Date(props.latestRelease.published_at))
})

const releaseNotesHtml = computed(() => {
    if (!props.latestRelease?.body) {
        return ''
    }

    const html = marked.parse(props.latestRelease.body) as string

    return DOMPurify.sanitize(html, {
        USE_PROFILES: {
            html: true,
        },
    })
})

function openInstallModal() {
    if (!props.canInstallUpdates || !props.latestRelease || busy.value) {
        return
    }

    modalOpen.value = true
}

function installUpdate() {
    if (!props.latestRelease) {
        return
    }

    installing.value = true

    router.post('/admin/updates/install', {
        version: props.latestRelease.version,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false
            status.value = {
                state: 'queued',
                version: props.latestRelease?.version,
                message: 'The update has been queued and will begin shortly.',
            }

            startPolling()
        },
        onFinish: () => {
            installing.value = false
        },
    })
}

async function refreshStatus() {
    try {
        const response = await fetch('/admin/updates/status', {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        })

        if (!response.ok) {
            return
        }

        status.value = await response.json()

        if (status.value.state === 'complete' || status.value.state === 'failed') {
            stopPolling()

            if (status.value.state === 'complete') {
                window.setTimeout(() => {
                    window.location.reload()
                }, 1200)
            }
        }
    } catch {
        // HivePanel may briefly become unavailable while its containers restart.
    }
}

function startPolling() {
    if (pollTimer !== null) {
        return
    }

    pollTimer = window.setInterval(refreshStatus, 2500)
}

function stopPolling() {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer)
        pollTimer = null
    }
}

if (busy.value) {
    startPolling()
}

onBeforeUnmount(stopPolling)
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Updates" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="space-y-4">
                    <!-- Page header -->
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-hive/10 text-hive">
                                <RefreshCw class="size-5" />
                            </div>

                            <div>
                                <h1 class="text-2xl font-black">
                                    Updates
                                </h1>

                                <p class="mt-1 text-sm text-zinc-500">
                                    Keep your HivePanel installation up to date.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Flash messages -->
                    <div
                        v-if="flashSuccess"
                        class="flex items-start gap-3 rounded-panel border border-emerald-500/20 bg-emerald-500/5 p-4"
                    >
                        <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-emerald-400" />

                        <p class="text-sm font-medium text-emerald-300">
                            {{ flashSuccess }}
                        </p>
                    </div>

                    <div
                        v-if="checkError"
                        class="flex items-start gap-3 rounded-panel border border-amber-500/20 bg-amber-500/5 p-4"
                    >
                        <AlertCircle class="mt-0.5 size-4 shrink-0 text-amber-400" />

                        <p class="text-sm text-amber-200">
                            {{ checkError }}
                        </p>
                    </div>

                    <!-- Version overview -->
                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 px-5 py-4 sm:px-6">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-lg bg-hive/10 text-hive">
                                    <Rocket class="size-4" />
                                </div>

                                <div>
                                    <h2 class="font-black">
                                        HivePanel
                                    </h2>

                                    <p class="mt-0.5 text-xs text-zinc-500">
                                        Version and update information
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid lg:grid-cols-2">
                            <!-- Installed -->
                            <div class="border-b border-zinc-800 p-5 sm:p-6 lg:border-b-0 lg:border-r">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-600">
                                    Installed version
                                </div>

                                <div class="mt-3 flex items-center gap-3">
                                    <span class="font-mono text-2xl font-black text-white">
                                        v{{ currentVersion }}
                                    </span>

                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-800 bg-[#0d0f11] px-2.5 py-1 text-[11px] font-bold text-zinc-400">
                                        <CheckCircle2 class="size-3 text-emerald-400" />
                                        Installed
                                    </span>
                                </div>

                                <p class="mt-3 text-sm text-zinc-500">
                                    The version of HivePanel currently running on this server.
                                </p>
                            </div>

                            <!-- Latest -->
                            <div class="p-5 sm:p-6">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-600">
                                    Latest version
                                </div>

                                <template v-if="latestRelease">
                                    <div class="mt-3 flex flex-wrap items-center gap-3">
                                        <span class="font-mono text-2xl font-black text-white">
                                            v{{ latestRelease.version }}
                                        </span>

                                        <span
                                            v-if="updateAvailable"
                                            class="inline-flex items-center gap-1.5 rounded-full border border-hive/20 bg-hive/10 px-2.5 py-1 text-[11px] font-bold text-hive"
                                        >
                                            <Download class="size-3" />
                                            Update available
                                        </span>

                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold text-emerald-400"
                                        >
                                            <CheckCircle2 class="size-3" />
                                            Up to date
                                        </span>
                                    </div>

                                    <p
                                        v-if="formattedReleaseDate"
                                        class="mt-3 text-sm text-zinc-500"
                                    >
                                        Released {{ formattedReleaseDate }}
                                    </p>
                                </template>

                                <p
                                    v-else-if="!checkError"
                                    class="mt-3 text-sm text-zinc-500"
                                >
                                    No stable HivePanel release is currently available.
                                </p>
                            </div>
                        </div>

                        <!-- Update action -->
                        <div
                            v-if="updateAvailable"
                            class="flex flex-col gap-4 border-t border-zinc-800 bg-[#0d0f11]/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >
                            <div>
                                <div class="text-sm font-bold text-white">
                                    A new version of HivePanel is available.
                                </div>

                                <p class="mt-1 text-xs text-zinc-500">
                                    Review the release information before installing the update.
                                </p>
                            </div>

                            <button
                                type="button"
                                :disabled="!canInstallUpdates || busy"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border px-4 py-2.5 text-sm font-black transition disabled:cursor-not-allowed"
                                :class="canInstallUpdates && !busy
                                    ? 'border-hive bg-hive text-black hover:bg-hive-light'
                                    : 'border-zinc-800 bg-zinc-900 text-zinc-600'"
                                @click="openInstallModal"
                            >
                                <RefreshCw
                                    v-if="busy"
                                    class="size-4 animate-spin"
                                />

                                <LockKeyhole
                                    v-else-if="!canInstallUpdates"
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
                        v-if="updateAvailable && !canInstallUpdates"
                        class="flex items-start gap-3 rounded-panel border border-zinc-800 bg-surface p-4"
                    >
                        <LockKeyhole class="mt-0.5 size-4 shrink-0 text-zinc-500" />

                        <div>
                            <div class="text-sm font-bold text-zinc-300">
                                Update permission required
                            </div>

                            <p class="mt-1 text-xs leading-5 text-zinc-500">
                                Your account can view available updates but cannot install them.
                                An administrator with update permissions must approve the installation.
                            </p>
                        </div>
                    </div>

                    <!-- Release notes -->
                    <section
                        v-if="latestRelease"
                        class="overflow-hidden rounded-panel border border-zinc-800 bg-surface"
                    >
                        <div class="flex items-center justify-between gap-4 border-b border-zinc-800 px-5 py-4 sm:px-6">
                            <div>
                                <h2 class="font-black">
                                    {{ latestRelease.name || `HivePanel v${latestRelease.version}` }}
                                </h2>

                                <p class="mt-1 text-xs text-zinc-500">
                                    Release notes for v{{ latestRelease.version }}
                                </p>
                            </div>

                            <a
                                v-if="latestRelease.url"
                                :href="latestRelease.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex shrink-0 items-center gap-2 text-xs font-bold text-zinc-500 transition hover:text-hive"
                            >
                                View release
                                <ExternalLink class="size-3.5" />
                            </a>
                        </div>

                        <div class="p-5 sm:p-6">
                            <div
                                v-if="releaseNotesHtml"
                                class="release-notes"
                                v-html="releaseNotesHtml"
                            />

                            <p
                                v-else
                                class="text-sm text-zinc-500"
                            >
                                No release notes were provided for this version.
                            </p>
                        </div>
                    </section>

                    <!-- Update progress -->
                    <section
                        v-if="status.state !== 'idle'"
                        class="overflow-hidden rounded-panel border border-zinc-800 bg-surface"
                    >
                        <div class="flex items-start gap-4 p-5 sm:p-6">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                                :class="status.state === 'failed'
                                    ? 'bg-red-500/10 text-red-400'
                                    : status.state === 'complete'
                                        ? 'bg-emerald-500/10 text-emerald-400'
                                        : 'bg-hive/10 text-hive'"
                            >
                                <RefreshCw
                                    v-if="busy"
                                    class="size-5 animate-spin"
                                />

                                <CheckCircle2
                                    v-else-if="status.state === 'complete'"
                                    class="size-5"
                                />

                                <AlertCircle
                                    v-else
                                    class="size-5"
                                />
                            </div>

                            <div class="min-w-0 flex-1">
                                <h2 class="font-black">
                                    {{ statusTitle }}
                                </h2>

                                <p class="mt-1 text-sm text-zinc-500">
                                    {{ status.message || status.state }}
                                </p>

                                <div
                                    v-if="status.version"
                                    class="mt-3 flex items-center gap-2 text-xs text-zinc-600"
                                >
                                    <Clock3 class="size-3.5" />
                                    Target version v{{ status.version }}
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