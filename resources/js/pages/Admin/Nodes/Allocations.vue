<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CpuIcon, HardDrive, Plus, Shield, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import NodeNavigation from './NodeNavigation.vue';

const props = defineProps<{
    node: any;
    allocations: any[];
}>();

const page = usePage();
const allocationError = computed(() => page.props.errors.allocations);

const form = useForm({
    ip: '0.0.0.0',
    alias: '',
    notes: '',
    mode: 'single',
    port: '',
    port_start: '',
    port_end: '',
});

const confirmation = ref({
    open: false,
    title: '',
    description: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    danger: false,
    loading: false,
    allocation: null as any,
});

const availableCount = computed(() => props.allocations.filter((allocation) => !allocation.is_reserved && !allocation.is_assigned).length);

const reservedCount = computed(() => props.allocations.filter((allocation) => allocation.is_reserved).length);

const assignedCount = computed(() => props.allocations.filter((allocation) => allocation.is_assigned).length);

const primaryAssignedCount = computed(() => props.allocations.filter((allocation) => allocation.is_assigned && allocation.is_primary).length);

const additionalAssignedCount = computed(() => props.allocations.filter((allocation) => allocation.is_assigned && !allocation.is_primary).length);

const ipCount = computed(() => new Set(props.allocations.map((allocation) => allocation.ip)).size);

function submit() {
    const payload: any = {
        ip: form.ip,
        alias: form.alias,
        notes: form.notes,
    };

    if (form.mode === 'single') {
        payload.port = form.port;
    } else {
        payload.port_start = form.port_start;
        payload.port_end = form.port_end;
    }

    form.transform(() => payload).post(`/admin/nodes/${props.node.id}/allocations`, {
        preserveScroll: true,
        onSuccess: () => {
            form.port = '';
            form.port_start = '';
            form.port_end = '';
        },
    });
}

function toggleReserve(allocation: any) {
    if (allocation.is_assigned) return;

    router.patch(
        `/admin/nodes/${props.node.id}/allocations/${allocation.id}/reserve`,
        {},
        {
            preserveScroll: true,
        },
    );
}

function deleteAllocation(allocation: any) {
    if (allocation.is_assigned) return;

    confirmation.value = {
        open: true,
        title: 'Delete Allocation',
        description: `Are you sure you want to delete ${allocation.ip}:${allocation.port}? This allocation will no longer be available to the node.`,
        confirmText: 'Delete Allocation',
        cancelText: 'Cancel',
        danger: true,
        loading: false,
        allocation,
    };
}

function confirmDelete() {
    const allocation = confirmation.value.allocation;

    if (!allocation || confirmation.value.loading) return;

    confirmation.value.loading = true;

    router.delete(`/admin/nodes/${props.node.id}/allocations/${allocation.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            confirmation.value.open = false;
            confirmation.value.allocation = null;
        },
        onFinish: () => {
            confirmation.value.loading = false;
        },
    });
}

function cancelDelete() {
    if (confirmation.value.loading) return;

    confirmation.value.open = false;
    confirmation.value.allocation = null;
}

function statusLabel(allocation: any) {
    if (allocation.is_assigned && allocation.is_primary) return 'Primary';
    if (allocation.is_assigned) return 'Additional';
    if (allocation.is_reserved) return 'Reserved';

    return 'Available';
}

function statusClass(allocation: any) {
    if (allocation.is_assigned && allocation.is_primary) {
        return 'border-hive/30 bg-hive/10 text-hive';
    }

    if (allocation.is_assigned) {
        return 'border-sky-400/30 bg-sky-400/10 text-sky-300';
    }

    if (allocation.is_reserved) {
        return 'border-status-warning/30 bg-status-warning/10 text-status-warning';
    }

    return 'border-status-success/30 bg-status-success/10 text-status-success';
}

function formatDate(value?: string) {
    if (!value) return 'Never';

    return new Date(value).toLocaleString();
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Node ${node.name} Allocations`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">{{ node.name }} {{ t('ui.allocations_0b2ee12') }}</h1>

                                        <span
                                            v-if="node.location"
                                            class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive"
                                        >
                                            {{ node.location }}
                                        </span>

                                        <span
                                            class="rounded-full border border-border bg-surface-light px-2 py-0.5 text-xs font-bold text-muted-foreground"
                                        >
                                            {{ node.scheme }}
                                        </span>
                                    </div>

                                    <p class="mt-2 font-mono text-sm text-muted-foreground">
                                        {{ node.fqdn }}
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

                    <NodeNavigation :node-id="node.id" active="allocations" />

                    <div
                        v-if="allocationError"
                        role="alert"
                        class="rounded-button border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger"
                    >
                        {{ allocationError }}
                    </div>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.total') }}
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ allocations.length }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.across') }} {{ ipCount }} {{ ipCount === 1 ? 'IP' : 'IPs' }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.available') }}
                            </div>

                            <div class="mt-1 text-2xl font-black text-status-success">
                                {{ availableCount }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.ready_to_assign') }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.assigned') }}
                            </div>

                            <div class="mt-1 text-2xl font-black text-hive">
                                {{ assignedCount }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ primaryAssignedCount }} {{ t('ui.primary_3dbbdb1') }} {{ additionalAssignedCount }} {{ t('ui.additional') }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.reserved') }}
                            </div>

                            <div class="mt-1 text-2xl font-black text-status-warning">
                                {{ reservedCount }}
                            </div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.blocked_from_automatic_assignment') }}
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-5 xl:grid-cols-[420px_1fr]">
                        <section class="h-fit rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <div class="mb-5">
                                <div class="flex items-center gap-3">
                                    <Plus class="size-5 text-hive" />

                                    <h2 class="text-lg font-black">
                                        {{ t('ui.add_allocations') }}
                                    </h2>
                                </div>

                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{ t('ui.add_an_exact_ip_and_port_or_generate_a_contiguous_range_for_the_s') }}
                                </p>
                            </div>

                            <form class="space-y-4" @submit.prevent="submit">
                                <div>
                                    <label class="text-sm font-bold text-muted-foreground">
                                        {{ t('ui.ip_address') }}
                                    </label>

                                    <input
                                        v-model="form.ip"
                                        type="text"
                                        placeholder="203.0.113.10"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 font-mono text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                    />

                                    <div v-if="form.errors.ip" class="mt-1 text-xs font-bold text-status-danger">
                                        {{ form.errors.ip }}
                                    </div>
                                </div>

                                <div>
                                    <label class="text-sm font-bold text-muted-foreground">
                                        {{ t('ui.alias') }}
                                    </label>

                                    <input
                                        v-model="form.alias"
                                        type="text"
                                        :placeholder="t('ui.optional_display_alias')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                    />

                                    <div v-if="form.errors.alias" class="mt-1 text-xs font-bold text-status-danger">
                                        {{ form.errors.alias }}
                                    </div>
                                </div>

                                <div>
                                    <label class="text-sm font-bold text-muted-foreground">
                                        {{ t('ui.mode') }}
                                    </label>

                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            class="rounded-button border px-4 py-3 text-sm font-black transition"
                                            :class="
                                                form.mode === 'single'
                                                    ? 'border-hive bg-hive/10 text-hive'
                                                    : 'border-border bg-surface-dark text-muted-foreground hover:text-foreground'
                                            "
                                            @click="form.mode = 'single'"
                                        >
                                            {{ t('ui.single_port') }}
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-button border px-4 py-3 text-sm font-black transition"
                                            :class="
                                                form.mode === 'range'
                                                    ? 'border-hive bg-hive/10 text-hive'
                                                    : 'border-border bg-surface-dark text-muted-foreground hover:text-foreground'
                                            "
                                            @click="form.mode = 'range'"
                                        >
                                            {{ t('ui.port_range') }}
                                        </button>
                                    </div>
                                </div>

                                <div v-if="form.mode === 'single'">
                                    <label class="text-sm font-bold text-muted-foreground">
                                        {{ t('ui.port') }}
                                    </label>

                                    <input
                                        v-model="form.port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        placeholder="25565"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 font-mono text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                    />

                                    <div v-if="form.errors.port" class="mt-1 text-xs font-bold text-status-danger">
                                        {{ form.errors.port }}
                                    </div>
                                </div>

                                <div v-else class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-sm font-bold text-muted-foreground">
                                            {{ t('ui.start_port') }}
                                        </label>

                                        <input
                                            v-model="form.port_start"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            placeholder="25565"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 font-mono text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                        />
                                    </div>

                                    <div>
                                        <label class="text-sm font-bold text-muted-foreground">
                                            {{ t('ui.end_port') }}
                                        </label>

                                        <input
                                            v-model="form.port_end"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            placeholder="25600"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 font-mono text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                        />
                                    </div>

                                    <div
                                        v-if="form.errors.port_start || form.errors.port_end"
                                        class="col-span-2 text-xs font-bold text-status-danger"
                                    >
                                        {{ form.errors.port_start || form.errors.port_end }}
                                    </div>
                                </div>

                                <div>
                                    <label class="text-sm font-bold text-muted-foreground">
                                        {{ t('ui.notes') }}
                                    </label>

                                    <textarea
                                        v-model="form.notes"
                                        rows="3"
                                        :placeholder="t('ui.optional_internal_notes')"
                                        class="mt-2 w-full resize-none rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                    ></textarea>

                                    <div v-if="form.errors.notes" class="mt-1 text-xs font-bold text-status-danger">
                                        {{ form.errors.notes }}
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-button bg-hive px-4 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="form.processing"
                                >
                                    <Plus class="size-4" />
                                    {{ form.processing ? 'Adding...' : 'Add Allocation' }}
                                </button>
                            </form>
                        </section>

                        <section class="overflow-hidden rounded-panel border border-border bg-surface">
                            <div class="flex flex-col gap-3 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                                <div>
                                    <h2 class="text-lg font-black">
                                        {{ t('ui.node_allocations') }}
                                    </h2>

                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ t('ui.exact_ip_and_port_assignments_available_to_this_node_assigned_all') }}
                                    </p>
                                </div>

                                <div class="text-xs font-bold text-muted-foreground">{{ allocations.length }} {{ t('ui.total_1123987') }}</div>
                            </div>

                            <div v-if="allocations.length === 0" class="p-10 text-center">
                                <div class="mx-auto flex size-14 items-center justify-center rounded-full border border-border bg-surface-dark">
                                    <HardDrive class="size-6 text-muted-foreground" />
                                </div>

                                <div class="mt-4 text-lg font-black text-foreground">
                                    {{ t('ui.no_allocations_yet') }}
                                </div>

                                <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                                    {{ t('ui.add_a_single_port_or_a_port_range_to_make_this_node_available_for') }}
                                </p>
                            </div>

                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-border">
                                    <thead class="bg-surface-dark">
                                        <tr>
                                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.address') }}
                                            </th>

                                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.alias') }}
                                            </th>

                                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.status') }}
                                            </th>

                                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.cell_assignment') }}
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
                                        <tr v-for="allocation in allocations" :key="allocation.id" class="transition hover:bg-surface-light/40">
                                            <td class="px-5 py-4">
                                                <div class="font-mono text-sm font-black text-foreground">
                                                    {{ allocation.ip }}:{{ allocation.port }}
                                                </div>

                                                <div
                                                    v-if="allocation.notes"
                                                    class="mt-1 max-w-md truncate text-xs text-muted-foreground"
                                                    :title="allocation.notes"
                                                >
                                                    {{ allocation.notes }}
                                                </div>
                                            </td>

                                            <td class="px-5 py-4">
                                                <div v-if="allocation.alias" class="text-sm font-bold text-foreground">
                                                    {{ allocation.alias }}
                                                </div>

                                                <span v-else class="text-sm text-muted-foreground"> — </span>
                                            </td>

                                            <td class="px-5 py-4">
                                                <span
                                                    class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                                    :class="statusClass(allocation)"
                                                >
                                                    {{ statusLabel(allocation) }}
                                                </span>
                                            </td>

                                            <td class="px-5 py-4">
                                                <div v-if="allocation.cell">
                                                    <Link
                                                        :href="`/admin/cells/${allocation.cell.id}`"
                                                        class="text-sm font-black text-foreground transition hover:text-hive"
                                                    >
                                                        {{ allocation.cell.name }}
                                                    </Link>

                                                    <div class="mt-1 text-xs text-muted-foreground">
                                                        {{ allocation.is_primary ? 'Primary allocation' : 'Additional allocation' }}
                                                    </div>
                                                </div>

                                                <span v-else class="text-sm font-bold text-muted-foreground">
                                                    {{ t('ui.none') }}
                                                </span>
                                            </td>

                                            <td class="px-5 py-4 text-sm font-bold text-muted-foreground">
                                                {{ formatDate(allocation.created_at) }}
                                            </td>

                                            <td class="px-5 py-4">
                                                <div class="flex justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-2 rounded-button border px-3 py-2 text-xs font-black transition disabled:cursor-not-allowed disabled:opacity-40"
                                                        :class="
                                                            allocation.is_reserved
                                                                ? 'border-status-warning/40 bg-status-warning/10 text-status-warning hover:bg-status-warning/20'
                                                                : 'border-border bg-surface-dark text-muted-foreground hover:border-status-warning hover:text-status-warning'
                                                        "
                                                        :disabled="allocation.is_assigned"
                                                        :title="
                                                            allocation.is_assigned
                                                                ? 'Assigned allocations cannot be reserved.'
                                                                : allocation.is_reserved
                                                                  ? 'Make this allocation available again.'
                                                                  : 'Prevent automatic assignment of this allocation.'
                                                        "
                                                        @click="toggleReserve(allocation)"
                                                    >
                                                        <Shield class="size-4" />
                                                        {{ allocation.is_reserved ? 'Unreserve' : 'Reserve' }}
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-2 rounded-button border border-status-danger/40 bg-status-danger/10 px-3 py-2 text-xs font-black text-status-danger transition hover:bg-status-danger/20 disabled:cursor-not-allowed disabled:opacity-40"
                                                        :disabled="allocation.is_assigned"
                                                        :title="
                                                            allocation.is_assigned
                                                                ? 'Assigned allocations cannot be deleted.'
                                                                : 'Delete this allocation.'
                                                        "
                                                        @click="deleteAllocation(allocation)"
                                                    >
                                                        <Trash2 class="size-4" />
                                                        {{ t('ui.delete') }}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="confirmation.open"
            :title="confirmation.title"
            :description="confirmation.description"
            :confirm-text="confirmation.confirmText"
            :cancel-text="confirmation.cancelText"
            :danger="confirmation.danger"
            :loading="confirmation.loading"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </AppLayout>
</template>
