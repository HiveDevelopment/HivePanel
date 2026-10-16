<script setup lang="ts">
import { combGroupLabel, defaultResources, nextResources, resourceRecommendations } from '@/composables/combCreation';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Boxes,
    Check,
    ChevronLeft,
    ChevronRight,
    Container,
    CpuIcon,
    Database,
    Info,
    Plus,
    Settings,
    Terminal,
    User,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AllocationSelector from './Sections/AllocationSelector.vue';
import DeploySummary from './Sections/DeploySummary.vue';
import NodeSelector from './Sections/NodeSelector.vue';
import ResourceLimits from './Sections/ResourceLimits.vue';

type UserRecord = {
    id: number | string;
    name: string;
    email: string;
};

type CombRecord = {
    id: number | string;
    external_id: string;
    name: string;
    game: string;
    category?: string;
    group?: string;
    source?: string;
    data?: any;
};

const props = defineProps<{
    nodes: any[];
    combs: CombRecord[];
    users: UserRecord[];
}>();

const currentStep = ref(1);
const allocations = ref<any[]>([]);
const loadingAllocations = ref(false);
const showAllocationModal = ref(false);
const creatingAllocation = ref(false);
const allocationError = ref('');
const allocationForm = ref({ ip: '', port: '', alias: '' });
const ownerSearchFocused = ref(false);

const form = useForm({
    node_id: '',
    allocation_id: '',
    additional_allocation_ids: [] as any[],

    name: '',
    owner_email: '',
    description: '',
    start_on_completion: true,

    comb_id: '',
    version: '',
    skip_install_script: false,

    ...defaultResources,
    cpu_pinning: '',
    oom_killer: true,
    exclude_from_resource_calculation: false,

    database_limit: null as number | null,
    allocation_limit: null as number | null,
    backup_limit: null as number | null,
    backup_storage_mb: null as number | null,

    docker_image: '',
    startup_command: '',

    variables: {} as Record<string, string>,
});

const steps = [
    { id: 1, label: 'Core', icon: Info },
    { id: 2, label: 'Placement', icon: Settings },
    { id: 3, label: 'Comb', icon: Boxes },
    { id: 4, label: 'Resources', icon: Terminal },
    { id: 5, label: 'Advanced', icon: Database },
    { id: 6, label: 'Review', icon: Check },
];

const selectedNode = computed(() => props.nodes.find((node) => String(node.id) === String(form.node_id)));

const selectedAllocation = computed(() => allocations.value.find((allocation) => String(allocation.id) === String(form.allocation_id)));

const selectedAdditionalAllocations = computed(() =>
    allocations.value.filter((allocation) => form.additional_allocation_ids.map(String).includes(String(allocation.id))),
);

const selectedComb = computed(() => props.combs.find((comb) => String(comb.id) === String(form.comb_id)));

const combVariables = computed<Record<string, any>>(() => {
    const data = selectedComb.value?.data ?? {};
    const variables = data.variables_schema ?? data.variables ?? [];

    if (Array.isArray(variables)) {
        return Object.fromEntries(
            variables.filter((variable) => variable && typeof variable.name === 'string').map((variable) => [variable.name, variable]),
        );
    }

    if (variables && typeof variables === 'object') {
        return variables;
    }

    return {};
});

const hasVersion = computed(() => {
    return Object.keys(combVariables.value).some((key) => key.trim().toLowerCase() === 'version');
});

const editableCombVariables = computed(() =>
    Object.entries(combVariables.value)
        .filter(([key]) => !['version', 'memory', 'server_port', 'server_ip'].includes(key.toLowerCase()))
        .map(([key, definition]) => ({
            key,
            label: typeof definition === 'object' ? definition.name || key : key,
            description: typeof definition === 'object' ? definition.description || '' : '',
            required: typeof definition === 'object' ? Boolean(definition.required) : false,
            type: typeof definition === 'object' ? definition.type || 'string' : 'string',
        })),
);

const versionVariableKey = computed(() => Object.keys(combVariables.value).find((key) => key.toLowerCase() === 'version'));

const filteredUsers = computed(() => {
    const q = form.owner_email.toLowerCase().trim();

    if (!q) return props.users.slice(0, 8);

    return props.users.filter((user) => user.name.toLowerCase().includes(q) || user.email.toLowerCase().includes(q)).slice(0, 8);
});

const groupedCombs = computed(() => {
    return props.combs.reduce<Record<string, CombRecord[]>>((groups, comb) => {
        const game = combGroupLabel(comb);

        if (!groups[game]) groups[game] = [];

        groups[game].push(comb);

        return groups;
    }, {});
});

const recommendedResources = computed(() => resourceRecommendations(selectedComb.value?.data ?? {}));
const hasResourceRecommendations = computed(() => Object.keys(recommendedResources.value).length > 0);
let automaticResources = { ...defaultResources };
function applyResourceRecommendations(force = false) {
    const next = nextResources(form, automaticResources, recommendedResources.value, force);
    Object.assign(form, next.values);
    automaticResources = next.automatic;
}

const canContinue = computed(() => {
    if (currentStep.value === 1) {
        return !!form.name;
    }

    if (currentStep.value === 2) {
        return !!form.node_id && !!form.allocation_id;
    }

    if (currentStep.value === 3) {
        return !!form.comb_id && !!form.docker_image && !!form.startup_command && (!hasVersion.value || !!form.version);
    }

    return true;
});

const canSubmit = computed(
    () =>
        !!form.name &&
        !!form.node_id &&
        !!form.allocation_id &&
        !!form.comb_id &&
        !!form.docker_image &&
        !!form.startup_command &&
        (!hasVersion.value || !!form.version),
);

watch(
    () => form.node_id,
    async (nodeId) => {
        form.allocation_id = '';
        form.additional_allocation_ids = [];
        allocations.value = [];

        if (!nodeId) return;

        loadingAllocations.value = true;

        try {
            const response = await fetch(`/admin/nodes/${nodeId}/available-allocations`, {
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                const data = await response.json();
                allocations.value = data.allocations ?? [];
            }
        } finally {
            loadingAllocations.value = false;
        }
    },
);

watch(
    () => form.allocation_id,
    (allocationId) => {
        form.additional_allocation_ids = form.additional_allocation_ids.filter((id) => String(id) !== String(allocationId));
    },
);

watch(selectedComb, (comb) => {
    applyResourceRecommendations();
    const data = comb?.data ?? {};

    form.docker_image = String(data.docker?.image ?? data.image ?? '');
    form.startup_command = String(data.startup?.command ?? (typeof data.startup === 'string' ? data.startup : ''));

    form.variables = {};
    form.version = '';

    for (const [key, definition] of Object.entries(combVariables.value)) {
        const value =
            definition && typeof definition === 'object'
                ? ((definition as Record<string, any>).default ?? (definition as Record<string, any>).value ?? '')
                : definition;

        form.variables[key] = String(value ?? '');
    }

    const key = Object.keys(form.variables).find((name) => name.toLowerCase() === 'version');

    if (key) {
        form.version = form.variables[key];
    }
});

watch(
    () => form.version,
    (version) => {
        if (versionVariableKey.value) form.variables[versionVariableKey.value] = version;
    },
);

async function createAllocation() {
    if (!form.node_id || creatingAllocation.value) return;
    creatingAllocation.value = true;
    allocationError.value = '';
    try {
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const xsrf = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];
        const response = await fetch(`/admin/nodes/${form.node_id}/allocations`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
            },
            body: JSON.stringify({
                ip: allocationForm.value.ip.trim(),
                port: Number(allocationForm.value.port),
                alias: allocationForm.value.alias.trim() || null,
            }),
        });
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            allocationError.value =
                error.message ||
                Object.values(error.errors || {})
                    .flat()
                    .join(' ') ||
                'Could not create allocation.';
            return;
        }
        const listResponse = await fetch(`/admin/nodes/${form.node_id}/available-allocations`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!listResponse.ok) throw new Error('Allocation created, but the list could not be refreshed.');
        const data = await listResponse.json();
        allocations.value = data.allocations ?? [];
        const created = allocations.value.find(
            (item) => item.ip === allocationForm.value.ip.trim() && Number(item.port) === Number(allocationForm.value.port),
        );
        if (created) form.allocation_id = String(created.id);
        showAllocationModal.value = false;
        allocationForm.value = { ip: '', port: '', alias: '' };
    } catch (error) {
        allocationError.value = error instanceof Error ? error.message : 'Could not create allocation.';
    } finally {
        creatingAllocation.value = false;
    }
}

function selectOwner(user: UserRecord) {
    form.owner_email = user.email;
    ownerSearchFocused.value = false;
}

function nextStep() {
    if (!canContinue.value) return;
    currentStep.value = Math.min(currentStep.value + 1, steps.length);
}

function previousStep() {
    currentStep.value = Math.max(currentStep.value - 1, 1);
}

function goToStep(step: number) {
    currentStep.value = step;
}

function submit() {
    form.post('/admin/cells', {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.version || errors.comb_id || errors.variables) {
                currentStep.value = 3;
            }
        },
    });
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="t('ui.create_cell')" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        {{ t('ui.new_cell') }}
                                    </h1>

                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.add_a_new_cell_to_your_hivepanel_instance_cells_are_isolated_envi') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/cells"
                                class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back') }}
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-border bg-surface p-2">
                        <div class="grid gap-2 md:grid-cols-6">
                            <button
                                v-for="step in steps"
                                :key="step.id"
                                type="button"
                                class="flex items-center gap-3 rounded-button px-4 py-3 text-left transition"
                                :class="
                                    currentStep === step.id
                                        ? 'bg-hive/10 text-hive'
                                        : currentStep > step.id
                                          ? 'bg-status-success/10 text-status-success'
                                          : 'text-muted-foreground hover:bg-surface-light hover:text-foreground'
                                "
                                @click="goToStep(step.id)"
                            >
                                <component :is="step.icon" class="size-4" />
                                <span class="text-sm font-black">{{ step.label }}</span>
                            </button>
                        </div>
                    </section>

                    <form class="grid gap-5 xl:grid-cols-[1fr_380px]" @submit.prevent="submit">
                        <div class="space-y-5">
                            <div
                                v-if="Object.keys(form.errors).length"
                                role="alert"
                                class="rounded-button border border-status-danger/30 bg-status-danger/10 p-4"
                            >
                                <p class="mb-2 text-sm font-bold text-status-danger">
                                    {{ t('ui.unable_to_deploy_cell') }}
                                </p>

                                <ul class="space-y-1 text-sm text-status-danger">
                                    <li v-for="(message, field) in form.errors" :key="field">
                                        {{ message }}
                                    </li>
                                </ul>
                            </div>

                            <section v-if="currentStep === 1" class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Info class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">{{ t('ui.core_details') }}</h2>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-bold text-muted-foreground">{{ t('ui.cell_name') }}</label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            :placeholder="t('ui.survival_server')"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                        />
                                    </div>

                                    <div class="relative">
                                        <label class="text-sm font-bold text-muted-foreground">{{ t('ui.owner_email') }}</label>

                                        <div class="relative mt-2">
                                            <User class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />

                                            <input
                                                v-model="form.owner_email"
                                                type="email"
                                                :placeholder="t('ui.search_name_or_email')"
                                                class="w-full rounded-button border border-border bg-surface-dark py-3 pl-10 pr-4 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                                @focus="ownerSearchFocused = true"
                                                @blur="setTimeout(() => (ownerSearchFocused = false), 150)"
                                            />
                                        </div>

                                        <div
                                            v-if="ownerSearchFocused && filteredUsers.length > 0"
                                            class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-button border border-border bg-surface-dark p-2 shadow-2xl"
                                        >
                                            <button
                                                v-for="user in filteredUsers"
                                                :key="user.id"
                                                type="button"
                                                class="block w-full rounded-button px-3 py-2 text-left transition hover:bg-surface-light"
                                                @mousedown.prevent="selectOwner(user)"
                                            >
                                                <span class="block text-sm font-black text-foreground">
                                                    {{ user.name }}
                                                </span>
                                                <span class="block text-xs font-bold text-muted-foreground">
                                                    {{ user.email }}
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="text-sm font-bold text-muted-foreground">{{ t('ui.description') }}</label>
                                        <textarea
                                            v-model="form.description"
                                            rows="4"
                                            :placeholder="t('ui.optional_internal_description')"
                                            class="mt-2 w-full resize-none rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                        />
                                    </div>

                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-button border border-border bg-surface-dark p-4 md:col-span-2"
                                    >
                                        <input v-model="form.start_on_completion" type="checkbox" class="mt-1" />
                                        <span>
                                            <span class="block text-sm font-black text-foreground">{{ t('ui.start_cell_when_installed') }}</span>
                                            <span class="mt-1 block text-xs leading-5 text-muted-foreground">
                                                {{ t('ui.automatically_start_the_cell_once_installation_completes_successf') }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </section>

                            <template v-if="currentStep === 2">
                                <NodeSelector v-model="form.node_id" :nodes="nodes" />

                                <AllocationSelector
                                    v-model="form.allocation_id"
                                    :allocations="allocations"
                                    :disabled="!form.node_id"
                                    :loading="loadingAllocations"
                                />
                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        :disabled="!form.node_id"
                                        class="inline-flex items-center gap-2 rounded-button border border-hive/40 px-4 py-2 text-sm font-bold text-hive disabled:opacity-40"
                                        @click="
                                            allocationError = '';
                                            showAllocationModal = true;
                                        "
                                    >
                                        <Plus class="size-4" /> {{ t('ui.create_allocation') }}
                                    </button>
                                </div>

                                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                    <div class="mb-5 flex items-center gap-3">
                                        <Settings class="size-5 text-hive" />
                                        <h2 class="text-lg font-black">{{ t('ui.additional_allocations') }}</h2>
                                    </div>

                                    <div
                                        v-if="!form.node_id"
                                        class="rounded-button border border-border bg-surface-dark p-4 text-sm font-bold text-muted-foreground"
                                    >
                                        {{ t('ui.select_a_node_first') }}
                                    </div>

                                    <div
                                        v-else-if="allocations.length === 0"
                                        class="rounded-button border border-border bg-surface-dark p-4 text-sm font-bold text-muted-foreground"
                                    >
                                        {{ t('ui.no_allocations_available') }}
                                    </div>

                                    <div v-else class="max-h-64 space-y-2 overflow-y-auto rounded-button border border-border bg-surface-dark p-3">
                                        <label
                                            v-for="allocation in allocations.filter((item) => String(item.id) !== String(form.allocation_id))"
                                            :key="allocation.id"
                                            class="flex cursor-pointer items-center justify-between gap-3 rounded-button px-3 py-2 hover:bg-surface-light"
                                        >
                                            <span>
                                                <span class="block font-mono text-sm font-black text-foreground">
                                                    {{ allocation.ip }}:{{ allocation.port }}
                                                </span>
                                                <span class="block text-xs font-bold text-muted-foreground">
                                                    {{ allocation.alias || 'No alias' }}
                                                </span>
                                            </span>

                                            <input v-model="form.additional_allocation_ids" type="checkbox" :value="allocation.id" />
                                        </label>
                                    </div>
                                </section>
                            </template>

                            <section v-if="currentStep === 3" class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Boxes class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">{{ t('ui.comb_startup') }}</h2>
                                </div>

                                <div v-if="combs.length === 0" class="rounded-button border border-border bg-surface-dark p-8 text-center">
                                    <Boxes class="mx-auto size-10 text-muted-foreground" />
                                    <h3 class="mt-4 text-lg font-black text-foreground">{{ t('ui.no_combs_installed') }}</h3>
                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.import_a_comb_from_hiveregistry_or_create_a_manual_comb_first') }}
                                    </p>

                                    <Link
                                        href="/admin/combs"
                                        class="mt-5 inline-flex items-center justify-center rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:opacity-90"
                                    >
                                        {{ t('ui.manage_combs') }}
                                    </Link>
                                </div>

                                <div v-else class="space-y-5">
                                    <div class="space-y-4">
                                        <div v-for="(items, game) in groupedCombs" :key="game" class="space-y-2">
                                            <h3 class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ game }}
                                            </h3>

                                            <div class="grid gap-3 md:grid-cols-2">
                                                <button
                                                    v-for="comb in items"
                                                    :key="comb.id"
                                                    type="button"
                                                    class="rounded-button border p-4 text-left transition hover:-translate-y-0.5 hover:border-hive/40 hover:bg-surface-hover active:translate-y-0"
                                                    :class="
                                                        String(form.comb_id) === String(comb.id)
                                                            ? 'border-hive bg-hive/10'
                                                            : 'border-border bg-surface-dark'
                                                    "
                                                    @click="form.comb_id = String(comb.id)"
                                                >
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <h4 class="text-sm font-black text-foreground">
                                                                {{ comb.name }}
                                                            </h4>

                                                            <p class="mt-1 font-mono text-xs text-muted-foreground">
                                                                {{ comb.external_id }}
                                                            </p>
                                                        </div>

                                                        <span
                                                            class="rounded-full border px-2 py-0.5 text-xs font-bold"
                                                            :class="
                                                                comb.source === 'registry'
                                                                    ? 'border-hive/30 bg-hive/10 text-hive'
                                                                    : 'border-border bg-surface-light text-muted-foreground'
                                                            "
                                                        >
                                                            {{ comb.source || 'local' }}
                                                        </span>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="selectedComb && (hasVersion || editableCombVariables.length)"
                                        class="rounded-button border border-hive/20 bg-hive/5 p-4"
                                    >
                                        <h3 class="text-sm font-black text-foreground">{{ t('ui.environment_variables') }}</h3>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {{ t('ui.configure_the_variables_used_by_this_comb_during_installation_and') }}
                                        </p>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div v-if="hasVersion">
                                            <label class="text-sm font-bold text-muted-foreground">{{ t('ui.server_version') }}</label>
                                            <input
                                                v-model="form.version"
                                                type="text"
                                                :placeholder="t('ui.enter_server_version')"
                                                class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                            />
                                        </div>

                                        <div v-for="variable in editableCombVariables" :key="variable.key">
                                            <label class="text-sm font-bold text-muted-foreground"
                                                >{{ variable.label }}<span v-if="variable.required" class="text-status-danger"> *</span></label
                                            >
                                            <input
                                                v-model="form.variables[variable.key]"
                                                :type="variable.type === 'number' || variable.type === 'integer' ? 'number' : 'text'"
                                                :required="variable.required"
                                                class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                            />
                                            <p v-if="variable.description" class="mt-1 text-xs text-muted-foreground">{{ variable.description }}</p>
                                        </div>

                                        <div>
                                            <label class="flex items-center gap-2 text-sm font-bold text-muted-foreground">
                                                <Container class="size-4 text-muted-foreground" />
                                                {{ t('ui.docker_image') }}
                                            </label>
                                            <input
                                                v-model="form.docker_image"
                                                type="text"
                                                :placeholder="t('ui.comb_docker_image')"
                                                class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 font-mono text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                            />
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="text-sm font-bold text-muted-foreground">{{ t('ui.startup_command') }}</label>
                                            <textarea
                                                v-model="form.startup_command"
                                                rows="4"
                                                class="mt-2 w-full resize-none rounded-button border border-border bg-surface-dark px-4 py-3 font-mono text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                            />
                                        </div>

                                        <label
                                            class="flex cursor-pointer items-start gap-3 rounded-button border border-border bg-surface-dark p-4 md:col-span-2"
                                        >
                                            <input v-model="form.skip_install_script" type="checkbox" class="mt-1" />
                                            <span>
                                                <span class="block text-sm font-black text-foreground">{{ t('ui.skip_install_script') }}</span>
                                                <span class="mt-1 block text-xs leading-5 text-muted-foreground">
                                                    {{ t('ui.skip_the_comb_install_script_during_deployment') }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <div v-if="currentStep === 4" class="space-y-5">
                                <section class="flex flex-wrap items-center justify-between gap-3 rounded-panel border border-hive/20 bg-hive/5 p-5">
                                    <div>
                                        <h3 class="text-sm font-bold text-foreground">
                                            {{ hasResourceRecommendations ? 'Comb resource recommendations' : 'Resource limits' }}
                                        </h3>
                                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                            {{
                                                hasResourceRecommendations
                                                    ? `${selectedComb?.name} provides starting values. Your manual changes are kept when switching Combs; you can reapply its suggestions below.`
                                                    : 'This Comb has no resource recommendations. Generic defaults are provided; adjust them for your workload.'
                                            }}
                                        </p>
                                    </div>
                                    <button
                                        v-if="hasResourceRecommendations"
                                        type="button"
                                        class="shrink-0 rounded-button border border-hive/30 bg-hive/10 px-4 py-2 text-xs font-bold text-hive"
                                        @click="applyResourceRecommendations(true)"
                                    >
                                        {{ t('ui.use_comb_recommendations') }}
                                    </button>
                                </section>
                                <ResourceLimits
                                    v-model:memory-mb="form.memory_mb"
                                    v-model:overhead-memory-mb="form.overhead_memory_mb"
                                    v-model:swap-mb="form.swap_mb"
                                    v-model:disk-mb="form.disk_mb"
                                    v-model:cpu-percent="form.cpu_percent"
                                    v-model:cpu-pinning="form.cpu_pinning"
                                    v-model:io-weight="form.io_weight"
                                    v-model:oom-killer="form.oom_killer"
                                    v-model:exclude-from-resource-calculation="form.exclude_from_resource_calculation"
                                />
                            </div>

                            <section v-if="currentStep === 5" class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Database class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">{{ t('ui.feature_limits_advanced') }}</h2>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-bold text-muted-foreground">{{ t('ui.database_limit') }}</label>
                                        <input
                                            v-model="form.database_limit"
                                            type="number"
                                            min="0"
                                            :placeholder="t('ui.unlimited')"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                        />
                                    </div>

                                    <div>
                                        <label class="text-sm font-bold text-muted-foreground">{{ t('ui.allocation_limit') }}</label>
                                        <input
                                            v-model="form.allocation_limit"
                                            type="number"
                                            min="0"
                                            :placeholder="t('ui.unlimited')"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                        />
                                    </div>

                                    <div>
                                        <label class="text-sm font-bold text-muted-foreground">{{ t('ui.backup_limit') }}</label>
                                        <input
                                            v-model="form.backup_limit"
                                            type="number"
                                            min="0"
                                            :placeholder="t('ui.unlimited')"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                        />
                                    </div>

                                    <div>
                                        <label class="text-sm font-bold text-muted-foreground">{{ t('ui.backup_storage_limit') }}</label>
                                        <div class="relative mt-2">
                                            <input
                                                v-model="form.backup_storage_mb"
                                                type="number"
                                                min="0"
                                                :placeholder="t('ui.unlimited')"
                                                class="w-full rounded-button border border-border bg-surface-dark px-4 py-3 pr-14 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                            />
                                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-muted-foreground">{{
                                                t('ui.mib')
                                            }}</span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section v-if="currentStep === 6" class="rounded-panel border border-hive/30 bg-hive/5 p-5 sm:p-6">
                                <div class="mb-3 flex items-center gap-3">
                                    <Check class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">{{ t('ui.ready_to_deploy') }}</h2>
                                </div>

                                <p class="text-sm leading-6 text-muted-foreground">
                                    {{ t('ui.review_the_summary_on_the_right_when_everything_looks_correct_dep') }}
                                </p>
                            </section>

                            <div class="flex items-center justify-between gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-light px-4 py-2 text-sm font-bold text-foreground transition hover:border-hive hover:text-hive disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="currentStep === 1"
                                    @click="previousStep"
                                >
                                    <ChevronLeft class="size-4" />
                                    {{ t('ui.back') }}
                                </button>

                                <button
                                    v-if="currentStep < steps.length"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canContinue"
                                    @click="nextStep"
                                >
                                    {{ t('ui.continue') }}
                                    <ChevronRight class="size-4" />
                                </button>
                            </div>
                        </div>

                        <DeploySummary
                            :node="selectedNode"
                            :allocation="selectedAllocation"
                            :additional-allocations="selectedAdditionalAllocations"
                            :name="form.name"
                            :comb="selectedComb ? `${combGroupLabel(selectedComb)} / ${selectedComb.name}` : ''"
                            :version="hasVersion ? form.version : ''"
                            :owner-email="form.owner_email"
                            :memory-mb="form.memory_mb"
                            :disk-mb="form.disk_mb"
                            :cpu-percent="form.cpu_percent"
                            :docker-image="form.docker_image"
                            :startup-command="form.startup_command"
                            :processing="form.processing"
                            :can-submit="canSubmit"
                        />
                    </form>
                </div>
            </main>
        </div>
        <div
            v-if="showAllocationModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4"
            @click.self="showAllocationModal = false"
        >
            <form class="w-full max-w-md space-y-4 rounded-panel border border-border bg-surface p-6" @submit.prevent="createAllocation">
                <h2 class="text-lg font-black">{{ t('ui.create_allocation') }}</h2>
                <p class="text-sm text-muted-foreground">{{ t('ui.add_an_allocation_to_the_selected_node_and_select_it_for_this_cel') }}</p>
                <p v-if="allocationError" role="alert" class="text-sm text-status-danger">{{ allocationError }}</p>
                <label class="block text-sm font-bold text-muted-foreground"
                    >{{ t('ui.ip_address') }}
                    <input
                        v-model="allocationForm.ip"
                        required
                        placeholder="0.0.0.0"
                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-foreground"
                    />
                </label>
                <label class="block text-sm font-bold text-muted-foreground"
                    >{{ t('ui.port') }}
                    <input
                        v-model="allocationForm.port"
                        required
                        type="number"
                        min="1"
                        max="65535"
                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-foreground"
                    />
                </label>
                <label class="block text-sm font-bold text-muted-foreground"
                    >{{ t('ui.alias_optional') }}
                    <input
                        v-model="allocationForm.alias"
                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-foreground"
                    />
                </label>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-button border border-border px-4 py-2" @click="showAllocationModal = false">
                        {{ t('ui.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="creatingAllocation"
                        class="rounded-button bg-hive px-4 py-2 font-bold text-black disabled:opacity-50"
                    >
                        {{ creatingAllocation ? 'Creating...' : 'Create Allocation' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
