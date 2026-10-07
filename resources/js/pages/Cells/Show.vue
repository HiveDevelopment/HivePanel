<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import CellHeader from '@/components/cells/CellHeader.vue'
import StatChartCard from '@/components/cells/StatChartCard.vue'
import { Head } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'

type CellStatus = 'offline' | 'starting' | 'running' | 'stopping'

type CellInstallStatus =
    | 'pending'
    | 'installing'
    | 'installed'
    | 'failed'
    | null

const props = defineProps<{
    cell: any
    stats: any
    console_ws_url?: string | null
}>()

const liveStats = ref({ ...props.stats })
type ConsoleEntry = {
    timestamp: string | null
    message: string
}

const consoleLines = ref<ConsoleEntry[]>([])
const command = ref('')
const consoleEl = ref<HTMLElement | null>(null)
const popoutConsoleEl = ref<HTMLElement | null>(null)

const consolePoppedOut = ref(false)

type ChartPoint = {
    x: number
    y: number
}

const cpuHistory = ref<ChartPoint[]>([])
const memoryHistory = ref<ChartPoint[]>([])
const networkRxHistory = ref<ChartPoint[]>([])
const networkTxHistory = ref<ChartPoint[]>([])

const socketStatus = ref<
    'connecting' |
    'connected' |
    'disconnected'
>('disconnected')

const cellId = computed(() => props.cell?.id ?? null)
const cellDaemonId = computed(() => props.cell?.daemon_id ?? null)
const isLocked = computed(() => props.cell?.lock?.locked === true)

const installStatus = computed<CellInstallStatus>(() => {
    const status =
        props.cell?.install_status ??
        props.cell?.installation_status ??
        null

    if (
        status === 'pending' ||
        status === 'installing' ||
        status === 'installed' ||
        status === 'failed'
    ) {
        return status
    }

    return null
})

/**
 * Runtime functionality should not be contacted until
 * installation has completed.
 *
 * null is allowed for backwards compatibility with older
 * Cell payloads that do not expose install_status.
 */
const canUseRuntime = computed(() => {
    return (
        installStatus.value !== 'pending' &&
        installStatus.value !== 'installing' &&
        installStatus.value !== 'failed'
    )
})

const currentStatus = computed<CellStatus>(() => {
    if (!canUseRuntime.value) {
        return 'offline'
    }

    if (liveStats.value?.running === true) {
        return 'running'
    }

    if (props.cell?.status) {
        return normaliseStatus(props.cell.status)
    }

    return 'offline'
})

const commandSuggestions = [
    'help',
    'list',
    'stop',
    'restart',
    'say ',
    'broadcast ',
    'op ',
    'deop ',
    'ban ',
    'pardon ',
    'kick ',
    'whitelist add ',
    'whitelist remove ',
    'gamemode survival ',
    'gamemode creative ',
    'time set day',
    'time set night',
    'weather clear',
    'save-all',
]

const filteredCommandSuggestions = computed(() => {
    const value = command.value
        .trim()
        .toLowerCase()

    if (!value) {
        return []
    }

    return commandSuggestions
        .filter((suggestion) =>
            suggestion
                .toLowerCase()
                .startsWith(value),
        )
        .slice(0, 6)
})

let pollTimer: number | undefined
let consolePollTimer: number | undefined
let statsLoading = false
let consoleLoading = false

async function refreshStats() {
    if (
        !cellId.value ||
        !canUseRuntime.value ||
        statsLoading
    ) {
        return
    }

    statsLoading = true

    try {
        const response = await fetch(
            `/cells/${cellId.value}/stats-json`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        )

        if (!response.ok) {
            return
        }

        const data = await response.json()

        liveStats.value = data

        pushHistory(
            cpuHistory.value,
            data.cpu ?? 0,
        )

        pushHistory(
            memoryHistory.value,
            data.memory_mb ?? 0,
        )

        pushHistory(
            networkRxHistory.value,
            data.network_rx_bytes ?? 0,
        )

        pushHistory(
            networkTxHistory.value,
            data.network_tx_bytes ?? 0,
        )
    } catch {
        // A temporary Worker/network failure should not
        // break the Cell page.
    } finally {
        statsLoading = false
    }
}

function localConsoleEntry(message: string): ConsoleEntry {
    return {
        timestamp: new Date().toISOString(),
        message,
    }
}

function normaliseConsoleEntry(line: unknown): ConsoleEntry {
    if (typeof line === 'string') {
        return {
            timestamp: null,
            message: line,
        }
    }

    if (line && typeof line === 'object') {
        const value = line as Record<string, unknown>

        return {
            timestamp:
                typeof value.timestamp === 'string'
                    ? value.timestamp
                    : null,
            message:
                typeof value.message === 'string'
                    ? value.message
                    : String(value.line ?? ''),
        }
    }

    return {
        timestamp: null,
        message: String(line ?? ''),
    }
}

function formatConsoleTime(timestamp: string | null) {
    if (!timestamp) {
        return '--:--:--'
    }

    const date = new Date(timestamp)

    if (Number.isNaN(date.getTime())) {
        return '--:--:--'
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(date)
}

function consoleIsNearBottom(element: HTMLElement | null) {
    if (!element) {
        return true
    }

    return element.scrollHeight - element.scrollTop - element.clientHeight < 80
}

async function scrollConsoleToBottom() {
    await nextTick()

    if (consoleEl.value) {
        consoleEl.value.scrollTop = consoleEl.value.scrollHeight
    }

    if (popoutConsoleEl.value) {
        popoutConsoleEl.value.scrollTop = popoutConsoleEl.value.scrollHeight
    }
}

function setOfflineConsoleMessage() {
    if (!canUseRuntime.value) {
        if (installStatus.value === 'failed') {
            consoleLines.value = [localConsoleEntry('container@hivepanel~ Installation failed. Runtime unavailable.')]
            return
        }

        if (
            installStatus.value === 'pending' ||
            installStatus.value === 'installing'
        ) {
            consoleLines.value = [localConsoleEntry('container@hivepanel~ Installation in progress...')]
            return
        }
    }

    if (consoleLines.value.length === 0) {
        consoleLines.value = [localConsoleEntry('container@hivepanel~ Server marked as offline...')]
    }
}

async function refreshConsole() {
    if (
        !cellId.value ||
        !canUseRuntime.value ||
        consoleLoading
    ) {
        return
    }

    consoleLoading = true

    const mainWasNearBottom = consoleIsNearBottom(consoleEl.value)
    const popoutWasNearBottom = consoleIsNearBottom(popoutConsoleEl.value)

    try {
        const response = await fetch(
            route('cells.console-json', cellId.value),
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        )

        if (!response.ok) {
            socketStatus.value = 'disconnected'
            return
        }

        const data = await response.json()
        const nextLines = Array.isArray(data?.lines)
            ? data.lines.map(normaliseConsoleEntry).slice(-500)
            : []

        socketStatus.value = 'connected'

        const currentSignature = JSON.stringify(consoleLines.value)
        const nextSignature = JSON.stringify(nextLines)

        if (currentSignature === nextSignature) {
            return
        }

        consoleLines.value = nextLines
        await nextTick()

        if (mainWasNearBottom && consoleEl.value) {
            consoleEl.value.scrollTop = consoleEl.value.scrollHeight
        }

        if (popoutWasNearBottom && popoutConsoleEl.value) {
            popoutConsoleEl.value.scrollTop = popoutConsoleEl.value.scrollHeight
        }
    } catch {
        socketStatus.value = 'disconnected'
    } finally {
        consoleLoading = false
    }
}

function startConsolePolling() {
    if (consolePollTimer !== undefined) {
        return
    }

    socketStatus.value = 'connecting'
    void refreshConsole()

    consolePollTimer = window.setInterval(() => {
        void refreshConsole()
    }, 1000)
}

function stopConsolePolling() {
    if (consolePollTimer !== undefined) {
        window.clearInterval(consolePollTimer)
        consolePollTimer = undefined
    }

    socketStatus.value = 'disconnected'
}

async function sendCommand() {
    if (!canUseRuntime.value) {
        consoleLines.value.push(localConsoleEntry('[error] Runtime unavailable while installation is incomplete.'))
        return
    }

    if (isLocked.value) {
        consoleLines.value.push(localConsoleEntry('[error] Server is locked. Commands are disabled.'))
        return
    }

    const value = command.value.trim()
    if (!value || !cellId.value) {
        return
    }

    try {
        const response = await fetch(
            route('cells.command', cellId.value),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ command: value }),
            },
        )

        if (!response.ok) {
            consoleLines.value.push(localConsoleEntry(`[error] Command failed (${response.status}).`))
            return
        }

        command.value = ''
        window.setTimeout(() => void refreshConsole(), 100)
    } catch {
        consoleLines.value.push(localConsoleEntry('[error] Failed to send command through HivePanel.'))
    }
}

function pushHistory(
    history: ChartPoint[],
    value: number,
) {
    history.push({
        x: Date.now(),
        y: Number(value || 0),
    })

    if (history.length > 60) {
        history.shift()
    }
}

function applyCommandSuggestion(
    suggestion: string,
) {
    command.value = suggestion
}

function clearConsole() {
    if (!canUseRuntime.value) {
        setOfflineConsoleMessage()
        return
    }

    if (!liveStats.value?.running) {
        setOfflineConsoleMessage()
        return
    }

    consoleLines.value = []
}

function consoleLineClass(
    line: string,
) {
    const value = line.toLowerCase()

    if (
        value.includes('error') ||
        value.includes('exception') ||
        value.includes('failed') ||
        value.includes('failure') ||
        value.includes('severe') ||
        value.includes('fatal')
    ) {
        return 'font-bold text-status-danger'
    }

    if (
        value.includes('warn') ||
        value.includes('warning') ||
        value.includes('deprecated') ||
        value.includes('timed out')
    ) {
        return 'font-bold text-status-warning'
    }

    if (
        value.includes('done') ||
        value.includes('started') ||
        value.includes('running') ||
        value.includes('success') ||
        value.includes('online')
    ) {
        return 'font-bold text-status-success'
    }

    if (
        value.includes('container@') ||
        value.includes('hivepanel') ||
        value.includes('pterodactyl')
    ) {
        return 'font-bold text-hive'
    }

    return 'text-zinc-300'
}

onMounted(async () => {
    /*
     * Pending/installing/failed Cells have no usable
     * runtime. Do not start stats polling or connect
     * to their console.
     */
    if (!canUseRuntime.value) {
        setOfflineConsoleMessage()
        return
    }

    consoleLines.value = [localConsoleEntry('container@hivepanel~ Console attached. Waiting for output...')]

    startConsolePolling()

    /*
     * Five seconds is sufficient for runtime stats.
     *
     * Console output is relayed through HivePanel once per second,
     * so Workers do not need a browser-accessible WebSocket endpoint.
     */
    pollTimer = window.setInterval(
        async () => {
            if (!canUseRuntime.value) {
                return
            }

            const wasRunning =
                liveStats.value?.running === true

            await refreshStats()

            const isRunning =
                liveStats.value?.running === true

            if (!wasRunning && isRunning) {
                startConsolePolling()
            }
        },
        5000,
    )
})

onUnmounted(() => {
    if (pollTimer !== undefined) {
        window.clearInterval(
            pollTimer,
        )

        pollTimer = undefined
    }

    stopConsolePolling()
})

function normaliseStatus(
    status?: string,
): CellStatus {
    if (
        status === 'running' ||
        status === 'starting' ||
        status === 'stopping'
    ) {
        return status
    }

    return 'offline'
}

function formatBytes(
    bytes?: number,
) {
    const value = bytes ?? 0

    if (
        value >=
        1024 * 1024 * 1024
    ) {
        return `${(
            value /
            1024 /
            1024 /
            1024
        ).toFixed(2)} GB`
    }

    if (
        value >=
        1024 * 1024
    ) {
        return `${(
            value /
            1024 /
            1024
        ).toFixed(2)} MB`
    }

    if (value >= 1024) {
        return `${(
            value / 1024
        ).toFixed(2)} KB`
    }

    return `${value} B`
}

function formatMemoryUsed() {
    const used =
        liveStats.value?.memory_mb ?? 0

    if (used >= 1024) {
        return `${(
            used / 1024
        ).toFixed(2)} GB`
    }

    return `${used.toFixed(2)} MB`
}

function formatMemoryLimit() {
    const limit =
        props.cell?.limits?.memory_mb ?? 0

    if (limit <= 0) {
        return 'Unlimited'
    }

    if (limit >= 1024) {
        return `${(
            limit / 1024
        ).toFixed(0)} GB`
    }

    return `${limit} MB`
}

function formatDiskUsed() {
    const usedMb =
        liveStats.value?.disk_mb ??
        liveStats.value?.disk_usage_mb ??
        0

    if (usedMb >= 1024) {
        return `${(
            usedMb / 1024
        ).toFixed(2)} GB`
    }

    return `${Number(
        usedMb,
    ).toFixed(2)} MB`
}

function formatUptime(
    seconds?: number,
) {
    const total =
        seconds ??
        liveStats.value?.uptime_sec ??
        0

    const days =
        Math.floor(total / 86400)

    const hours =
        Math.floor(
            (total % 86400) / 3600,
        )

    const minutes =
        Math.floor(
            (total % 3600) / 60,
        )

    if (days > 0) {
        return `${days}d ${hours}h ${minutes}m`
    }

    if (hours > 0) {
        return `${hours}h ${minutes}m`
    }

    return `${minutes}m`
}

function formatUtcTime() {
    return new Intl.DateTimeFormat(
        'en-GB',
        {
            timeZone: 'UTC',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        },
    ).format(new Date())
}

async function startCell() {
    if (
        !cellId.value ||
        !canUseRuntime.value
    ) {
        return
    }

    consoleLines.value.push(
        localConsoleEntry('container@hivepanel~ Server marked as starting...'),
    )

    startConsolePolling()

    await fetch(
        route(
            'cells.start',
            cellId.value,
        ),
        {
            method: 'POST',

            headers: {
                Accept: 'application/json',

                'X-CSRF-TOKEN':
                    document
                        .querySelector(
                            'meta[name="csrf-token"]',
                        )
                        ?.getAttribute('content') ??
                    '',
            },
        },
    )

    await refreshStats()

    window.setTimeout(() => {
        startConsolePolling()
    }, 1000)
}

async function stopCell() {
    if (
        !cellId.value ||
        !canUseRuntime.value
    ) {
        return
    }

    consoleLines.value.push(localConsoleEntry('container@hivepanel~ Stopping server...'))

    await fetch(
        route(
            'cells.stop',
            cellId.value,
        ),
        {
            method: 'POST',

            headers: {
                Accept: 'application/json',

                'X-CSRF-TOKEN':
                    document
                        .querySelector(
                            'meta[name="csrf-token"]',
                        )
                        ?.getAttribute('content') ??
                    '',
            },
        },
    )

    stopConsolePolling()

    window.setTimeout(
        async () => {
            await refreshStats()
            setOfflineConsoleMessage()
        },
        1000,
    )
}

function restartCell() {
    if (
        !cellId.value ||
        !canUseRuntime.value
    ) {
        return
    }

    void stopCell()

    window.setTimeout(() => {
        void startCell()
    }, 1500)
}
</script>

<template>
    <AppLayout
        :active-cell="cell"
        :active-cell-status="currentStatus"
        :context="'server'"
    >
        <Head :title="cell.name" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <CellHeader
                        :cell="cell"
                        :current-status="currentStatus"
                        @start="startCell"
                        @restart="restartCell"
                        @stop="stopCell"
                    />

                    <div class="grid gap-4 xl:grid-cols-[1fr_355px]">
                        <div class="space-y-4">
                            <section
                                class="overflow-hidden rounded-panel border border-zinc-800 bg-surface shadow-[0_0_30px_rgba(0,0,0,0.25)]"
                            >
                                <div class="p-3">
                                    <div
                                        ref="consoleEl"
                                        class="relative h-[320px] overflow-y-auto rounded-button border border-zinc-800 bg-black p-4 text-[12px] leading-6 text-zinc-300 sm:h-[385px] sm:p-6 sm:text-[13px] font-mono"
                                    >
                                        <div
                                            class="absolute right-3 top-3 z-10 flex gap-2 sm:right-4 sm:top-4"
                                        >
                                            <button
                                                class="rounded-button border border-zinc-800 bg-surface-light px-3 py-2 font-sans text-xs font-bold text-zinc-200 transition hover:border-hive hover:text-hive"
                                                @click="consolePoppedOut = true"
                                            >
                                                ⛶
                                            </button>

                                            <button
                                                class="rounded-button border border-zinc-800 bg-surface-light px-3 py-2 font-sans text-xs font-bold text-zinc-200 transition hover:border-hive hover:text-hive"
                                                @click="clearConsole"
                                            >
                                                ♲
                                            </button>
                                        </div>

                                        <div
                                            v-if="consoleLines.length === 0"
                                            class="text-zinc-600"
                                        >
                                            No console output yet.
                                        </div>

                                        <div
                                            v-for="(line, index) in consoleLines"
                                            :key="index"
                                            :class="consoleLineClass(line.message)"
                                        >
                                            <span class="mr-3 select-none text-zinc-600">{{ formatConsoleTime(line.timestamp) }}</span><span>{{ line.message }}</span>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-3 rounded-button border border-zinc-800 bg-surface transition focus-within:border-hive"
                                    >
                                        <div
                                            class="flex items-center gap-3 px-4 py-3 sm:gap-4 sm:px-5 sm:py-4"
                                        >
                                            <span class="text-xl text-hive">
                                                ⌬
                                            </span>

                                            <input
                                                v-model="command"
                                                :disabled="isLocked || !canUseRuntime"
                                                class="w-full bg-transparent font-mono text-sm text-zinc-300 outline-none placeholder:text-zinc-600 disabled:cursor-not-allowed disabled:text-zinc-600"
                                                :placeholder="
                                                    !canUseRuntime
                                                        ? 'Runtime unavailable until installation completes'
                                                        : isLocked
                                                            ? 'Server locked - commands disabled'
                                                            : 'Enter a command...'
                                                "
                                                @keydown.enter.prevent="sendCommand"
                                            />

                                            <button
                                                class="text-xl text-zinc-300 transition hover:translate-x-0.5 hover:text-hive disabled:cursor-not-allowed disabled:text-zinc-700 disabled:hover:translate-x-0"
                                                :disabled="isLocked || !canUseRuntime"
                                                @click="sendCommand"
                                            >
                                                ➤
                                            </button>
                                        </div>

                                        <div
                                            v-if="filteredCommandSuggestions.length && canUseRuntime"
                                            class="border-t border-zinc-800"
                                        >
                                            <button
                                                v-for="suggestion in filteredCommandSuggestions"
                                                :key="suggestion"
                                                class="block w-full px-5 py-2 text-left font-mono text-sm text-zinc-400 transition hover:bg-hive/10 hover:text-hive"
                                                @click="applyCommandSuggestion(suggestion)"
                                            >
                                                {{ suggestion }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <div class="grid gap-4 xl:grid-cols-3">
                                <StatChartCard
                                    title="CPU Load"
                                    :value="`${(liveStats.cpu ?? 0).toFixed(2)}%`"
                                    :history="cpuHistory"
                                    suffix="%"
                                    :max="100"
                                />

                                <StatChartCard
                                    title="Memory"
                                    :value="formatMemoryUsed()"
                                    :history="memoryHistory"
                                    suffix=" MB"
                                    :max="cell.limits?.memory_mb ?? undefined"
                                />

                                <StatChartCard
                                    title="Network"
                                    :value="`↓ ${formatBytes(liveStats.network_rx_bytes)} / ↑ ${formatBytes(liveStats.network_tx_bytes)}`"
                                    :history="networkRxHistory"
                                    suffix=" B"
                                />
                            </div>
                        </div>

                        <aside class="space-y-4">
                            <section
                                class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                            >
                                <h2
                                    class="text-sm font-black uppercase tracking-wide text-zinc-400"
                                >
                                    Server Information
                                </h2>

                                <div class="mt-6 space-y-5">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-button bg-hive/10 text-xl text-hive shadow-hive-soft"
                                        >
                                            ◷
                                        </div>

                                        <div>
                                            <div class="text-sm text-zinc-500">
                                                Uptime
                                            </div>

                                            <div class="text-lg font-black">
                                                {{ formatUptime(liveStats.uptime_sec) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-button bg-surface-light text-xl text-zinc-200"
                                        >
                                            ⌁
                                        </div>

                                        <div>
                                            <div class="text-sm text-zinc-500">
                                                Address
                                            </div>

                                            <div class="break-all text-lg font-black">
                                                {{ cell.allocation?.ip ?? '127.0.0.1' }}:{{ cell.allocation?.port ?? '25565' }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-button bg-hive/10 text-xl text-hive"
                                        >
                                            ▥
                                        </div>

                                        <div>
                                            <div class="text-sm text-zinc-500">
                                                CPU Load
                                            </div>

                                            <div class="text-lg font-black">
                                                {{ (liveStats.cpu ?? 0).toFixed(2) }}%
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-button bg-hive/10 text-xl text-hive"
                                        >
                                            ▣
                                        </div>

                                        <div>
                                            <div class="text-sm text-zinc-500">
                                                Memory
                                            </div>

                                            <div class="text-lg font-black">
                                                {{ formatMemoryUsed() }} / {{ formatMemoryLimit() }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-button bg-hive/10 text-xl text-hive"
                                        >
                                            ▰
                                        </div>

                                        <div>
                                            <div class="text-sm text-zinc-500">
                                                Disk
                                            </div>

                                            <div class="text-lg font-black">
                                                {{ formatDiskUsed() }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-button bg-surface-light text-xl text-zinc-200"
                                        >
                                            ⇅
                                        </div>

                                        <div>
                                            <div class="text-sm text-zinc-500">
                                                Network
                                            </div>

                                            <div class="text-sm font-black">
                                                ↓ {{ formatBytes(liveStats.network_rx_bytes) }}
                                                /
                                                ↑ {{ formatBytes(liveStats.network_tx_bytes) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-button bg-surface-light text-xl text-zinc-200"
                                        >
                                            ▤
                                        </div>

                                        <div>
                                            <div class="text-sm text-zinc-500">
                                                Node
                                            </div>

                                            <div class="text-lg font-black">
                                                {{ cell.node?.name ?? 'worker-01' }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-button bg-surface-light text-xl text-zinc-200"
                                        >
                                            🕒
                                        </div>

                                        <div>
                                            <div class="text-sm text-zinc-500">
                                                UTC Time
                                            </div>

                                            <div class="font-mono text-lg font-black">
                                                {{ formatUtcTime() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>
            </main>
        </div>

        <div
            v-if="consolePoppedOut"
            class="fixed inset-0 z-50 bg-black/80 p-4 backdrop-blur-sm"
        >
            <div
                class="flex h-full flex-col rounded-panel border border-zinc-800 bg-surface p-4"
            >
                <div
                    class="mb-3 flex items-center justify-between gap-4"
                >
                    <div>
                        <h2 class="text-lg font-black text-white">
                            {{ cell.name }} Console
                        </h2>

                        <p class="text-sm text-zinc-500">
                            Socket: {{ socketStatus }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <button
                            class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                            @click="consolePoppedOut = false"
                        >
                            Close
                        </button>
                    </div>
                </div>

                <div
                    ref="popoutConsoleEl"
                    class="flex-1 overflow-y-auto rounded-button border border-zinc-800 bg-black p-5 text-sm leading-6 font-mono"
                >
                    <div
                        v-for="(line, index) in consoleLines"
                        :key="index"
                        :class="consoleLineClass(line.message)"
                    >
                        <span class="mr-3 select-none text-zinc-600">{{ formatConsoleTime(line.timestamp) }}</span><span>{{ line.message }}</span>
                    </div>
                </div>

                <div
                    class="mt-3 rounded-button border border-zinc-800 bg-surface transition focus-within:border-hive"
                >
                    <div class="flex items-center gap-3 px-4 py-3">
                        <span class="text-xl text-hive">
                            ⌬
                        </span>

                        <input
                            v-model="command"
                            :disabled="!canUseRuntime || isLocked"
                            class="w-full bg-transparent font-mono text-sm text-zinc-300 outline-none placeholder:text-zinc-600 disabled:cursor-not-allowed disabled:text-zinc-600"
                            :placeholder="
                                !canUseRuntime
                                    ? 'Runtime unavailable until installation completes'
                                    : isLocked
                                        ? 'Server locked - commands disabled'
                                        : 'Enter a command...'
                            "
                            @keydown.enter.prevent="sendCommand"
                        />

                        <button
                            class="text-xl text-zinc-300 transition hover:translate-x-0.5 hover:text-hive disabled:cursor-not-allowed disabled:text-zinc-700"
                            :disabled="!canUseRuntime || isLocked"
                            @click="sendCommand"
                        >
                            ➤
                        </button>
                    </div>

                    <div
                        v-if="filteredCommandSuggestions.length && canUseRuntime"
                        class="border-t border-zinc-800"
                    >
                        <button
                            v-for="suggestion in filteredCommandSuggestions"
                            :key="suggestion"
                            class="block w-full px-5 py-2 text-left font-mono text-sm text-zinc-400 transition hover:bg-hive/10 hover:text-hive"
                            @click="applyCommandSuggestion(suggestion)"
                        >
                            {{ suggestion }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>