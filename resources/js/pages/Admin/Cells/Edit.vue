<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Cpu, HardDrive, MemoryStick, Save, Server, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    cell: any;
    editState: {
        status: 'ready' | 'running' | 'unreachable' | 'missing' | 'error';
        editable: boolean;
        message: string;
    };
    variableSchema: {
        name: string;
        label?: string;
        description?: string;
        type?: string;
        required?: boolean;
        default?: string | number | boolean;
        options?: any[];
    }[];
    allocations: {
        id: string;
        ip: string;
        port: number;
        alias?: string | null;
        label: string;
        assigned_to_cell?: boolean;
        primary?: boolean;
    }[];
}>();

const form = useForm({
    name: props.cell.name ?? '',
    variables: Object.fromEntries(
        props.variableSchema
            .filter((variable) => !['memory', 'server_ip', 'server_port'].includes(variable.name))
            .map((variable) => [variable.name, String(props.cell.variables?.[variable.name] ?? variable.default ?? '')]),
    ),
    memory_mb: Number(props.cell.limits?.memory_mb ?? 1024),
    cpu_percent: Number(props.cell.limits?.cpu_percent ?? 100),
    disk_mb: Number(props.cell.limits?.disk_mb ?? 0),
    allocation_id: props.cell.allocation?.id ?? '',
    additional_allocation_ids: (props.cell.additional_allocations ?? []).map((allocation: any) => allocation.id),
});

const editableVariables = computed(() => props.variableSchema.filter((variable) => !['memory', 'server_ip', 'server_port'].includes(variable.name)));

function editStateTitle() {
    switch (props.editState.status) {
        case 'ready':
            return 'Ready to edit';

        case 'running':
            return 'Cell is running';

        case 'unreachable':
            return 'Worker unreachable';

        case 'missing':
            return 'Cell missing from Worker';

        default:
            return 'Editing unavailable';
    }
}

function editStateClass() {
    if (props.editState.status === 'ready') {
        return 'border-status-success/30 bg-status-success/10';
    }

    if (props.editState.status === 'running') {
        return 'border-status-warning/30 bg-status-warning/10';
    }

    return 'border-status-danger/30 bg-status-danger/10';
}

function editStateTextClass() {
    if (props.editState.status === 'ready') {
        return 'text-status-success';
    }

    if (props.editState.status === 'running') {
        return 'text-status-warning';
    }

    return 'text-status-danger';
}

function submit() {
    form.patch(`/admin/cells/${props.cell.id}`, {
        preserveScroll: true,
    });
}

function formatMb(value: number) {
    if (!value) return 'Unlimited';

    if (value >= 1024) {
        return `${(value / 1024).toFixed(value % 1024 === 0 ? 0 : 1)} GB`;
    }

    return `${value} MB`;
}

function isPrimaryAllocation(id: string) {
    return String(form.allocation_id) === String(id);
}

function toggleAdditionalAllocation(id: string) {
    const value = String(id);

    if (isPrimaryAllocation(value)) {
        form.additional_allocation_ids = form.additional_allocation_ids.filter((allocationId: string) => String(allocationId) !== value);
        return;
    }

    if (form.additional_allocation_ids.some((allocationId: string) => String(allocationId) === value)) {
        form.additional_allocation_ids = form.additional_allocation_ids.filter((allocationId: string) => String(allocationId) !== value);
        return;
    }

    form.additional_allocation_ids = [...form.additional_allocation_ids, value];
}

function additionalAllocationSelected(id: string) {
    return form.additional_allocation_ids.some((allocationId: string) => String(allocationId) === String(id));
}

function primaryAllocationChanged() {
    const primaryId = String(form.allocation_id);

    form.additional_allocation_ids = form.additional_allocation_ids.filter((allocationId: string) => String(allocationId) !== primaryId);
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Edit ${cell.name}`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex size-12 shrink-0 items-center justify-center rounded-button border border-border bg-surface-dark text-hive"
                                >
                                    <Server class="size-6" />
                                </div>

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        {{ t('ui.edit_cell') }}
                                    </h1>

                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.update_the_cell_name_and_resource_limits_stored_by_hivepanel_and_') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="`/admin/cells/${cell.id}`"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-light px-4 py-2 text-sm font-bold text-foreground transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back_to_cell') }}
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border p-5 sm:p-6" :class="editStateClass()">
                        <div class="flex items-start gap-3">
                            <TriangleAlert v-if="!editState.editable" class="mt-0.5 size-5 shrink-0" :class="editStateTextClass()" />

                            <Server v-else class="mt-0.5 size-5 shrink-0" :class="editStateTextClass()" />

                            <div>
                                <h2 class="text-sm font-black" :class="editStateTextClass()">
                                    {{ editStateTitle() }}
                                </h2>

                                <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                    {{ editState.message }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <form class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" @submit.prevent="submit">
                        <fieldset class="contents" :disabled="!editState.editable || form.processing">
                            <div class="space-y-5">
                                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                    <div>
                                        <h2 class="text-lg font-black">
                                            {{ t('ui.general') }}
                                        </h2>

                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{ t('ui.basic_identity_for_this_cell') }}
                                        </p>
                                    </div>

                                    <div class="mt-5">
                                        <label for="name" class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.cell_name') }}
                                        </label>

                                        <input
                                            id="name"
                                            v-model="form.name"
                                            type="text"
                                            maxlength="255"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                            :class="{ 'border-status-danger': form.errors.name }"
                                        />

                                        <p v-if="form.errors.name" class="mt-2 text-xs font-bold text-status-danger">
                                            {{ form.errors.name }}
                                        </p>
                                    </div>
                                </section>

                                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                    <div>
                                        <h2 class="text-lg font-black">
                                            {{ t('ui.resource_limits') }}
                                        </h2>

                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{ t('ui.these_values_become_the_authoritative_hivepanel_definition_and_ar') }}
                                        </p>
                                    </div>

                                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                                        <div>
                                            <label
                                                for="memory_mb"
                                                class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-muted-foreground"
                                            >
                                                <MemoryStick class="size-4 text-hive" />
                                                {{ t('ui.memory') }}
                                            </label>

                                            <div class="relative mt-2">
                                                <input
                                                    id="memory_mb"
                                                    v-model.number="form.memory_mb"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    class="w-full rounded-button border border-border bg-surface-dark px-4 py-3 pr-12 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                                    :class="{ 'border-status-danger': form.errors.memory_mb }"
                                                />
                                                <span
                                                    class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-black text-muted-foreground"
                                                >
                                                    {{ t('ui.mb') }}
                                                </span>
                                            </div>

                                            <p class="mt-2 text-xs text-muted-foreground">
                                                {{ formatMb(Number(form.memory_mb)) }}
                                            </p>

                                            <p v-if="form.errors.memory_mb" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ form.errors.memory_mb }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                for="cpu_percent"
                                                class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-muted-foreground"
                                            >
                                                <Cpu class="size-4 text-hive" />
                                                {{ t('ui.cpu') }}
                                            </label>

                                            <div class="relative mt-2">
                                                <input
                                                    id="cpu_percent"
                                                    v-model.number="form.cpu_percent"
                                                    type="number"
                                                    min="0"
                                                    max="1000"
                                                    step="1"
                                                    class="w-full rounded-button border border-border bg-surface-dark px-4 py-3 pr-10 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                                    :class="{ 'border-status-danger': form.errors.cpu_percent }"
                                                />
                                                <span
                                                    class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-black text-muted-foreground"
                                                >
                                                    %
                                                </span>
                                            </div>

                                            <p class="mt-2 text-xs text-muted-foreground">
                                                {{ Number(form.cpu_percent) }}{{ t('ui.cpu_allocation') }}
                                            </p>

                                            <p v-if="form.errors.cpu_percent" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ form.errors.cpu_percent }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                for="disk_mb"
                                                class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-muted-foreground"
                                            >
                                                <HardDrive class="size-4 text-hive" />
                                                {{ t('ui.disk') }}
                                            </label>

                                            <div class="relative mt-2">
                                                <input
                                                    id="disk_mb"
                                                    v-model.number="form.disk_mb"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    class="w-full rounded-button border border-border bg-surface-dark px-4 py-3 pr-12 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                                    :class="{ 'border-status-danger': form.errors.disk_mb }"
                                                />
                                                <span
                                                    class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-black text-muted-foreground"
                                                >
                                                    {{ t('ui.mb') }}
                                                </span>
                                            </div>

                                            <p class="mt-2 text-xs text-muted-foreground">
                                                {{ formatMb(Number(form.disk_mb)) }}
                                            </p>

                                            <p v-if="form.errors.disk_mb" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ form.errors.disk_mb }}
                                            </p>
                                        </div>
                                    </div>
                                </section>

                                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h2 class="text-lg font-black">
                                                {{ t('ui.primary_allocation') }}
                                            </h2>

                                            <p class="mt-1 text-sm text-muted-foreground">
                                                {{ t('ui.move_this_cell_to_another_available_allocation_on_the_same_worker') }}
                                            </p>
                                        </div>

                                        <Server class="size-5 shrink-0 text-hive" />
                                    </div>

                                    <div class="mt-5">
                                        <label for="allocation_id" class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.allocation') }}
                                        </label>

                                        <select
                                            id="allocation_id"
                                            v-model="form.allocation_id"
                                            @change="primaryAllocationChanged"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                            :class="{ 'border-status-danger': form.errors.allocation_id }"
                                        >
                                            <option v-for="allocation in allocations" :key="allocation.id" :value="allocation.id">
                                                {{ allocation.label }}
                                            </option>
                                        </select>

                                        <div class="mt-3 rounded-button border border-border bg-surface-dark p-4">
                                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.current_allocation') }}
                                            </div>

                                            <div class="mt-2 font-mono text-sm font-black text-foreground">
                                                <template v-if="cell.allocation"> {{ cell.allocation.ip }}:{{ cell.allocation.port }} </template>
                                                <template v-else>
                                                    {{ t('ui.missing') }}
                                                </template>
                                            </div>
                                        </div>

                                        <p class="mt-3 text-xs leading-5 text-muted-foreground">
                                            {{ t('ui.only_unreserved_allocations_on') }} {{ cell.node?.name || 'this node' }}
                                            {{ t('ui.are_shown_changing_the_primary_allocation_also_updates_the_stored') }}
                                        </p>

                                        <p v-if="form.errors.allocation_id" class="mt-2 text-xs font-bold text-status-danger">
                                            {{ form.errors.allocation_id }}
                                        </p>
                                    </div>
                                </section>

                                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h2 class="text-lg font-black">
                                                {{ t('ui.additional_allocations') }}
                                            </h2>

                                            <p class="mt-1 text-sm text-muted-foreground">
                                                {{ t('ui.assign_extra_ip_and_port_combinations_to_this_cell_the_primary_al') }}
                                            </p>
                                        </div>

                                        <Server class="size-5 shrink-0 text-hive" />
                                    </div>

                                    <div class="mt-5">
                                        <div
                                            v-if="allocations.length === 0"
                                            class="rounded-button border border-border bg-surface-dark p-4 text-sm font-bold text-muted-foreground"
                                        >
                                            {{ t('ui.no_allocations_are_currently_available_on_this_node') }}
                                        </div>

                                        <div v-else class="grid gap-3 md:grid-cols-2">
                                            <button
                                                v-for="allocation in allocations"
                                                :key="allocation.id"
                                                type="button"
                                                class="flex items-start gap-3 rounded-button border p-4 text-left transition"
                                                :class="[
                                                    isPrimaryAllocation(allocation.id)
                                                        ? 'cursor-not-allowed border-hive/30 bg-hive/10 opacity-60'
                                                        : additionalAllocationSelected(allocation.id)
                                                          ? 'border-hive bg-hive/10'
                                                          : 'border-border bg-surface-dark hover:border-border',
                                                ]"
                                                :disabled="isPrimaryAllocation(allocation.id)"
                                                @click="toggleAdditionalAllocation(allocation.id)"
                                            >
                                                <span
                                                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border text-[10px] font-black"
                                                    :class="
                                                        additionalAllocationSelected(allocation.id)
                                                            ? 'border-hive bg-hive text-black'
                                                            : 'border-border bg-surface text-transparent'
                                                    "
                                                >
                                                    ✓
                                                </span>

                                                <span class="min-w-0 flex-1">
                                                    <span class="block font-mono text-sm font-black text-foreground">
                                                        {{ allocation.ip }}:{{ allocation.port }}
                                                    </span>

                                                    <span v-if="allocation.alias" class="mt-1 block text-xs font-bold text-muted-foreground">
                                                        {{ allocation.alias }}
                                                    </span>

                                                    <span
                                                        v-if="isPrimaryAllocation(allocation.id)"
                                                        class="mt-2 inline-flex rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-hive"
                                                    >
                                                        {{ t('ui.primary') }}
                                                    </span>

                                                    <span
                                                        v-else-if="allocation.assigned_to_cell"
                                                        class="mt-2 inline-flex rounded-full border border-border bg-surface-light px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-muted-foreground"
                                                    >
                                                        {{ t('ui.assigned') }}
                                                    </span>
                                                </span>
                                            </button>
                                        </div>

                                        <p class="mt-3 text-xs leading-5 text-muted-foreground">
                                            {{ t('ui.additional_allocations_may_use_different_ip_addresses_and_may_reu') }}
                                        </p>

                                        <p v-if="form.errors.additional_allocation_ids" class="mt-2 text-xs font-bold text-status-danger">
                                            {{ form.errors.additional_allocation_ids }}
                                        </p>
                                    </div>
                                </section>

                                <section v-if="editableVariables.length" class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                    <h2 class="text-lg font-black">{{ t('ui.environment_variables') }}</h2>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ t('ui.change_the_variables_defined_by_this_cell_s_comb_stop_the_cell_be') }}
                                    </p>
                                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                        <div v-for="variable in editableVariables" :key="variable.name">
                                            <label
                                                :for="`variable-${variable.name}`"
                                                class="text-xs font-black uppercase tracking-wide text-muted-foreground"
                                            >
                                                {{ variable.label || variable.name
                                                }}<span v-if="variable.required" class="text-status-danger"> *</span>
                                            </label>
                                            <select
                                                v-if="variable.type === 'boolean'"
                                                :id="`variable-${variable.name}`"
                                                v-model="form.variables[variable.name]"
                                                class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                            >
                                                <option value="true">{{ t('ui.true') }}</option>
                                                <option value="false">{{ t('ui.false') }}</option>
                                            </select>
                                            <select
                                                v-else-if="variable.options?.length"
                                                :id="`variable-${variable.name}`"
                                                v-model="form.variables[variable.name]"
                                                class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                            >
                                                <option
                                                    v-for="option in variable.options"
                                                    :key="String(typeof option === 'object' ? option.value : option)"
                                                    :value="String(typeof option === 'object' ? option.value : option)"
                                                >
                                                    {{ typeof option === 'object' ? option.label || option.value : option }}
                                                </option>
                                            </select>
                                            <input
                                                v-else
                                                :id="`variable-${variable.name}`"
                                                v-model="form.variables[variable.name]"
                                                :type="variable.type === 'number' || variable.type === 'integer' ? 'number' : 'text'"
                                                :required="variable.required"
                                                class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm text-foreground outline-none transition focus:border-hive"
                                                :class="{ 'border-status-danger': form.errors[`variables.${variable.name}`] }"
                                            />
                                            <p v-if="variable.description" class="mt-2 text-xs text-muted-foreground">{{ variable.description }}</p>
                                            <p v-if="form.errors[`variables.${variable.name}`]" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ form.errors[`variables.${variable.name}`] }}
                                            </p>
                                        </div>
                                    </div>
                                </section>

                                <section v-if="form.errors.worker" class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-5">
                                    <div class="flex items-start gap-3">
                                        <TriangleAlert class="mt-0.5 size-5 shrink-0 text-status-danger" />

                                        <div>
                                            <h2 class="text-sm font-black text-status-danger">
                                                {{ t('ui.worker_update_failed') }}
                                            </h2>

                                            <p class="mt-1 text-sm leading-6 text-foreground">
                                                {{ form.errors.worker }}
                                            </p>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <aside class="space-y-5">
                                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                    <h2 class="text-lg font-black">
                                        {{ t('ui.current_cell') }}
                                    </h2>

                                    <div class="mt-5 space-y-3">
                                        <div class="rounded-button border border-border bg-surface-dark p-4">
                                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.node') }}
                                            </div>
                                            <div class="mt-2 text-sm font-black text-foreground">
                                                {{ cell.node?.name || 'Unknown' }}
                                            </div>
                                        </div>

                                        <div class="rounded-button border border-border bg-surface-dark p-4">
                                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.allocation') }}
                                            </div>
                                            <div class="mt-2 font-mono text-sm font-black text-foreground">
                                                <template v-if="cell.allocation"> {{ cell.allocation.ip }}:{{ cell.allocation.port }} </template>
                                                <template v-else>
                                                    {{ t('ui.missing') }}
                                                </template>
                                            </div>
                                        </div>

                                        <div class="rounded-button border border-border bg-surface-dark p-4">
                                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.additional_allocations') }}
                                            </div>

                                            <div class="mt-2 text-sm font-black text-foreground">
                                                {{ form.additional_allocation_ids.length }}
                                            </div>
                                        </div>

                                        <div class="rounded-button border border-border bg-surface-dark p-4">
                                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.comb') }}
                                            </div>
                                            <div class="mt-2 text-sm font-black text-foreground">
                                                {{ cell.comb }}
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-3 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="form.processing || !editState.editable"
                                >
                                    <Save class="size-4" />
                                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </aside>
                        </fieldset>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
