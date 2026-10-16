<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CpuIcon, Database, Plus, Save, Star, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import NodeNavigation from './NodeNavigation.vue';

type Host = {
    id: string;
    name: string;
    driver: string;
    host: string;
    port: number;
    max_databases?: number | null;
    databases_count: number;
};

type Assignment = {
    id: string;
    database_host_id: string;
    priority: number;
    is_primary: boolean;
    enabled: boolean;
    host?: Host | null;
};

const props = defineProps<{
    node: {
        id: string;
        name: string;
        location?: string | null;
    };
    assignments: Assignment[];
    hosts: Host[];
}>();

const assignmentList = ref<Assignment[]>(props.assignments.map((assignment) => ({ ...assignment })));
const selectedHostId = ref('');
const selectedPriority = ref(100);
const selectedPrimary = ref(false);
const deleteAssignment = ref<Assignment | null>(null);

const unassignedHosts = computed(() => {
    const assignedIds = new Set(assignmentList.value.map((assignment) => assignment.database_host_id));

    return props.hosts.filter((host) => !assignedIds.has(host.id));
});

function assignHost() {
    if (!selectedHostId.value) return;

    router.post(
        `/admin/nodes/${props.node.id}/database-hosts`,
        {
            database_host_id: selectedHostId.value,
            priority: selectedPriority.value,
            is_primary: selectedPrimary.value,
            enabled: true,
        },
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['assignments', 'hosts'] }),
        },
    );
}

function saveAssignment(assignment: Assignment) {
    router.patch(
        `/admin/nodes/${props.node.id}/database-hosts/${assignment.id}`,
        {
            priority: assignment.priority,
            is_primary: assignment.is_primary,
            enabled: assignment.enabled,
        },
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['assignments'] }),
        },
    );
}

function removeAssignment() {
    if (!deleteAssignment.value) return;

    router.delete(`/admin/nodes/${props.node.id}/database-hosts/${deleteAssignment.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteAssignment.value = null;
        },
        onSuccess: () => router.reload({ only: ['assignments', 'hosts'] }),
    });
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${node.name} Database Hosts`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            {{ node.name }}
                                        </h1>

                                        <span
                                            v-if="node.location"
                                            class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive"
                                        >
                                            {{ node.location }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.manage_database_hosts_assigned_to_this_node') }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    href="/admin/nodes"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                                >
                                    <ArrowLeft class="size-4" />
                                    {{ t('ui.back') }}
                                </Link>
                            </div>
                        </div>
                    </section>

                    <NodeNavigation :node-id="node.id" active="database-hosts" />

                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <h2 class="text-lg font-black">
                            {{ t('ui.assign_database_host') }}
                        </h2>

                        <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_140px_auto_auto] lg:items-end">
                            <div>
                                <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                    {{ t('ui.database_host') }}
                                </label>

                                <select
                                    v-model="selectedHostId"
                                    class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                >
                                    <option value="">
                                        {{ t('ui.select_host') }}
                                    </option>

                                    <option v-for="host in unassignedHosts" :key="host.id" :value="host.id">
                                        {{ host.name }} — {{ host.host }}:{{ host.port }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                    {{ t('ui.priority') }}
                                </label>

                                <input
                                    v-model.number="selectedPriority"
                                    type="number"
                                    min="0"
                                    max="65535"
                                    class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                />
                            </div>

                            <label
                                class="flex h-[46px] items-center gap-2 rounded-button border border-border bg-surface-light px-4 text-sm font-black text-foreground"
                            >
                                <input v-model="selectedPrimary" type="checkbox" class="size-4 accent-hive" />
                                {{ t('ui.primary') }}
                            </label>

                            <button
                                type="button"
                                class="inline-flex h-[46px] items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 text-sm font-black text-black disabled:opacity-50"
                                :disabled="!selectedHostId"
                                @click="assignHost"
                            >
                                <Plus class="size-4" />
                                {{ t('ui.assign') }}
                            </button>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div v-if="assignmentList.length === 0" class="p-10 text-center">
                            <Database class="mx-auto size-10 text-muted-foreground" />

                            <h2 class="mt-4 text-lg font-black text-foreground">
                                {{ t('ui.no_database_hosts_assigned') }}
                            </h2>

                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('ui.cells_on_this_node_cannot_create_databases_until_an_enabled_host_') }}
                            </p>
                        </div>

                        <div v-else class="divide-y divide-border">
                            <div v-for="assignment in assignmentList" :key="assignment.id" class="p-5 sm:p-6">
                                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-black text-foreground">
                                                {{ assignment.host?.name ?? 'Missing host' }}
                                            </h3>

                                            <span
                                                v-if="assignment.is_primary"
                                                class="inline-flex items-center gap-1 rounded-full border border-hive/30 bg-hive/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-hive"
                                            >
                                                <Star class="size-3" />
                                                {{ t('ui.primary') }}
                                            </span>

                                            <span
                                                class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide"
                                                :class="
                                                    assignment.enabled
                                                        ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                        : 'border-border bg-surface-light text-muted-foreground'
                                                "
                                            >
                                                {{ assignment.enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </div>

                                        <div class="mt-2 font-mono text-sm text-muted-foreground">
                                            {{ assignment.host?.host }}:{{ assignment.host?.port }}
                                        </div>

                                        <div class="mt-2 text-xs font-bold text-muted-foreground">
                                            {{ assignment.host?.databases_count ?? 0 }} {{ t('ui.databases_capacity') }}
                                            {{ assignment.host?.max_databases ?? 'Unlimited' }}
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-end gap-3">
                                        <div>
                                            <label class="text-[10px] font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.priority') }}
                                            </label>
                                            <input
                                                v-model.number="assignment.priority"
                                                type="number"
                                                min="0"
                                                max="65535"
                                                class="mt-1 w-28 rounded-button border border-border bg-surface-light px-3 py-2 text-sm text-foreground outline-none focus:border-hive"
                                            />
                                        </div>

                                        <label
                                            class="flex h-[38px] items-center gap-2 rounded-button border border-border bg-surface-light px-3 text-xs font-black text-foreground"
                                        >
                                            <input v-model="assignment.is_primary" type="checkbox" class="size-4 accent-hive" />
                                            {{ t('ui.primary') }}
                                        </label>

                                        <label
                                            class="flex h-[38px] items-center gap-2 rounded-button border border-border bg-surface-light px-3 text-xs font-black text-foreground"
                                        >
                                            <input v-model="assignment.enabled" type="checkbox" class="size-4 accent-hive" />
                                            {{ t('ui.enabled') }}
                                        </label>

                                        <button
                                            type="button"
                                            class="inline-flex h-[38px] items-center gap-2 rounded-button border border-border bg-surface-light px-3 text-xs font-black text-foreground transition hover:border-hive hover:text-hive"
                                            @click="saveAssignment(assignment)"
                                        >
                                            <Save class="size-4" />
                                            {{ t('ui.save') }}
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex h-[38px] items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 text-xs font-black text-status-danger transition hover:bg-status-danger/20"
                                            @click="deleteAssignment = assignment"
                                        >
                                            <Trash2 class="size-4" />
                                            {{ t('ui.remove') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="deleteAssignment !== null"
            :title="t('ui.remove_database_host')"
            :description="
                deleteAssignment
                    ? `Remove ${deleteAssignment.host?.name ?? 'this database host'} from ${node.name}? Existing databases are not deleted, but new databases will no longer use this assignment.`
                    : ''
            "
            :confirm-text="t('ui.remove_host')"
            :danger="true"
            @cancel="deleteAssignment = null"
            @confirm="removeAssignment"
        />
    </AppLayout>
</template>
