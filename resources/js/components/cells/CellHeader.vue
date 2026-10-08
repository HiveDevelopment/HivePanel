<script setup lang="ts">
import type { CellStatus } from '@/composables/useActiveCell'
import { computed } from 'vue'

const props = defineProps<{
    cell: any
    currentStatus: CellStatus
}>()

const emit = defineEmits<{
    start: []
    restart: []
    stop: []
}>()

const isLocked = computed(() => props.cell?.lock?.locked === true)

const powerDisabled = computed(() =>
    props.cell?.lock?.locked === true && props.cell?.lock?.disable_power === true
)

function statusLabel(status?: CellStatus) {
    switch (status) {
        case 'running':
            return 'Running'
        case 'starting':
            return 'Starting'
        case 'stopping':
            return 'Stopping'
        default:
            return 'Offline'
    }
}

function statusDotClass(status?: CellStatus) {
    switch (status) {
        case 'running':
            return 'bg-hive shadow-hive-soft animate-pulse'
        case 'starting':
        case 'stopping':
            return 'bg-status-warning shadow-hive-soft animate-pulse'
        default:
            return 'bg-status-danger shadow-[0_0_16px_rgba(239,68,68,0.75)]'
    }
}
</script>

<template>
    <section
        class="group relative overflow-hidden rounded-panel border bg-surface px-5 py-4 transition duration-300 hover:bg-surface-hover sm:px-6 sm:py-4 lg:px-7 lg:py-4"
        :class="isLocked ? 'border-status-warning/40 hover:border-status-warning/50' : 'border-zinc-800 hover:border-hive/45'"
    >
        <div
            class="absolute inset-y-0 left-0 w-1.5"
            :class="isLocked ? 'bg-status-warning' : 'bg-hive'"
        />

        <div
            class="pointer-events-none absolute inset-y-0 left-0 w-48 opacity-[0.14] transition duration-500 group-hover:opacity-[0.22] sm:w-72 lg:w-[340px]"
            style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2269.28%22 viewBox=%220 0 80 69.28%22%3E%3Cg fill=%22none%22 stroke=%22%23ff8a00%22 stroke-width=%221.3%22 opacity=%220.55%22%3E%3Cpath d=%22M20 1L40 12.5V35L20 46.5L0 35V12.5Z%22/%3E%3Cpath d=%22M60 1L80 12.5V35L60 46.5L40 35V12.5Z%22/%3E%3Cpath d=%22M40 35L60 46.5V69L40 80.5L20 69V46.5Z%22/%3E%3C/g%3E%3C/svg%3E'); background-repeat: repeat; background-size: 80px 69px;"
        />

        <div class="relative flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-5">
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold tracking-tight text-zinc-50 sm:text-2xl lg:text-[28px]">
                    {{ cell.name }}
                </h1>

                <div class="mt-1.5 flex flex-col gap-1.5 text-xs text-zinc-400 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                    <span
                        class="inline-flex items-center gap-2 text-sm font-semibold"
                        :class="currentStatus === 'running' ? 'text-hive' : 'text-zinc-400'"
                    >
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :class="statusDotClass(currentStatus)"
                        />
                        {{ statusLabel(currentStatus) }}
                    </span>

                    <span
                        v-if="isLocked"
                        class="inline-flex items-center rounded-full border border-status-warning/30 bg-status-warning/10 px-2.5 py-1 text-xs font-black text-status-warning"
                    >
                        Locked
                    </span>

                    <span class="hidden text-hive sm:inline">•</span>

                    <span>{{ cell.comb ?? 'minecraft-paper' }}</span>

                    <span class="hidden text-hive sm:inline">•</span>

                    <span class="max-w-[180px] truncate sm:max-w-[320px] lg:max-w-[420px]">
                        {{ cell.uuid ?? cell.id }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
                <button
                    class="inline-flex w-full min-w-[108px] flex-1 items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-hive-light disabled:cursor-not-allowed disabled:border-zinc-800 disabled:bg-zinc-900 disabled:text-zinc-600 sm:w-auto sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm"
                    :disabled="powerDisabled || currentStatus === 'running' || currentStatus === 'starting'"
                    @click="emit('start')"
                >
                    Start
                </button>

                <button
                    class="inline-flex w-full min-w-[108px] flex-1 items-center justify-center gap-2 rounded-button border border-zinc-700 bg-surface-light px-4 py-2.5 text-sm font-semibold text-zinc-100 transition hover:border-hive hover:text-hive disabled:cursor-not-allowed disabled:border-zinc-800 disabled:bg-zinc-900 disabled:text-zinc-600 sm:w-auto sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm"
                    :disabled="powerDisabled"
                    @click="emit('restart')"
                >
                    Restart
                </button>

                <button
                    class="inline-flex w-full min-w-[108px] flex-1 items-center justify-center gap-2 rounded-button border border-hive bg-transparent px-4 py-2.5 text-sm font-semibold text-hive transition hover:bg-hive/10 disabled:cursor-not-allowed disabled:border-zinc-800 disabled:text-zinc-600 sm:w-auto sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm"
                    :disabled="powerDisabled || currentStatus === 'offline' || currentStatus === 'stopping'"
                    @click="emit('stop')"
                >
                    Stop
                </button>
            </div>
        </div>
    </section>
</template>