<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import CellHeader from '@/components/cells/CellHeader.vue'
import StatChartCard from '@/components/cells/StatChartCard.vue'
import { Head } from '@inertiajs/vue3'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import { Clock3, Network, Cpu, MemoryStick, HardDrive, ArrowDownUp, Server, Globe2, Sparkles, X, Terminal, FileText, ArrowUp, ShieldCheck, Copy, Check, LoaderCircle } from 'lucide-vue-next'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

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
const aiDialog = ref(false)
const aiBusy = ref(false)
const aiAnswer = ref('')
const formattedAiAnswer = computed(() => {
    if (!aiAnswer.value) return ''
    return DOMPurify.sanitize(marked.parse(aiAnswer.value, { breaks: true, gfm: true }) as string)
})
const aiQuestion = ref('')
const aiCopied = ref(false)
const aiSuggestions = ['Explain these errors', 'Why did my server crash?', 'Suggest fixes']

async function copyAiAnswer() {
    if (!aiAnswer.value) return
    try {
        await navigator.clipboard.writeText(aiAnswer.value)
        aiCopied.value = true
        window.setTimeout(() => { aiCopied.value = false }, 1800)
    } catch {
        aiCopied.value = false
    }
}
const consoleHiddenUntil = ref(0)
const visibleConsoleLines = computed(() => consoleLines.value.slice(consoleHiddenUntil.value))

let detachedConsole: Window | null = null

function syncDetachedConsole() {
    if (!detachedConsole || detachedConsole.closed) {
        detachedConsole = null
        return
    }
    const output = detachedConsole.document.getElementById('console-output')
    if (!output) return
    const atBottom = output.scrollHeight - output.scrollTop - output.clientHeight < 80
    output.textContent = visibleConsoleLines.value
        .map(line => `${formatConsoleTime(line.timestamp)}  ${line.message}`)
        .join('\n')
    if (atBottom) output.scrollTop = output.scrollHeight
}

function popOutConsole() {
    if (detachedConsole && !detachedConsole.closed) {
        detachedConsole.focus()
        return
    }
    const popup = window.open('', `hivepanel-console-${cellId.value}`, 'width=1100,height=720,resizable=yes,scrollbars=yes')
    if (!popup) {
        window.alert('Allow pop-ups for HivePanel to open the console in a separate window.')
        return
    }
    detachedConsole = popup
    // No server text is written as HTML; it is inserted using textContent.
    popup.document.open()
    popup.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>HivePanel Console</title><style>
        *{box-sizing:border-box}body{margin:0;background:#101114;color:#e4e4e7;font:13px ui-monospace,SFMono-Regular,Consolas,monospace}
        main{height:100vh;display:flex;flex-direction:column;padding:14px;gap:10px}
        header{display:flex;justify-content:space-between;align-items:center;font:600 14px system-ui;color:#f4f4f5}
        #console-output{flex:1;overflow:auto;background:#050505;border:1px solid #27272a;border-radius:8px;padding:16px;white-space:pre-wrap;overflow-wrap:anywhere;user-select:text;line-height:1.6}
        form{display:flex;gap:10px}input{flex:1;min-width:0;background:#18181b;color:white;border:1px solid #3f3f46;border-radius:8px;padding:12px}button{border:1px solid #3f3f46;border-radius:8px;background:#27272a;color:white;padding:10px 15px;cursor:pointer}
        </style></head><body><main><header><span>HivePanel · Console</span><span id="connection-state"></span></header><div id="console-output"></div><form id="command-form"><input id="command-input" placeholder="Enter a command..." autocomplete="off"><button type="submit">Send</button></form></main></body></html>`)
    popup.document.close()
    const form = popup.document.getElementById('command-form') as HTMLFormElement | null
    form?.addEventListener('submit', (event) => {
        event.preventDefault()
        const input = popup.document.getElementById('command-input') as HTMLInputElement | null
        if (!input || !input.value.trim() || !canUseRuntime.value || isLocked.value) return
        command.value = input.value
        input.value = ''
        void sendCommand()
    })
    syncDetachedConsole()
}

watch(visibleConsoleLines, () => syncDetachedConsole(), { deep: true })

function clearVisibleConsole() {
    consoleHiddenUntil.value = consoleLines.value.length
}

async function askConsoleAI() {
    if (!cellId.value || aiBusy.value) return
    aiBusy.value = true
    aiAnswer.value = ''
    try {
        const response = await fetch(route('cells.console-ai', cellId.value), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                lines: visibleConsoleLines.value.slice(-100).map(line => line.message.slice(0, 2000)),
                question: aiQuestion.value,
            }),
        })
        const result = await response.json()
        aiAnswer.value = response.ok ? result.answer : (result.message ?? 'AI request failed.')
    } catch {
        aiAnswer.value = 'Could not contact HivePanel AI.'
    } finally {
        aiBusy.value = false
    }
}

function exportConsole() {
    const text = visibleConsoleLines.value.map(line => `[${formatConsoleTime(line.timestamp)}] ${line.message}`).join('\n')
    const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `hivepanel-console-${cellId.value}.log`
    link.click()
    URL.revokeObjectURL(url)
}


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
    if (detachedConsole && !detachedConsole.closed) detachedConsole.close()
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

        <div class="min-h-screen min-w-0 max-w-full bg-surface-dark text-white">
            <main class="min-w-0 max-w-full px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto w-full min-w-0 max-w-full space-y-5">
                    <CellHeader
                        :cell="cell"
                        :current-status="currentStatus"
                        @start="startCell"
                        @restart="restartCell"
                        @stop="stopCell"
                    />

                    <div class="grid min-w-0 max-w-full gap-4 xl:grid-cols-[minmax(0,1fr)_355px]">
                        <div class="min-w-0 space-y-4">
                            <section
                                class="min-w-0 max-w-full overflow-hidden rounded-panel border border-zinc-800 bg-surface shadow-[0_0_30px_rgba(0,0,0,0.25)]"
                            >
                                <div class="min-w-0 p-3">
                                    <div class="relative min-w-0 max-w-full">
                                        <div class="absolute right-3 top-2 z-20 flex items-center gap-1 font-sans">
                                            <button type="button" class="rounded p-1.5 text-zinc-500 transition hover:text-white focus-visible:outline focus-visible:outline-hive" title="Pop out console" aria-label="Pop out console" @click="popOutConsole">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6"/></svg>
                                            </button>
                                            <button type="button" class="rounded p-1.5 text-zinc-500 transition hover:text-white focus-visible:outline focus-visible:outline-hive" title="Fullscreen console" aria-label="Fullscreen console" @click="consolePoppedOut = true">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M3 16v3a2 2 0 0 0 2 2h3M21 16v3a2 2 0 0 1-2 2h-3"/></svg>
                                            </button>
                                        </div>
                                        <div
                                            ref="consoleEl"
                                            class="h-[320px] w-full min-w-0 max-w-full overflow-auto rounded-button border border-zinc-800 bg-black p-4 text-[12px] leading-6 text-zinc-300 sm:h-[385px] sm:p-6 sm:text-[13px] font-mono"
                                        >
                                        <div
                                            v-if="visibleConsoleLines.length === 0"
                                            class="text-zinc-600"
                                        >
                                            No console output yet.
                                        </div>

                                        <div
                                            v-for="(line, index) in visibleConsoleLines"
                                            :key="index"
                                            :class="consoleLineClass(line.message)"
                                        >
                                            <span class="mr-3 select-text text-zinc-600">{{ formatConsoleTime(line.timestamp) }}</span><span>{{ line.message }}</span>
                                        </div>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-3 rounded-button border border-zinc-800 bg-surface transition focus-within:border-hive"
                                    >
                                        <div
                                            class="flex items-center gap-3 px-4 py-3 sm:gap-4 sm:px-5 sm:py-4"
                                        >
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

                                            <div class="flex shrink-0 items-center gap-1.5 font-sans">
                                                <button type="button" class="rounded-md border border-zinc-700 px-2 py-1.5 text-xs text-zinc-300 transition hover:border-hive hover:text-hive" @click="aiDialog = true">Ask AI</button>
                                                <button type="button" class="rounded-md border border-zinc-700 px-2 py-1.5 text-xs text-zinc-300 transition hover:border-hive hover:text-hive" title="Download console log" @click="exportConsole">Export</button>
                                                <button type="button" class="rounded p-1.5 text-zinc-500 transition hover:text-red-400" title="Clear console display" aria-label="Clear console display" @click="clearVisibleConsole">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v5M14 11v5"/></svg>
                                                </button>
                                            </div>

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

                            <div class="grid min-w-0 max-w-full gap-4 md:grid-cols-3">
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

                        <aside class="min-w-0 space-y-4">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Server Information
                                </h2>

                                <div class="mt-5 space-y-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-800 bg-zinc-800/40 text-zinc-400">
                                            <Clock3 :size="17" :stroke-width="1.75" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-zinc-500">Uptime</div>
                                            <div class="text-sm font-semibold tabular-nums text-zinc-100">{{ formatUptime(liveStats.uptime_sec) }}</div>
                                        </div>
                                    </div>

                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-800 bg-zinc-800/40 text-zinc-400">
                                            <Network :size="17" :stroke-width="1.75" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-zinc-500">Address</div>
                                            <div class="break-all text-sm font-semibold tabular-nums text-zinc-100">{{ cell.allocation?.ip ?? '—' }}:{{ cell.allocation?.port ?? '—' }}</div>
                                        </div>
                                    </div>

                                    <div class="border-t border-zinc-800/70" />

                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-800 bg-zinc-800/40 text-zinc-400">
                                            <Cpu :size="17" :stroke-width="1.75" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-zinc-500">CPU Load</div>
                                            <div class="text-sm font-semibold tabular-nums text-zinc-100">{{ (liveStats.cpu ?? 0).toFixed(2) }}%</div>
                                        </div>
                                    </div>

                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-800 bg-zinc-800/40 text-zinc-400">
                                            <MemoryStick :size="17" :stroke-width="1.75" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-zinc-500">Memory</div>
                                            <div class="text-sm font-semibold tabular-nums text-zinc-100">{{ formatMemoryUsed() }} / {{ formatMemoryLimit() }}</div>
                                        </div>
                                    </div>

                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-800 bg-zinc-800/40 text-zinc-400">
                                            <HardDrive :size="17" :stroke-width="1.75" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-zinc-500">Disk</div>
                                            <div class="text-sm font-semibold tabular-nums text-zinc-100">{{ formatDiskUsed() }}</div>
                                        </div>
                                    </div>

                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-800 bg-zinc-800/40 text-zinc-400">
                                            <ArrowDownUp :size="17" :stroke-width="1.75" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-zinc-500">Network</div>
                                            <div class="text-sm font-semibold tabular-nums text-zinc-100">↓ {{ formatBytes(liveStats.network_rx_bytes) }} / ↑ {{ formatBytes(liveStats.network_tx_bytes) }}</div>
                                        </div>
                                    </div>

                                    <div class="border-t border-zinc-800/70" />

                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-800 bg-zinc-800/40 text-zinc-400">
                                            <Server :size="17" :stroke-width="1.75" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-zinc-500">Node</div>
                                            <div class="break-all text-sm font-semibold text-zinc-100">{{ cell.node?.name ?? '—' }}</div>
                                        </div>
                                    </div>

                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-800 bg-zinc-800/40 text-zinc-400">
                                            <Globe2 :size="17" :stroke-width="1.75" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs text-zinc-500">UTC Time</div>
                                            <div class="font-mono text-sm font-semibold tabular-nums text-zinc-100">{{ formatUtcTime() }}</div>
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
            v-if="aiDialog"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
            @keydown.esc="aiDialog = false"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="hive-ai-title"
                class="flex w-full max-w-[1100px] flex-col overflow-hidden rounded-2xl border border-zinc-800 bg-[#101216] shadow-2xl"
                :class="aiBusy || aiAnswer ? 'h-[min(90vh,960px)]' : 'h-auto max-h-[90vh]'"
            >
                <div class="flex shrink-0 items-center gap-3 border-b border-zinc-800/80 px-5 py-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-orange-500/20 bg-orange-500/10 text-orange-400">
                        <Sparkles :size="20" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 id="hive-ai-title" class="text-base font-semibold text-zinc-100">Hive AI</h2>
                        <p class="text-xs text-zinc-500">Console assistant</p>
                    </div>
                    <button type="button" aria-label="Close AI assistant" class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-800 hover:text-white" @click="aiDialog = false">
                        <X :size="18" />
                    </button>
                </div>

                <div class="min-h-0 space-y-4 overflow-y-auto px-5 py-5 lg:px-7"
                    :class="aiBusy || aiAnswer ? 'flex-1' : ''">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs text-zinc-300">
                            <Terminal :size="13" class="shrink-0" /><span class="truncate">{{ cell.name }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs text-zinc-400">
                            <FileText :size="13" /> Last {{ Math.min(100, visibleConsoleLines.length) }} console lines
                        </span>
                    </div>

                    <div class="rounded-xl border border-zinc-800 bg-[#0b0d10] p-3 focus-within:border-orange-500/50">
                        <label for="hive-ai-question" class="mb-2 block text-xs font-medium text-zinc-400">What would you like help with?</label>
                        <textarea
                            id="hive-ai-question"
                            v-model="aiQuestion"
                            rows="2"
                            class="block w-full resize-y border-0 bg-transparent p-0 text-sm leading-6 text-zinc-100 outline-none placeholder:text-zinc-600 focus:ring-0"
                            placeholder="What caused my server to stop during startup?"
                            @keydown.ctrl.enter.prevent="askConsoleAI"
                            @keydown.meta.enter.prevent="askConsoleAI"
                        />
                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                :disabled="aiBusy || visibleConsoleLines.length === 0 || !aiQuestion.trim()"
                                class="inline-flex items-center gap-2 rounded-lg bg-hive px-3 py-2 text-xs font-semibold text-black transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="askConsoleAI"
                            >
                                <LoaderCircle v-if="aiBusy" :size="15" class="animate-spin" />
                                <ArrowUp v-else :size="15" />
                                {{ aiBusy ? 'Analysing…' : 'Ask Hive AI' }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="suggestion in aiSuggestions"
                            :key="suggestion"
                            type="button"
                            class="rounded-full border border-zinc-800 px-3 py-1.5 text-xs text-zinc-400 transition hover:border-orange-500/40 hover:text-zinc-100"
                            @click="aiQuestion = suggestion"
                        >{{ suggestion }}</button>
                    </div>

                    <div v-if="aiBusy" role="status" class="flex items-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 text-sm text-zinc-400">
                        <LoaderCircle :size="16" class="animate-spin text-orange-400" /> Analysing console output…
                    </div>
                    <section v-if="aiAnswer" class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900/40">
                        <div class="flex items-center justify-between border-b border-zinc-800 px-4 py-3">
                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-200"><Sparkles :size="15" class="text-orange-400" /> AI response</span>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-zinc-400 hover:bg-zinc-800 hover:text-white" @click="copyAiAnswer">
                                <Check v-if="aiCopied" :size="14" /><Copy v-else :size="14" />{{ aiCopied ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                        <div class="hive-ai-markdown min-w-0 break-words px-5 py-5 text-sm leading-7 text-zinc-300 select-text" v-html="formattedAiAnswer"></div>
                    </section>

                    <div class="flex items-start gap-2 rounded-xl border border-zinc-800/70 bg-zinc-900/40 p-3">
                        <ShieldCheck :size="16" class="mt-0.5 shrink-0 text-zinc-400" />
                        <p class="text-xs leading-5 text-zinc-500">The latest console output is sent with your question to the configured AI provider. Check logs for secrets before submitting.</p>
                    </div>
                </div>
            </div>
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
                        v-for="(line, index) in visibleConsoleLines"
                        :key="index"
                        :class="consoleLineClass(line.message)"
                    >
                        <span class="mr-3 select-text text-zinc-600">{{ formatConsoleTime(line.timestamp) }}</span><span>{{ line.message }}</span>
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
<style scoped>
.hive-ai-markdown :deep(h1),
.hive-ai-markdown :deep(h2),
.hive-ai-markdown :deep(h3),
.hive-ai-markdown :deep(h4) { color: #f4f4f5; font-weight: 650; line-height: 1.35; margin: 1.5rem 0 .65rem; }
.hive-ai-markdown :deep(h1) { font-size: 1.45rem; }
.hive-ai-markdown :deep(h2) { font-size: 1.25rem; }
.hive-ai-markdown :deep(h3) { font-size: 1.08rem; }
.hive-ai-markdown :deep(h1:first-child),
.hive-ai-markdown :deep(h2:first-child),
.hive-ai-markdown :deep(h3:first-child) { margin-top: 0; }
.hive-ai-markdown :deep(p) { margin: .65rem 0 1rem; }
.hive-ai-markdown :deep(strong) { color: #fafafa; font-weight: 650; }
.hive-ai-markdown :deep(ul) { list-style: disc; padding-left: 1.5rem; margin: .75rem 0 1rem; }
.hive-ai-markdown :deep(ol) { list-style: decimal; padding-left: 1.5rem; margin: .75rem 0 1rem; }
.hive-ai-markdown :deep(li) { padding-left: .15rem; margin: .3rem 0; }
.hive-ai-markdown :deep(li > p) { margin: .25rem 0; }
.hive-ai-markdown :deep(a) { color: #fb923c; text-decoration: underline; overflow-wrap: anywhere; }
.hive-ai-markdown :deep(blockquote) { border-left: 3px solid #f97316; padding: .35rem 1rem; margin: 1rem 0; color: #a1a1aa; background: #18181b; }
.hive-ai-markdown :deep(code) { background: #27272a; color: #fdba74; border-radius: .3rem; padding: .1rem .35rem; font-size: .88em; overflow-wrap: anywhere; }
.hive-ai-markdown :deep(pre) { overflow-x: auto; max-width: 100%; background: #09090b; border: 1px solid #3f3f46; border-radius: .65rem; padding: 1rem; margin: 1rem 0; line-height: 1.6; }
.hive-ai-markdown :deep(pre code) { background: transparent; color: #e4e4e7; padding: 0; border-radius: 0; overflow-wrap: normal; }
.hive-ai-markdown :deep(table) { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; margin: 1rem 0; }
.hive-ai-markdown :deep(th),
.hive-ai-markdown :deep(td) { border: 1px solid #3f3f46; padding: .55rem .8rem; text-align: left; }
.hive-ai-markdown :deep(th) { background: #27272a; color: #fafafa; }
.hive-ai-markdown :deep(hr) { border: 0; border-top: 1px solid #3f3f46; margin: 1.5rem 0; }
</style>
