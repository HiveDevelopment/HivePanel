<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Boxes,
    CircleAlert,
    CircleCheck,
    CircleDashed,
    CpuIcon,
    Edit,
    Eye,
    Search,
    Server,
    TriangleAlert,
    User,
    WifiOff,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import NodeNavigation from './NodeNavigation.vue';

const props = defineProps<{
    node: any;
    cells: any[];
}>();

const search = ref('');
const statusFilter = ref<'all' | 'healthy' | 'issues' | 'installing' | 'failed'>('all');

const totalCells = computed(() => props.cells.length);

const installedCount = computed(() => props.cells.filter((cell) => cell.install_status === 'installed').length);

const syncIssueCount = computed(
    () => props.cells.filter((cell) => ['out_of_sync', 'missing', 'unreachable', 'error'].includes(cell.worker_sync?.status)).length,
);

const allocationCount = computed(() =>
    props.cells.reduce((total, cell) => {
        const primary = cell.allocation ? 1 : 0;
        const additional = cell.additional_allocations?.length ?? 0;

        return total + primary + additional;
    }, 0),
);

const filteredCells = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.cells.filter((cell) => {
        const matchesSearch =
            !query ||
            [cell.name, cell.daemon_id, cell.owner?.name, cell.owner?.email, cell.allocation?.ip, cell.allocation?.port, cell.comb].some((value) =>
                String(value ?? '')
                    .toLowerCase()
                    .includes(query),
            );

        if (!matchesSearch) return false;

        switch (statusFilter.value) {
            case 'healthy':
                return cell.install_status === 'installed' && (!cell.worker_sync?.status || cell.worker_sync?.status === 'synced');

            case 'issues':
                return ['out_of_sync', 'missing', 'unreachable', 'error'].includes(cell.worker_sync?.status);

            case 'installing':
                return ['pending', 'installing'].includes(cell.install_status);

            case 'failed':
                return cell.install_status === 'failed';

            default:
                return true;
        }
    });
});

function installStatusClass(status?: string) {
    switch (status) {
        case 'installed':
            return 'border-status-success/30 bg-status-success/10 text-status-success';

        case 'installing':
            return 'border-hive/30 bg-hive/10 text-hive';

        case 'pending':
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning';

        case 'failed':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger';

        default:
            return 'border-border bg-surface-light text-muted-foreground';
    }
}

function syncStatusClass(status?: string | null) {
    switch (status) {
        case 'synced':
            return 'border-status-success/30 bg-status-success/10 text-status-success';

        case 'out_of_sync':
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning';

        case 'missing':
        case 'unreachable':
        case 'error':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger';

        default:
            return 'border-border bg-surface-light text-muted-foreground';
    }
}

function syncStatusLabel(status?: string | null) {
    switch (status) {
        case 'synced':
            return 'Synced';

        case 'out_of_sync':
            return 'Out of Sync';

        case 'missing':
            return 'Missing';

        case 'unreachable':
            return 'Unavailable';

        case 'error':
            return 'Check Failed';

        default:
            return 'Not Checked';
    }
}

function syncStatusIcon(status?: string | null) {
    switch (status) {
        case 'synced':
            return CircleCheck;

        case 'out_of_sync':
            return TriangleAlert;

        case 'missing':
            return CircleAlert;

        case 'unreachable':
            return WifiOff;

        case 'error':
            return CircleAlert;

        default:
            return CircleDashed;
    }
}

function formatDate(value?: string) {
    if (!value) return 'Never';

    return new Date(value).toLocaleString();
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${node.name} Cells`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">{{ node.name }} {{ t('ui.cells') }}</h1>

                                        <span
                                            v-if="node.location"
                                            class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive"
                                        >
                                            {{ node.location }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.review_every_cell_deployed_to_this_worker_node') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/nodes"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back_to_nodes') }}
                            </Link>
                        </div>
                    </section>

                    <NodeNavigation :node-id="node.id" active="cells" />

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.total_cells') }}
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ totalCells }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.deployed_to_this_node') }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.installed') }}
                            </div>

                            <div class="mt-1 text-2xl font-black text-status-success">
                                {{ installedCount }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.completed_installations') }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.allocations') }}
                            </div>

                            <div class="mt-1 text-2xl font-black text-hive">
                                {{ allocationCount }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.primary_additional') }}
                            </div>
                        </div>

                        <div class="rounded-panel border bg-surface p-5" :class="syncIssueCount > 0 ? 'border-status-danger/30' : 'border-border'">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.sync_issues') }}
                            </div>

                            <div class="mt-1 text-2xl font-black" :class="syncIssueCount > 0 ? 'text-status-danger' : 'text-status-success'">
                                {{ syncIssueCount }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.requiring_attention') }}
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div class="border-b border-border p-5 sm:p-6">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                                <div>
                                    <h2 class="text-lg font-black">
                                        {{ t('ui.node_cells') }}
                                    </h2>

                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ t('ui.installation_networking_and_worker_synchronization_state_for_this') }}
                                    </p>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <div class="relative">
                                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />

                                        <input
                                            v-model="search"
                                            type="search"
                                            :placeholder="t('ui.search_cells')"
                                            class="w-full min-w-[260px] rounded-button border border-border bg-surface-dark py-2.5 pl-10 pr-4 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive sm:w-auto"
                                        />
                                    </div>

                                    <select
                                        v-model="statusFilter"
                                        class="rounded-button border border-border bg-surface-dark px-4 py-2.5 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                    >
                                        <option value="all">{{ t('ui.all_statuses') }}</option>
                                        <option value="healthy">{{ t('ui.healthy') }}</option>
                                        <option value="issues">{{ t('ui.sync_issues') }}</option>
                                        <option value="installing">{{ t('ui.installing') }}</option>
                                        <option value="failed">{{ t('ui.failed') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 text-xs font-bold text-muted-foreground">
                                {{ t('ui.showing') }} {{ filteredCells.length }} {{ t('ui.of') }} {{ cells.length }} {{ t('Cells') }}
                            </div>
                        </div>

                        <div v-if="cells.length === 0" class="p-10 text-center">
                            <Server class="mx-auto size-10 text-muted-foreground" />

                            <h2 class="mt-4 text-lg font-black text-foreground">
                                {{ t('ui.no_cells_on_this_node') }}
                            </h2>

                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('ui.this_worker_does_not_currently_have_any_cells_assigned') }}
                            </p>
                        </div>

                        <div v-else-if="filteredCells.length === 0" class="p-10 text-center">
                            <Search class="mx-auto size-10 text-muted-foreground" />

                            <h2 class="mt-4 text-lg font-black text-foreground">
                                {{ t('ui.no_matching_cells') }}
                            </h2>

                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('ui.adjust_the_search_text_or_status_filter') }}
                            </p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-border">
                                <thead class="bg-surface-dark">
                                    <tr>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.cell') }}
                                        </th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.owner') }}
                                        </th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.allocation') }}
                                        </th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.comb') }}
                                        </th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.install') }}
                                        </th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.worker_sync') }}
                                        </th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.created') }}
                                        </th>
                                        <th class="px-5 py-4 text-right text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.actions') }}
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-border">
                                    <tr v-for="cell in filteredCells" :key="cell.id" class="transition hover:bg-surface-light/40">
                                        <td class="px-5 py-4">
                                            <Link :href="`/admin/cells/${cell.id}`" class="font-black text-foreground transition hover:text-hive">
                                                {{ cell.name }}
                                            </Link>

                                            <div class="mt-1 font-mono text-xs text-muted-foreground">
                                                {{ cell.daemon_id || 'No daemon ID' }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2 text-sm font-bold text-foreground">
                                                <User class="size-4 text-muted-foreground" />
                                                {{ cell.owner?.name || 'Unknown' }}
                                            </div>

                                            <div class="mt-1 text-xs text-muted-foreground">
                                                {{ cell.owner?.email || 'No email' }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div v-if="cell.allocation">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="font-mono text-sm font-black text-foreground">
                                                        {{ cell.allocation.ip }}:{{ cell.allocation.port }}
                                                    </span>

                                                    <span
                                                        class="inline-flex rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-hive"
                                                    >
                                                        {{ t('ui.primary') }}
                                                    </span>
                                                </div>

                                                <div class="mt-1 text-xs text-muted-foreground">
                                                    {{ cell.additional_allocations?.length ?? 0 }} {{ t('ui.additional') }}
                                                </div>
                                            </div>

                                            <div v-else class="text-sm font-bold text-status-warning">
                                                {{ t('ui.no_primary_allocation') }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div
                                                class="inline-flex items-center gap-2 rounded-full border border-border bg-surface-dark px-3 py-1 text-xs font-black text-foreground"
                                            >
                                                <Boxes class="size-3" />
                                                {{ cell.comb }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <span
                                                class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                                :class="installStatusClass(cell.install_status)"
                                            >
                                                {{ cell.install_status_label || cell.install_status || 'Unknown' }}
                                            </span>
                                        </td>

                                        <td class="px-5 py-4">
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black"
                                                :class="syncStatusClass(cell.worker_sync?.status)"
                                            >
                                                <component :is="syncStatusIcon(cell.worker_sync?.status)" class="size-3.5" />
                                                {{ syncStatusLabel(cell.worker_sync?.status) }}
                                            </span>
                                        </td>

                                        <td class="px-5 py-4 text-sm font-bold text-muted-foreground">
                                            {{ formatDate(cell.created_at) }}
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="flex justify-end gap-2">
                                                <Link
                                                    :href="`/admin/cells/${cell.id}`"
                                                    class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-dark px-3 py-2 text-xs font-black text-foreground transition hover:border-hive hover:text-hive"
                                                >
                                                    <Eye class="size-4" />
                                                    {{ t('ui.view') }}
                                                </Link>

                                                <Link
                                                    :href="`/admin/cells/${cell.id}/edit`"
                                                    class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-dark px-3 py-2 text-xs font-black text-foreground transition hover:border-hive hover:text-hive"
                                                >
                                                    <Edit class="size-4" />
                                                    {{ t('ui.edit') }}
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
