<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Activity, CircleCheck, CircleDashed, CpuIcon, HardDrive, MapPin, Plus, Server, TriangleAlert, WifiOff } from 'lucide-vue-next';
import { computed } from 'vue';

type NodeRecord = {
    id: string;
    name: string;
    location?: string | null;
    scheme: 'http' | 'https';
    fqdn: string;
    port: number;
    daemon_port?: number;
    is_active: boolean;
    is_registered?: boolean;
    maintenance_mode?: boolean;
    last_seen_at?: string | null;
    worker_version?: string | null;
    worker_hostname?: string | null;
    live_stat?: {
        host_cpu_used?: number;
        host_cpu_max?: number;
        host_memory_used_gb?: number;
        host_memory_max_gb?: number;
        host_disk_used_gb?: number;
        host_disk_max_gb?: number;
        cells_total?: number;
        cells_running?: number;
        updated_at?: string | null;
    } | null;
    created_at?: string;
    updated_at?: string;
};

const props = defineProps<{
    nodes: NodeRecord[];
}>();

const totalNodes = computed(() => props.nodes.length);

const activeNodes = computed(() => props.nodes.filter((node) => node.is_active).length);

const registeredNodes = computed(() => props.nodes.filter((node) => node.is_registered).length);

const maintenanceNodes = computed(() => props.nodes.filter((node) => node.maintenance_mode).length);

const totalCells = computed(() => props.nodes.reduce((total, node) => total + Number(node.live_stat?.cells_total ?? 0), 0));

const runningCells = computed(() => props.nodes.reduce((total, node) => total + Number(node.live_stat?.cells_running ?? 0), 0));

function formatDate(value?: string | null) {
    if (!value) return 'Never';

    return new Date(value).toLocaleString();
}

function formatPercent(used?: number, max?: number) {
    const usedValue = Number(used ?? 0);
    const maxValue = Number(max ?? 0);

    if (maxValue <= 0) return '—';

    return `${Math.round((usedValue / maxValue) * 100)}%`;
}

function nodeHealthLabel(node: NodeRecord) {
    if (node.maintenance_mode) return 'Maintenance';
    if (!node.is_active) return 'Inactive';
    if (!node.is_registered) return 'Not Registered';
    if (!node.last_seen_at) return 'Awaiting Heartbeat';

    return 'Online';
}

function nodeHealthClass(node: NodeRecord) {
    if (node.maintenance_mode) {
        return 'border-status-warning/30 bg-status-warning/10 text-status-warning';
    }

    if (!node.is_active) {
        return 'border-border bg-surface-light text-muted-foreground';
    }

    if (!node.is_registered || !node.last_seen_at) {
        return 'border-status-danger/30 bg-status-danger/10 text-status-danger';
    }

    return 'border-status-success/30 bg-status-success/10 text-status-success';
}

function nodeHealthIcon(node: NodeRecord) {
    if (node.maintenance_mode) return TriangleAlert;
    if (!node.is_active) return CircleDashed;
    if (!node.is_registered || !node.last_seen_at) return WifiOff;

    return CircleCheck;
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="t('ui.admin_nodes')" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        {{ t('Nodes') }}
                                    </h1>

                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.monitor_worker_health_deployed_cells_and_backend_capacity_across_') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/nodes/create"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                            >
                                <Plus class="size-4" />
                                {{ t('ui.new_node') }}
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.total_nodes') }}
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ totalNodes }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.configured_workers') }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.active_9234069') }}
                            </div>

                            <div class="mt-1 text-2xl font-black text-status-success">
                                {{ activeNodes }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.enabled_for_use') }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.registered') }}
                            </div>

                            <div class="mt-1 text-2xl font-black text-hive">
                                {{ registeredNodes }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.workers_connected') }}
                            </div>
                        </div>

                        <div class="rounded-panel border bg-surface p-5" :class="maintenanceNodes > 0 ? 'border-status-warning/30' : 'border-border'">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.maintenance') }}
                            </div>

                            <div class="mt-1 text-2xl font-black" :class="maintenanceNodes > 0 ? 'text-status-warning' : 'text-foreground'">
                                {{ maintenanceNodes }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.nodes_restricted') }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('Cells') }}
                            </div>

                            <div class="mt-1 text-2xl font-black text-foreground">{{ runningCells }}/{{ totalCells }}</div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.running_reported') }}
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div class="flex flex-col gap-3 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                            <div>
                                <h2 class="text-lg font-black">
                                    {{ t('ui.worker_nodes') }}
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.current_worker_registration_heartbeat_and_host_resource_informati') }}
                                </p>
                            </div>

                            <div class="text-xs font-bold text-muted-foreground">{{ nodes.length }} {{ t('ui.total_1123987') }}</div>
                        </div>

                        <div v-if="nodes.length === 0" class="p-10 text-center">
                            <div class="mx-auto flex size-14 items-center justify-center rounded-full border border-border bg-surface-dark">
                                <Server class="size-6 text-muted-foreground" />
                            </div>

                            <h2 class="mt-4 text-lg font-black text-foreground">
                                {{ t('ui.no_nodes_yet') }}
                            </h2>

                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('ui.add_your_first_worker_node_to_begin_deploying_cells') }}
                            </p>
                        </div>

                        <div v-else class="divide-y divide-border">
                            <Link
                                v-for="node in nodes"
                                :key="node.id"
                                :href="`/admin/nodes/${node.id}`"
                                class="group block p-5 transition hover:bg-surface-light/40 sm:p-6"
                            >
                                <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-black text-foreground transition group-hover:text-hive">
                                                {{ node.name }}
                                            </h3>

                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-black"
                                                :class="nodeHealthClass(node)"
                                            >
                                                <component :is="nodeHealthIcon(node)" class="size-3.5" />
                                                {{ nodeHealthLabel(node) }}
                                            </span>

                                            <span
                                                v-if="node.scheme"
                                                class="rounded-full border border-border bg-surface-light px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-muted-foreground"
                                            >
                                                {{ node.scheme }}
                                            </span>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs font-bold text-muted-foreground">
                                            <span class="inline-flex items-center gap-1.5">
                                                <MapPin class="size-3.5" />
                                                {{ node.location || 'No location' }}
                                            </span>

                                            <span class="font-mono"> {{ node.scheme }}://{{ node.fqdn }}:{{ node.daemon_port ?? node.port }} </span>

                                            <span> {{ t('ui.last_seen') }} {{ formatDate(node.last_seen_at) }} </span>

                                            <span v-if="node.worker_version"> {{ t('ui.worker') }} {{ node.worker_version }} </span>
                                        </div>
                                    </div>

                                    <div class="grid shrink-0 grid-cols-2 gap-2 sm:grid-cols-4 xl:w-[560px]">
                                        <div class="rounded-button border border-border bg-surface-dark p-3">
                                            <div
                                                class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-muted-foreground"
                                            >
                                                <Activity class="size-3" />
                                                {{ t('Cells') }}
                                            </div>

                                            <div class="mt-1 text-sm font-black text-foreground">
                                                {{ node.live_stat?.cells_running ?? 0 }}/{{ node.live_stat?.cells_total ?? 0 }}
                                            </div>
                                        </div>

                                        <div class="rounded-button border border-border bg-surface-dark p-3">
                                            <div
                                                class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-muted-foreground"
                                            >
                                                <CpuIcon class="size-3" />
                                                {{ t('ui.cpu') }}
                                            </div>

                                            <div class="mt-1 text-sm font-black text-foreground">
                                                {{ formatPercent(node.live_stat?.host_cpu_used, node.live_stat?.host_cpu_max) }}
                                            </div>
                                        </div>

                                        <div class="rounded-button border border-border bg-surface-dark p-3">
                                            <div class="text-[10px] font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.memory') }}
                                            </div>

                                            <div class="mt-1 text-sm font-black text-foreground">
                                                {{ formatPercent(node.live_stat?.host_memory_used_gb, node.live_stat?.host_memory_max_gb) }}
                                            </div>
                                        </div>

                                        <div class="rounded-button border border-border bg-surface-dark p-3">
                                            <div
                                                class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-muted-foreground"
                                            >
                                                <HardDrive class="size-3" />
                                                {{ t('ui.disk') }}
                                            </div>

                                            <div class="mt-1 text-sm font-black text-foreground">
                                                {{ formatPercent(node.live_stat?.host_disk_used_gb, node.live_stat?.host_disk_max_gb) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
