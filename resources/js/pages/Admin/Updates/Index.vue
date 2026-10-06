<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { AlertCircle, CheckCircle2, Clock3, Download, RefreshCw, ShieldCheck } from 'lucide-vue-next'
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

const busy = computed(() => ['queued', 'backing_up', 'pulling', 'migrating', 'restarting'].includes(status.value.state))
const updateAvailable = computed(() => Boolean(props.latestRelease?.available))

function openInstallModal() {
    if (!props.canInstallUpdates || !props.latestRelease || busy.value) return
    modalOpen.value = true
}

function installUpdate() {
    if (!props.latestRelease) return

    installing.value = true
    router.post('/admin/updates/install', { version: props.latestRelease.version }, {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false
            status.value = { state: 'queued', version: props.latestRelease?.version, message: 'Update queued for the host updater.' }
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
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
        if (!response.ok) return
        status.value = await response.json()

        if (status.value.state === 'complete' || status.value.state === 'failed') {
            stopPolling()
            if (status.value.state === 'complete') window.setTimeout(() => window.location.reload(), 1200)
        }
    } catch {
        // The panel can briefly disappear while containers are replaced.
    }
}

function startPolling() {
    if (pollTimer !== null) return
    pollTimer = window.setInterval(refreshStatus, 2500)
}

function stopPolling() {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer)
        pollTimer = null
    }
}

if (busy.value) startPolling()
onBeforeUnmount(stopPolling)

const flashSuccess = computed(() => (page.props.flash as any)?.success as string | undefined)
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="HivePanel Updates" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto max-w-5xl space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-hive/20 bg-hive/10">
                                    <RefreshCw class="size-5 text-hive" />
                                </div>
                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">HivePanel Updates</h1>
                                    <p class="mt-2 text-sm text-zinc-400">Check for new HivePanel releases and install them when you are ready.</p>
                                </div>
                            </div>

                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-right">
                                <div class="text-[10px] font-black uppercase tracking-wider text-zinc-600">Installed</div>
                                <div class="mt-1 font-mono text-sm font-black text-white">v{{ currentVersion }}</div>
                            </div>
                        </div>
                    </section>

                    <div v-if="flashSuccess" class="rounded-panel border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm font-bold text-emerald-300">
                        {{ flashSuccess }}
                    </div>

                    <div v-if="checkError" class="rounded-panel border border-amber-500/20 bg-amber-500/5 p-4 text-sm text-amber-200">
                        {{ checkError }}
                    </div>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <CheckCircle2 v-if="!updateAvailable" class="size-5 text-emerald-400" />
                                    <Download v-else class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">{{ updateAvailable ? 'Update available' : 'You’re up to date' }}</h2>
                                </div>

                                <template v-if="latestRelease">
                                    <div class="mt-4 flex flex-wrap items-center gap-3">
                                        <span class="rounded-full border border-zinc-800 bg-[#0d0f11] px-3 py-1 text-xs font-black text-zinc-300">Latest v{{ latestRelease.version }}</span>
                                        <span v-if="latestRelease.published_at" class="text-xs text-zinc-600">Published {{ new Date(latestRelease.published_at).toLocaleString() }}</span>
                                    </div>
                                    <p v-if="latestRelease.body" class="mt-4 whitespace-pre-line text-sm leading-6 text-zinc-400">{{ latestRelease.body }}</p>
                                </template>
                                <p v-else-if="!checkError" class="mt-3 text-sm text-zinc-500">No stable HivePanel release is currently available.</p>
                            </div>

                            <button
                                v-if="updateAvailable"
                                type="button"
                                :disabled="!canInstallUpdates || busy"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="openInstallModal"
                            >
                                <Download class="size-4" />
                                {{ busy ? 'Update in progress' : 'Install update' }}
                            </button>
                        </div>

                        <div v-if="updateAvailable && !canInstallUpdates" class="mt-5 flex items-start gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                            <ShieldCheck class="mt-0.5 size-4 shrink-0 text-zinc-500" />
                            <p class="text-sm text-zinc-400">You can view available updates, but your account does not have permission to install them.</p>
                        </div>
                    </section>

                    <section v-if="status.state !== 'idle'" class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex items-start gap-3">
                            <RefreshCw v-if="busy" class="mt-0.5 size-5 animate-spin text-hive" />
                            <CheckCircle2 v-else-if="status.state === 'complete'" class="mt-0.5 size-5 text-emerald-400" />
                            <AlertCircle v-else class="mt-0.5 size-5 text-red-400" />
                            <div>
                                <h2 class="font-black">Update status</h2>
                                <p class="mt-1 text-sm text-zinc-400">{{ status.message || status.state }}</p>
                                <div v-if="status.version" class="mt-3 inline-flex items-center gap-2 text-xs text-zinc-600">
                                    <Clock3 class="size-3.5" />
                                    Target v{{ status.version }}
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
            :description="`Update HivePanel from v${currentVersion} to v${latestRelease?.version}? A database backup will be created first and the panel may be unavailable briefly while the containers are replaced.`"
            confirm-text="Install update"
            cancel-text="Cancel"
            :loading="installing"
            @cancel="modalOpen = false"
            @confirm="installUpdate"
        />
    </AppLayout>
</template>
