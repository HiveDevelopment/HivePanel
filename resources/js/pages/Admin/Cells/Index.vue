<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Boxes,
    CircleAlert,
    CircleCheck,
    CircleDashed,
    Edit,
    Eye,
    HardDrive,
    Plus,
    Search,
    Server,
    TriangleAlert,
    Trash2,
    User,
    WifiOff,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

const props = defineProps<{
    cells: any[]
}>()

const cellToDelete = ref<any | null>(null)
const deleting = ref(false)
const search = ref('')
const statusFilter = ref<'all' | 'healthy' | 'issues' | 'installing' | 'failed'>('all')

const totalCells = computed(() => props.cells.length)

const filteredCells = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.cells.filter((cell) => {
        const matchesSearch = !query || [
            cell.name,
            cell.daemon_id,
            cell.owner?.name,
            cell.owner?.email,
            cell.node?.name,
            cell.node?.location,
            cell.allocation?.ip,
            cell.allocation?.port,
            cell.comb,
        ].some((value) => String(value ?? '').toLowerCase().includes(query))

        if (!matchesSearch) return false

        switch (statusFilter.value) {
            case 'healthy':
                return cell.install_status === 'installed' &&
                    (!cell.worker_sync?.status || cell.worker_sync?.status === 'synced')

            case 'issues':
                return ['out_of_sync', 'missing', 'unreachable', 'error'].includes(cell.worker_sync?.status)

            case 'installing':
                return ['pending', 'installing'].includes(cell.install_status)

            case 'failed':
                return cell.install_status === 'failed'

            default:
                return true
        }
    })
})

const assignedCount = computed(() =>
    props.cells.reduce((total, cell) => {
        const primary = cell.allocation ? 1 : 0
        const additional = cell.additional_allocations?.length ?? 0

        return total + primary + additional
    }, 0)
)

const cellsWithAllocations = computed(() =>
    props.cells.filter((cell) => cell.allocation).length
)

const additionalAllocationCount = computed(() =>
    props.cells.reduce((total, cell) => total + (cell.additional_allocations?.length ?? 0), 0)
)

const syncIssueCount = computed(() =>
    props.cells.filter((cell) =>
        ['out_of_sync', 'missing', 'unreachable', 'error'].includes(cell.worker_sync?.status)
    ).length
)

function confirmDelete(cell: any) {
    cellToDelete.value = cell
}

function cancelDelete() {
    if (deleting.value) return
    cellToDelete.value = null
}

function deleteCell() {
    if (!cellToDelete.value) return

    const routeId = cellToDelete.value.uuid ?? cellToDelete.value.id

    if (!routeId) {
        console.error('Missing cell route id', cellToDelete.value)
        return
    }

    deleting.value = true

    router.delete(`/admin/cells/${routeId}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            cellToDelete.value = null
        },
    })
}

function installStatusClass(status?: string) {
    switch (status) {
        case 'installed':
            return 'border-status-success/30 bg-status-success/10 text-status-success'

        case 'installing':
            return 'border-hive/30 bg-hive/10 text-hive'

        case 'pending':
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'

        case 'failed':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        default:
            return 'border-zinc-700 bg-zinc-800 text-zinc-400'
    }
}

function syncStatusClass(status?: string | null) {
    switch (status) {
        case 'synced':
            return 'border-status-success/30 bg-status-success/10 text-status-success'

        case 'out_of_sync':
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'

        case 'missing':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        case 'unreachable':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'
        
        case 'error':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        default:
            return 'border-zinc-700 bg-zinc-800 text-zinc-400'
    }
}

function syncStatusLabel(status?: string | null) {
    switch (status) {
        case 'synced':
            return 'Synced'

        case 'out_of_sync':
            return 'Out of Sync'

        case 'missing':
            return 'Missing'

        case 'unreachable':
            return 'Unavailable'

        case 'error':
            return 'Check Failed'

        default:
            return 'Not Checked'
    }
}

function syncStatusIcon(status?: string | null) {
    switch (status) {
        case 'synced':
            return CircleCheck

        case 'out_of_sync':
            return TriangleAlert

        case 'missing':
            return CircleAlert

        case 'unreachable':
            return WifiOff
        
        case 'error':
            return CircleAlert

        default:
            return CircleDashed
    }
}

function syncStatusDescription(cell: any) {
    const status = cell.worker_sync?.status

    if (!status) {
        return 'Worker sync has not been checked yet.'
    }

    if (status === 'synced') {
        return cell.worker_sync?.checked_at
            ? `Checked ${formatDate(cell.worker_sync.checked_at)}`
            : 'Worker definition matches HivePanel.'
    }

    if (status === 'out_of_sync') {
        const count = cell.worker_sync?.differences?.length ?? 0

        if (count === 1) {
            return '1 definition difference'
        }

        if (count > 1) {
            return `${count} definition differences`
        }

        return 'Worker definition differs from HivePanel.'
    }

    if (status === 'missing') {
        return 'Cell is missing from the Worker.'
    }

    if (status === 'unreachable') {
        return 'Worker could not be contacted.'
    }

    if (status === 'error') {
        return cell.worker_sync?.message || 'Worker reconciliation failed.'
    }

    return cell.worker_sync?.message || 'Unknown Worker sync state.'
}

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Cells" />

        <div class="min-h-screen min-w-0 bg-surface-dark text-white">
            <main class="min-w-0 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto min-w-0 max-w-full space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Server class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Cells
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Manage deployed cells and their allocations on worker nodes.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/cells/create"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                            >
                                <Plus class="size-4" />
                                New Cell
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Total Cells
                            </div>
                            <div class="mt-1 text-2xl font-black">
                                {{ totalCells }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">
                                created cells
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Total Allocations
                            </div>
                            <div class="mt-1 text-2xl font-black text-hive">
                                {{ assignedCount }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">
                                primary + additional
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Unassigned
                            </div>
                            <div class="mt-1 text-2xl font-black text-status-warning">
                                {{ totalCells - cellsWithAllocations }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">
                                cells missing primary
                            </div>
                        </div>

                        <div
                            class="rounded-panel border bg-surface p-5"
                            :class="syncIssueCount > 0 ? 'border-status-danger/30' : 'border-zinc-800'"
                        >
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                    Sync Issues
                                </div>

                                <TriangleAlert
                                    v-if="syncIssueCount > 0"
                                    class="size-4 text-status-danger"
                                />
                                <CircleCheck
                                    v-else
                                    class="size-4 text-status-success"
                                />
                            </div>

                            <div
                                class="mt-1 text-2xl font-black"
                                :class="syncIssueCount > 0 ? 'text-status-danger' : 'text-status-success'"
                            >
                                {{ syncIssueCount }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                cells requiring attention · {{ additionalAllocationCount }} additional allocations
                            </div>
                        </div>
                    </section>

                    <section class="min-w-0 rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 p-5 sm:p-6">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                                <div>
                                    <h2 class="text-lg font-black">
                                        All Cells
                                    </h2>

                                    <p class="mt-1 text-sm text-zinc-500">
                                        Review deployment, networking, installation and Worker reconciliation state.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <div class="relative">
                                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-600" />

                                        <input
                                            v-model="search"
                                            type="search"
                                            placeholder="Search Cells..."
                                            class="w-full min-w-0 rounded-button border border-zinc-800 bg-[#0d0f11] py-2.5 pl-10 pr-4 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive sm:w-auto"
                                        />
                                    </div>

                                    <select
                                        v-model="statusFilter"
                                        class="rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2.5 text-sm font-bold text-zinc-300 outline-none transition focus:border-hive"
                                    >
                                        <option value="all">All Statuses</option>
                                        <option value="healthy">Healthy</option>
                                        <option value="issues">Sync Issues</option>
                                        <option value="installing">Installing</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 text-xs font-bold text-zinc-600">
                                Showing {{ filteredCells.length }} of {{ cells.length }} Cells
                            </div>
                        </div>

                        <div
                            v-if="cells.length === 0"
                            class="rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center"
                        >
                            <Server class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No cells yet
                            </h2>

                            <p class="mt-2 text-sm text-zinc-500">
                                Add your first cell to get started.
                            </p>
                        </div>

                        <div
                            v-else-if="filteredCells.length === 0"
                            class="p-10 text-center"
                        >
                            <Search class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No matching Cells
                            </h2>

                            <p class="mt-2 text-sm text-zinc-500">
                                Adjust the search text or status filter.
                            </p>
                        </div>

                        <div v-else class="min-w-0 w-full">
                            <!-- Mobile and tablet: stacked cards avoid a seven-column table. -->
                            <div class="grid gap-3 p-3 lg:hidden">
                                <article v-for="cell in filteredCells" :key="cell.id" class="min-w-0 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                    <div class="flex min-w-0 items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <Link :href="`/admin/cells/${cell.id}`" class="block truncate text-sm font-black text-white hover:text-hive">{{ cell.name }}</Link>
                                            <div class="mt-1 truncate font-mono text-[11px] text-zinc-500">{{ cell.daemon_id ? cell.daemon_id.slice(0, 8) : 'No daemon ID' }}</div>
                                        </div>
                                        <span class="max-w-[55%] shrink-0 truncate rounded-full border px-2 py-1 text-[10px] font-bold" :class="installStatusClass(cell.install_status)" :title="cell.install_failure_reason || syncStatusDescription(cell)">{{ cell.install_status_label || cell.install_status || 'Unknown' }}</span>
                                    </div>
                                    <dl class="mt-4 grid min-w-0 grid-cols-2 gap-x-3 gap-y-3 text-xs">
                                        <div class="min-w-0"><dt class="text-zinc-500">Owner</dt><dd class="mt-1 truncate font-semibold text-zinc-200">{{ cell.owner?.name || 'Unknown' }}</dd></div>
                                        <div class="min-w-0"><dt class="text-zinc-500">Node</dt><dd class="mt-1 truncate font-semibold text-zinc-200">{{ cell.node?.name || 'Unknown' }}</dd></div>
                                        <div class="min-w-0"><dt class="text-zinc-500">Allocation</dt><dd class="mt-1 truncate font-mono text-zinc-200" :title="cell.allocation ? `${cell.allocation.ip}:${cell.allocation.port}` : ''">{{ cell.allocation ? `${cell.allocation.ip}:${cell.allocation.port}` : 'Unassigned' }}</dd></div>
                                        <div class="min-w-0"><dt class="text-zinc-500">Comb</dt><dd class="mt-1 truncate font-semibold text-zinc-200">{{ cell.comb || 'None' }}</dd></div>
                                    </dl>
                                    <p v-if="['out_of_sync', 'missing', 'unreachable', 'error'].includes(cell.worker_sync?.status)" class="mt-3 truncate text-xs text-status-warning" :title="syncStatusDescription(cell)">Worker sync issue</p>
                                    <div class="mt-4 flex items-center gap-2 border-t border-zinc-800 pt-3">
                                        <Link :href="`/admin/cells/${cell.id}`" class="inline-flex flex-1 items-center justify-center gap-2 rounded-button border border-zinc-800 px-3 py-2 text-xs font-bold text-zinc-200"><Eye class="size-4" /> View</Link>
                                        <Link :href="`/admin/cells/${cell.id}/edit`" class="inline-flex flex-1 items-center justify-center gap-2 rounded-button border border-zinc-800 px-3 py-2 text-xs font-bold text-zinc-200"><Edit class="size-4" /> Edit</Link>
                                        <button type="button" class="rounded-button border border-status-danger/40 p-2 text-status-danger" title="Delete Cell" aria-label="Delete Cell" @click="confirmDelete(cell)"><Trash2 class="size-4" /></button>
                                    </div>
                                </article>
                            </div>
                            <div class="hidden w-full min-w-0 overflow-x-auto lg:block">
                            <table class="w-full table-fixed divide-y divide-zinc-800">
                                <thead class="bg-[#0d0f11]">
                                    <tr>
                                        <th class="w-[22%] px-3 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Cell</th>
                                        <th class="w-[16%] px-3 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Owner</th>
                                        <th class="w-[12%] px-3 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Node</th>
                                        <th class="w-[16%] px-3 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Allocation</th>
                                        <th class="w-[12%] px-3 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Comb</th>
                                        <th class="w-[12%] px-3 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Status</th>
                                        <th class="w-[10%] px-3 py-4 text-right text-xs font-black uppercase tracking-wide text-zinc-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800">
                                    <tr v-for="cell in filteredCells" :key="cell.id" class="transition hover:bg-surface-light/40">
                                        <td class="min-w-0 px-3 py-4">
                                            <Link :href="`/admin/cells/${cell.id}`" class="block truncate text-sm font-black text-white transition hover:text-hive" :title="cell.name">
                                                {{ cell.name }}
                                            </Link>
                                            <span class="mt-1 block truncate font-mono text-[11px] text-zinc-500" :title="cell.daemon_id || ''">{{ cell.daemon_id ? cell.daemon_id.slice(0, 8) : 'No daemon ID' }}</span>
                                        </td>
                                        <td class="min-w-0 px-3 py-4">
                                            <div class="truncate text-sm font-bold text-zinc-300" :title="cell.owner?.email || ''">{{ cell.owner?.name || 'Unknown' }}</div>
                                        </td>
                                        <td class="min-w-0 px-3 py-4">
                                            <div class="truncate text-sm font-bold text-zinc-300">{{ cell.node?.name || 'Unknown' }}</div>
                                        </td>
                                        <td class="min-w-0 px-3 py-4">
                                            <div v-if="cell.allocation" class="truncate font-mono text-xs font-bold text-white" :title="`${cell.allocation.ip}:${cell.allocation.port}`">{{ cell.allocation.ip }}:{{ cell.allocation.port }}</div>
                                            <span v-else class="text-xs text-status-warning">Unassigned</span>
                                        </td>
                                        <td class="min-w-0 px-3 py-4">
                                            <div class="truncate text-xs font-bold text-zinc-300" :title="cell.comb">{{ cell.comb || 'None' }}</div>
                                        </td>
                                        <td class="min-w-0 px-3 py-4">
                                            <span class="inline-block max-w-full truncate rounded-full border px-2 py-1 align-middle text-[11px] font-bold" :class="installStatusClass(cell.install_status)" :title="cell.install_failure_reason || syncStatusDescription(cell)">
                                                {{ cell.install_status_label || cell.install_status || 'Unknown' }}
                                            </span>
                                            <div v-if="['out_of_sync', 'missing', 'unreachable', 'error'].includes(cell.worker_sync?.status)" class="mt-1 truncate text-[11px] text-status-warning" :title="syncStatusDescription(cell)">Sync issue</div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <div class="flex items-center justify-end gap-1">
                                                <Link :href="`/admin/cells/${cell.id}`" class="rounded-button border border-zinc-800 p-2 text-zinc-300 transition hover:border-hive hover:text-hive" title="View Cell" aria-label="View Cell"><Eye class="size-4" /></Link>
                                                <Link :href="`/admin/cells/${cell.id}/edit`" class="rounded-button border border-zinc-800 p-2 text-zinc-300 transition hover:border-hive hover:text-hive" title="Edit Cell" aria-label="Edit Cell"><Edit class="size-4" /></Link>
                                                <button type="button" class="rounded-button border border-status-danger/40 p-2 text-status-danger transition hover:bg-status-danger/10" title="Delete Cell" aria-label="Delete Cell" @click="confirmDelete(cell)"><Trash2 class="size-4" /></button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="!!cellToDelete"
            title="Delete Cell?"
            :description="`Are you sure you wish to delete '${cellToDelete?.name}'? This action cannot be undone.`"
            confirm-text="Delete Cell"
            cancel-text="Cancel"
            :danger="true"
            :loading="deleting"
            @cancel="cancelDelete"
            @confirm="deleteCell"
        />
    </AppLayout>
</template>