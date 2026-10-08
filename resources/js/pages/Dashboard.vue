<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

type CellStatus = 'offline' | 'starting' | 'running' | 'stopping'
type Cell = {
  id: string
  name: string
  comb: string
  status?: CellStatus
  allocation?: { ip: string; port: number }
  limits?: { memory_mb: number; cpu_percent: number; disk_mb?: number }
  stats?: { cpu?: number; memory_mb?: number; disk_bytes?: number }
}
type Comb = { id: string; name: string; game: string; variables?: Record<string, string> }

const props = withDefaults(defineProps<{ cells?: Cell[]; combs?: Comb[] }>(), {
  cells: () => [],
  combs: () => [],
})
const search = ref('')
const statusFilter = ref<'all' | CellStatus>('all')
const sort = ref<'created' | 'name'>('created')
const pending = ref<Record<string, boolean>>({})

function normaliseStatus(status?: string): CellStatus {
  return status === 'running' || status === 'starting' || status === 'stopping' ? status : 'offline'
}
const filteredCells = computed(() => {
  const q = search.value.trim().toLowerCase()
  const results = props.cells.filter(cell =>
    (!q || [cell.name, cell.id, cell.comb].some(value => value.toLowerCase().includes(q))) &&
    (statusFilter.value === 'all' || normaliseStatus(cell.status) === statusFilter.value),
  )
  if (sort.value === 'name') results.sort((a, b) => a.name.localeCompare(b.name))
  return results
})
const counts = computed(() => ({
  total: props.cells.length,
  online: props.cells.filter(c => normaliseStatus(c.status) === 'running').length,
  transitioning: props.cells.filter(c => ['starting', 'stopping'].includes(normaliseStatus(c.status))).length,
  offline: props.cells.filter(c => normaliseStatus(c.status) === 'offline').length,
}))
function statusLabel(status?: string) {
  const value = normaliseStatus(status)
  return value.charAt(0).toUpperCase() + value.slice(1)
}
function memoryUsed(cell: Cell) { return Math.max(0, cell.stats?.memory_mb ?? 0) }
function memoryLimit(cell: Cell) { return Math.max(0, cell.limits?.memory_mb ?? 0) }
function diskUsed(cell: Cell) { return Math.max(0, (cell.stats?.disk_bytes ?? 0) / 1073741824) }
function diskLimit(cell: Cell) { return Math.max(0, (cell.limits?.disk_mb ?? 0) / 1024) }
function percent(used: number, limit: number) { return limit > 0 ? Math.min(100, Math.max(0, used / limit * 100)) : 0 }
function formatMemory(mb: number) { return mb >= 1024 ? `${(mb / 1024).toFixed(1)} GB` : `${Math.round(mb)} MB` }
function formatDisk(gb: number) { return `${gb.toFixed(2)} GB` }
function cpu(cell: Cell) { return Math.max(0, cell.stats?.cpu ?? 0) }
function allocation(cell: Cell) {
  if (!cell.allocation?.ip || !cell.allocation?.port) return 'Not allocated'
  return `${cell.allocation.ip}:${cell.allocation.port}`
}
function cellGame(cell: Cell) {
  const comb = (props.combs ?? []).find(c => c.id === cell.comb)
  return comb?.game || comb?.name || (cell.comb || 'Unknown game').replace(/[-_]/g, ' ')
}
function power(id: string, action: 'start' | 'stop') {
  if (pending.value[id]) return
  pending.value = { ...pending.value, [id]: true }
  router.post(route(`cells.${action}`, id), {}, {
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['cells'], preserveScroll: true }),
    onFinish: () => { pending.value = { ...pending.value, [id]: false } },
  })
}
</script>

<template>
  <AppLayout>
    <Head title="Cells" />
    <main class="min-h-screen bg-[#090b0e] px-5 py-8 text-zinc-100 sm:px-8 xl:px-10">
      <div class="mx-auto max-w-[1600px] space-y-7">
        <header class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold tracking-tight text-white sm:text-[34px]">Your servers</h1>
            <p class="mt-2 text-sm text-zinc-500">Monitor and manage your game servers in one place.</p>
          </div>
          <div class="flex items-center gap-2 rounded-xl border border-white/[0.07] bg-[#111419] px-3.5 py-2.5 text-xs text-zinc-400">
            <span class="h-2 w-2 rounded-full bg-emerald-400" /> {{ counts.online }} online <span class="mx-1 text-zinc-700">/</span> {{ counts.total }} total
          </div>
        </header>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <div class="stat-card"><div class="stat-label">Total cells</div><div class="stat-value">{{ counts.total }}</div><div class="stat-note">Managed instances</div></div>
          <div class="stat-card"><div class="stat-label"><span class="status-dot bg-emerald-400" /> Running</div><div class="stat-value">{{ counts.online }}</div><div class="stat-note">Currently online</div></div>
          <div class="stat-card"><div class="stat-label"><span class="status-dot bg-amber-400" /> In progress</div><div class="stat-value">{{ counts.transitioning }}</div><div class="stat-note">Starting or stopping</div></div>
          <div class="stat-card"><div class="stat-label"><span class="status-dot bg-zinc-500" /> Offline</div><div class="stat-value">{{ counts.offline }}</div><div class="stat-note">Currently stopped</div></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-white/[0.07] bg-[#0f1217]">
          <div class="flex flex-col gap-4 border-b border-white/[0.06] px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 class="text-base font-semibold text-white">All cells <span class="ml-1.5 text-sm font-normal text-zinc-500">{{ filteredCells.length }}</span></h2>
              <p class="mt-1 text-xs text-zinc-500">Select a server to open its console and settings.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
              <div class="relative min-w-0 sm:w-64 xl:w-80">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input v-model="search" type="search" placeholder="Search servers..." aria-label="Search servers" class="control w-full pl-10" />
              </div>
              <select v-model="statusFilter" aria-label="Filter status" class="control sm:w-36"><option value="all">All statuses</option><option value="running">Running</option><option value="offline">Offline</option><option value="starting">Starting</option><option value="stopping">Stopping</option></select>
              <select v-model="sort" aria-label="Sort cells" class="control sm:w-36"><option value="created">Default order</option><option value="name">Name A–Z</option></select>
            </div>
          </div>

          <div v-if="filteredCells.length" class="divide-y divide-white/[0.055]">
            <article v-for="cell in filteredCells" :key="cell.id" class="group px-5 py-5 transition-colors hover:bg-white/[0.018]">
              <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex min-w-0 items-start gap-3.5 xl:w-[27%]">
                  <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-amber-500/15 bg-amber-500/[0.075] text-amber-400">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="16" height="8" rx="2"/><rect x="4" y="13" width="16" height="8" rx="2"/><path d="M8 7h.01M8 17h.01M12 7h5M12 17h5" stroke-linecap="round"/></svg>
                  </div>
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                      <Link :href="route('cells.show', cell.id)" class="truncate text-[15px] font-semibold text-white transition hover:text-amber-400">{{ cell.name }}</Link>
                      <span class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-[10px] font-semibold" :class="{
                        'border-emerald-500/20 bg-emerald-500/10 text-emerald-400': normaliseStatus(cell.status) === 'running',
                        'border-amber-500/20 bg-amber-500/10 text-amber-400': ['starting', 'stopping'].includes(normaliseStatus(cell.status)),
                        'border-white/10 bg-white/[0.035] text-zinc-400': normaliseStatus(cell.status) === 'offline',
                      }"><span class="status-dot" :class="normaliseStatus(cell.status) === 'running' ? 'bg-emerald-400' : ['starting','stopping'].includes(normaliseStatus(cell.status)) ? 'bg-amber-400' : 'bg-zinc-500'" />{{ statusLabel(cell.status) }}</span>
                    </div>
                    <p class="mt-1 truncate text-xs capitalize text-zinc-400">{{ cellGame(cell) }}</p>
                    <p class="mt-1.5 truncate font-mono text-[10px] text-zinc-600" :title="cell.id">{{ cell.id }}</p>
                  </div>
                </div>

                <div class="grid flex-1 grid-cols-3 gap-3 sm:gap-5 xl:max-w-[570px]">
                  <div class="min-w-0"><div class="metric-label">CPU</div><div class="metric-value">{{ cpu(cell).toFixed(1) }}<span class="text-xs text-zinc-500">%</span></div><div class="meter"><div class="h-full rounded-full bg-amber-400 transition-all" :style="{ width: `${Math.min(100, cpu(cell))}%` }" /></div></div>
                  <div class="min-w-0"><div class="metric-label">Memory</div><div class="metric-value truncate">{{ formatMemory(memoryUsed(cell)) }} <span class="text-[11px] font-normal text-zinc-500">/ {{ memoryLimit(cell) ? formatMemory(memoryLimit(cell)) : '—' }}</span></div><div class="meter"><div class="h-full rounded-full bg-sky-400 transition-all" :style="{ width: `${percent(memoryUsed(cell), memoryLimit(cell))}%` }" /></div></div>
                  <div class="min-w-0"><div class="metric-label">Storage</div><div class="metric-value truncate">{{ formatDisk(diskUsed(cell)) }} <span class="text-[11px] font-normal text-zinc-500">/ {{ diskLimit(cell) ? formatDisk(diskLimit(cell)) : '—' }}</span></div><div class="meter"><div class="h-full rounded-full bg-violet-400 transition-all" :style="{ width: `${percent(diskUsed(cell), diskLimit(cell))}%` }" /></div></div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 xl:w-[285px] xl:justify-end">
                  <div class="min-w-0 font-mono text-[11px] text-zinc-400 xl:mr-auto" :title="allocation(cell)">{{ allocation(cell) }}</div>
                  <div class="flex items-center gap-2">
                    <button type="button" class="action-btn border-amber-500/30 bg-amber-500 text-[#1a1305] hover:bg-amber-400 disabled:border-white/[0.06] disabled:bg-white/[0.04] disabled:text-zinc-600" :disabled="!!pending[cell.id] || ['running','starting','stopping'].includes(normaliseStatus(cell.status))" @click="power(cell.id, 'start')">Start</button>
                    <button type="button" class="action-btn border-white/10 bg-white/[0.035] text-zinc-300 hover:border-red-500/30 hover:text-red-400 disabled:opacity-35" :disabled="!!pending[cell.id] || normaliseStatus(cell.status) !== 'running'" @click="power(cell.id, 'stop')">Stop</button>
                    <Link :href="route('cells.show', cell.id)" class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.035] text-zinc-400 transition hover:border-amber-500/30 hover:text-amber-400" :aria-label="`Open ${cell.name}`"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg></Link>
                  </div>
                </div>
              </div>
            </article>
          </div>
          <div v-else class="flex flex-col items-center px-5 py-16 text-center">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.035] text-zinc-500"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="16" height="8" rx="2"/><rect x="4" y="13" width="16" height="8" rx="2"/></svg></div>
            <p class="font-semibold text-zinc-200">{{ cells.length ? 'No matching cells' : 'No cells yet' }}</p>
            <p class="mt-1 text-sm text-zinc-500">{{ cells.length ? 'Try changing your search or status filter.' : 'Your game servers will appear here once created.' }}</p>
            <button v-if="cells.length" class="mt-4 text-sm font-medium text-amber-400 hover:text-amber-300" @click="search = ''; statusFilter = 'all'">Clear filters</button>
          </div>
          <footer class="border-t border-white/[0.06] px-5 py-3.5 text-xs text-zinc-600">Showing {{ filteredCells.length }} of {{ cells.length }} cells</footer>
        </section>
      </div>
    </main>
  </AppLayout>
</template>

<style scoped>
.stat-card { @apply rounded-xl border border-white/[0.07] bg-[#111419] px-5 py-4; }
.stat-label { @apply flex items-center gap-2 text-xs font-medium text-zinc-400; }
.stat-value { @apply mt-3 text-3xl font-bold tracking-tight text-white; }
.stat-note { @apply mt-1 text-[11px] text-zinc-600; }
.status-dot { @apply inline-block h-1.5 w-1.5 shrink-0 rounded-full; }
.control { @apply h-10 rounded-lg border border-white/10 bg-[#0a0d11] px-3 text-xs text-zinc-200 outline-none transition placeholder:text-zinc-600 focus:border-amber-500/50; }
.metric-label { @apply mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-500; }
.metric-value { @apply text-sm font-semibold text-zinc-200; }
.meter { @apply mt-2.5 h-1 overflow-hidden rounded-full bg-white/[0.075]; }
.action-btn { @apply h-9 rounded-lg border px-3.5 text-xs font-semibold transition disabled:cursor-not-allowed; }
</style>
