<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CircleAlert,
    CircleCheck,
    Clipboard,
    Database,
    HardDrive,
    Key,
    KeyRound,
    Network,
    Save,
    Server,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    migration: any;
    servers: any[];
    summary: {
        total: number;
        ready: number;
        warnings: number;
        blocked: number;
    };
    transferNodes: any[];
    transferComplete: boolean;
    databaseTransferHosts: any[];
    databaseTransferComplete: boolean;
    selectedDatabaseCount: number;
}>();

const preparing = ref(false);
const generatingKeyFor = ref<string | null>(null);
const copiedCommandFor = ref<string | null>(null);
const localDetectionFor = ref<string | null>(null);
const localDetectionErrors = ref<Record<string, string>>({});
const localPathOverrides = ref<Record<string, boolean>>(Object.fromEntries(props.transferNodes.map((node) => [node.source_node, false])));
const localDetections = ref<Record<string, any>>(
    Object.fromEntries(props.transferNodes.map((node) => [node.source_node, node.local_detection ?? null])),
);

const transferForm = useForm({
    nodes: Object.fromEntries(
        props.transferNodes.map((node) => [
            node.source_node,
            {
                protocol: node.protocol,
                host: node.host,
                port: node.port,
                username: node.username,
                auth_type: node.auth_type ?? 'private_key',
                password: '',
                private_key: '',
                private_key_passphrase: '',
                path_template: node.path_template,
                same_machine_confirmed: node.same_machine_confirmed ?? false,
                source_servers_stopped_confirmed: node.source_servers_stopped_confirmed ?? false,
                file_strategy: node.file_strategy ?? 'copy',
            },
        ]),
    ),
});

const databaseTransferForm = useForm({
    hosts: Object.fromEntries(
        props.databaseTransferHosts.map((host) => [
            host.key,
            {
                source_username: host.source_username ?? '',
                source_password: '',
                destination_host: host.destination_host ?? '',
                destination_port: host.destination_port ?? 3306,
                destination_username: host.destination_username ?? '',
                destination_password: '',
                destination_prefix: host.destination_prefix ?? 'hive_',
            },
        ]),
    ),
});

const startReady = computed(() => props.summary.blocked === 0 && props.transferComplete && props.databaseTransferComplete);

async function generateMigrationKey(node: any) {
    if (generatingKeyFor.value) return;

    generatingKeyFor.value = node.source_node;

    try {
        const response = await fetch(`/admin/migrations/${props.migration.id}/transfer/generate-key`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                source_node: node.source_node,
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message ?? 'Could not generate the migration SSH key.');
        }

        transferForm.nodes[node.source_node].auth_type = 'private_key';
        transferForm.nodes[node.source_node].username = 'hivepanel-migration';

        node.auth_type = 'private_key';
        node.has_private_key = true;
        node.public_key = payload.public_key;
        node.setup_command = payload.setup_command;
    } catch (error: any) {
        transferForm.setError('transfer', error?.message ?? 'Could not generate the migration SSH key.');
    } finally {
        generatingKeyFor.value = null;
    }
}

async function copySetupCommand(node: any) {
    if (!node.setup_command) return;

    await navigator.clipboard.writeText(node.setup_command);

    copiedCommandFor.value = node.source_node;

    window.setTimeout(() => {
        if (copiedCommandFor.value === node.source_node) {
            copiedCommandFor.value = null;
        }
    }, 2000);
}

function markLocalPathOverride(node: any) {
    localPathOverrides.value[node.source_node] = true;
    localDetections.value[node.source_node] = null;
    delete localDetectionErrors.value[node.source_node];
}

function localDetection(node: any) {
    return localDetections.value[node.source_node] ?? null;
}

function localDetectionReady(node: any) {
    const detection = localDetection(node);

    const runtimeReady = detection?.runtime_check?.available !== true || detection?.runtime_check?.all_stopped === true;

    return Boolean(detection?.detected && (detection?.missing_uuids?.length ?? 0) === 0 && detection?.enough_space !== false && runtimeReady);
}

function runtimeCheck(node: any) {
    return localDetection(node)?.runtime_check ?? null;
}

function runtimeStateLabel(node: any) {
    const runtime = runtimeCheck(node);

    if (!runtime?.available) {
        return 'Not automatically verified';
    }

    if (runtime.all_stopped === true) {
        return 'No active source containers detected';
    }

    return `${runtime.active_count ?? 0} active source container${runtime.active_count === 1 ? '' : 's'} detected`;
}

function runtimeStateClass(node: any) {
    const runtime = runtimeCheck(node);

    if (!runtime?.available) {
        return 'border-status-warning/20 bg-status-warning/5 text-status-warning';
    }

    if (runtime.all_stopped === true) {
        return 'border-status-success/20 bg-status-success/5 text-status-success';
    }

    return 'border-status-danger/20 bg-status-danger/5 text-status-danger';
}

function localStatusLabel(node: any) {
    if (localDetectionFor.value === node.source_node) {
        return 'Detecting local source';
    }

    if (localDetectionReady(node)) {
        return 'Local source detected';
    }

    if (localDetectionErrors.value[node.source_node]) {
        return 'Detection failed';
    }

    if (transferForm.nodes[node.source_node].same_machine_confirmed && transferForm.nodes[node.source_node].source_servers_stopped_confirmed) {
        return 'Local source not verified';
    }

    return 'Confirmation required';
}

function localStatusClass(node: any) {
    if (localDetectionReady(node)) {
        return 'border-status-success/30 bg-status-success/10 text-status-success';
    }

    if (localDetectionErrors.value[node.source_node]) {
        return 'border-status-danger/30 bg-status-danger/10 text-status-danger';
    }

    return 'border-status-warning/30 bg-status-warning/10 text-status-warning';
}

async function detectLocalSource(node: any, force = false) {
    const formNode = transferForm.nodes[node.source_node];

    if (!formNode || formNode.protocol !== 'local') {
        return;
    }

    if (!force && localDetectionReady(node) && !localPathOverrides.value[node.source_node]) {
        return;
    }

    if (localDetectionFor.value === node.source_node) {
        return;
    }

    localDetectionFor.value = node.source_node;
    delete localDetectionErrors.value[node.source_node];

    try {
        const response = await fetch(`/admin/migrations/${props.migration.id}/transfer/detect-local`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                source_node: node.source_node,
                configured_path_template: localPathOverrides.value[node.source_node] ? formNode.path_template : null,
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message ?? 'HivePanel could not detect local source storage.');
        }

        const detection = payload.detection ?? null;

        localDetections.value[node.source_node] = detection;

        if (detection?.detected && detection?.path_template) {
            formNode.path_template = detection.path_template;

            localPathOverrides.value[node.source_node] = false;
        }
    } catch (error: any) {
        localDetectionErrors.value[node.source_node] = error?.message ?? 'HivePanel could not detect local source storage.';
    } finally {
        localDetectionFor.value = null;
    }
}

function installLocalDetectionWatchers() {
    props.transferNodes.forEach((node) => {
        watch(
            () => transferForm.nodes[node.source_node]?.protocol,
            (protocol, previousProtocol) => {
                if (protocol === 'local' && previousProtocol !== 'local') {
                    detectLocalSource(node, true);
                }
            },
        );
    });
}

onMounted(() => {
    installLocalDetectionWatchers();

    props.transferNodes.forEach((node) => {
        if (transferForm.nodes[node.source_node]?.protocol === 'local') {
            detectLocalSource(node);
        }
    });
});

function saveTransferAccess() {
    transferForm.patch(`/admin/migrations/${props.migration.id}/transfer`, {
        preserveScroll: true,
    });
}

function saveDatabaseTransferAccess() {
    databaseTransferForm.patch(`/admin/migrations/${props.migration.id}/database-transfer`, {
        preserveScroll: true,
    });
}

function prepareMigration() {
    preparing.value = true;

    router.post(
        `/admin/migrations/${props.migration.id}/prepare`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                preparing.value = false;
            },
        },
    );
}

function actionLabel(action: string) {
    switch (action) {
        case 'preserve_existing':
            return 'Preserve';

        case 'create_exact':
            return 'Create Exact';

        case 'replace_private':
            return 'Replace Private';

        case 'replace_unavailable_ip':
            return 'Replace IP';

        case 'allocate_new':
            return 'Allocate New';

        case 'conflict':
            return 'Conflict';

        default:
            return action;
    }
}

function actionClass(action: string) {
    if (['preserve_existing', 'create_exact'].includes(action)) {
        return 'border-status-success/30 bg-status-success/10 text-status-success';
    }

    if (['replace_private', 'replace_unavailable_ip', 'allocate_new'].includes(action)) {
        return 'border-status-warning/30 bg-status-warning/10 text-status-warning';
    }

    return 'border-status-danger/30 bg-status-danger/10 text-status-danger';
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${migration.name} - Preflight`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full border border-hive/30 bg-hive/10 px-3 py-1 text-xs font-black text-hive">
                                        {{ t('ui.preflight') }}
                                    </span>

                                    <span class="text-xs font-bold text-muted-foreground">
                                        {{ t('ui.nothing_is_created_during_this_check') }}
                                    </span>
                                </div>

                                <h1 class="mt-3 text-2xl font-black sm:text-3xl">
                                    {{ t('ui.migration_preflight') }}
                                </h1>

                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{ t('ui.validate_destination_networking_resources_and_configure_direct_ac') }}
                                </p>
                            </div>

                            <Link
                                :href="`/admin/migrations/${migration.id}/review`"
                                class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back_to_review') }}
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('Servers') }}</div>
                            <div class="mt-1 text-2xl font-black">{{ summary.total }}</div>
                        </div>

                        <div class="rounded-panel border border-status-success/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.ready') }}</div>
                            <div class="mt-1 text-2xl font-black text-status-success">{{ summary.ready }}</div>
                        </div>

                        <div class="rounded-panel border border-status-warning/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.warnings_0e04cd1') }}</div>
                            <div class="mt-1 text-2xl font-black text-status-warning">{{ summary.warnings }}</div>
                        </div>

                        <div class="rounded-panel border bg-surface p-5" :class="summary.blocked > 0 ? 'border-status-danger/30' : 'border-border'">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.blocked') }}</div>
                            <div class="mt-1 text-2xl font-black" :class="summary.blocked > 0 ? 'text-status-danger' : 'text-status-success'">
                                {{ summary.blocked }}
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex items-center gap-2">
                            <KeyRound class="size-5 text-hive" />
                            <h2 class="text-lg font-black">{{ t('ui.server_file_transfer') }}</h2>
                        </div>

                        <p class="mt-1 max-w-4xl text-sm text-muted-foreground">
                            {{ t('ui.choose_how_hivepanel_should_read_each_source_node_use_in_place_wh') }}
                        </p>

                        <form class="mt-5 space-y-4" @submit.prevent="saveTransferAccess">
                            <div
                                v-for="node in transferNodes"
                                :key="node.source_node"
                                class="rounded-panel border border-border bg-surface-dark p-4 sm:p-5"
                            >
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <Server class="size-4 text-hive" />
                                        <span class="font-black text-foreground">{{ node.source_node }}</span>
                                    </div>

                                    <span
                                        class="rounded-full border px-2.5 py-1 text-xs font-black"
                                        :class="
                                            transferForm.nodes[node.source_node].protocol === 'local'
                                                ? localStatusClass(node)
                                                : node.has_password || node.has_private_key
                                                  ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                  : 'border-status-warning/30 bg-status-warning/10 text-status-warning'
                                        "
                                    >
                                        {{
                                            transferForm.nodes[node.source_node].protocol === 'local'
                                                ? localStatusLabel(node)
                                                : node.has_password || node.has_private_key
                                                  ? 'Node access saved'
                                                  : 'Node access required'
                                        }}
                                    </span>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                                    <div>
                                        <label class="text-xs font-black text-muted-foreground">{{ t('ui.transfer_method') }}</label>
                                        <select
                                            v-model="transferForm.nodes[node.source_node].protocol"
                                            class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                        >
                                            <option value="local">{{ t('ui.in_place_same_machine') }}</option>
                                            <option value="sftp">{{ t('ui.remote_node_sftp') }}</option>
                                        </select>
                                    </div>

                                    <div v-if="transferForm.nodes[node.source_node].protocol === 'sftp'">
                                        <label class="text-xs font-black text-muted-foreground">{{ t('ui.authentication') }}</label>
                                        <select
                                            v-model="transferForm.nodes[node.source_node].auth_type"
                                            class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                        >
                                            <option value="private_key">{{ t('ui.ssh_key') }}</option>
                                            <option value="password">{{ t('ui.password') }}</option>
                                        </select>
                                    </div>

                                    <div v-if="transferForm.nodes[node.source_node].protocol === 'sftp'" class="xl:col-span-2">
                                        <label class="text-xs font-black text-muted-foreground">{{ t('ui.host_4a82311') }}</label>
                                        <input
                                            v-model="transferForm.nodes[node.source_node].host"
                                            class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                            :placeholder="t('ui.source_node_example_com')"
                                        />
                                    </div>

                                    <div v-if="transferForm.nodes[node.source_node].protocol === 'sftp'">
                                        <label class="text-xs font-black text-muted-foreground">{{ t('ui.port') }}</label>
                                        <input
                                            v-model.number="transferForm.nodes[node.source_node].port"
                                            type="number"
                                            class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                        />
                                    </div>

                                    <div v-if="transferForm.nodes[node.source_node].protocol === 'sftp'">
                                        <label class="text-xs font-black text-muted-foreground">{{ t('ui.username') }}</label>
                                        <input
                                            v-model="transferForm.nodes[node.source_node].username"
                                            class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                        />
                                    </div>

                                    <div
                                        v-if="
                                            transferForm.nodes[node.source_node].protocol === 'sftp' &&
                                            transferForm.nodes[node.source_node].auth_type === 'password'
                                        "
                                    >
                                        <label class="text-xs font-black text-muted-foreground">{{ t('ui.password') }}</label>
                                        <input
                                            v-model="transferForm.nodes[node.source_node].password"
                                            type="password"
                                            autocomplete="off"
                                            class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                            :placeholder="node.has_password ? 'Leave blank to keep saved password' : 'Required'"
                                        />
                                    </div>

                                    <div v-else-if="transferForm.nodes[node.source_node].protocol === 'sftp'" class="md:col-span-2 xl:col-span-2">
                                        <label class="text-xs font-black text-muted-foreground">{{ t('ui.ssh_private_key') }}</label>

                                        <textarea
                                            v-model="transferForm.nodes[node.source_node].private_key"
                                            rows="3"
                                            class="mt-1 w-full resize-none rounded-button border border-border bg-black/30 px-3 py-2.5 font-mono text-xs font-bold text-foreground outline-none focus:border-hive"
                                            :placeholder="
                                                node.has_private_key
                                                    ? 'Generated key saved — leave blank to keep it'
                                                    : 'Paste an OpenSSH private key, or generate one below'
                                            "
                                        ></textarea>
                                    </div>

                                    <div
                                        v-if="
                                            transferForm.nodes[node.source_node].protocol === 'sftp' &&
                                            transferForm.nodes[node.source_node].auth_type === 'private_key'
                                        "
                                        class="rounded-button border border-hive/20 bg-hive/5 p-4 md:col-span-2 xl:col-span-6"
                                    >
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <Key class="size-4 text-hive" />
                                                    <span class="text-sm font-black text-foreground">
                                                        {{ t('ui.quick_source_node_setup') }}
                                                    </span>
                                                </div>

                                                <p class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground">
                                                    {{ t('ui.generate_a_dedicated_hivepanel_migration_key_then_copy_the_comman') }}
                                                    <code>hivepanel-migration</code
                                                    >{{ t('ui.installs_the_restricted_sftp_key_reads_wings_configured') }} <code>system.data</code>
                                                    {{ t('ui.path_and_grants_read_only_access_to_the_real_pterodactyl_volume_d') }}
                                                </p>
                                            </div>

                                            <button
                                                type="button"
                                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border border-hive bg-hive px-3 py-2 text-xs font-black text-black transition hover:bg-hive-light disabled:opacity-50"
                                                :disabled="generatingKeyFor !== null"
                                                @click="generateMigrationKey(node)"
                                            >
                                                <Key class="size-3.5" />
                                                {{
                                                    generatingKeyFor === node.source_node
                                                        ? 'Generating...'
                                                        : node.has_private_key
                                                          ? 'Regenerate Key'
                                                          : 'Generate Migration Key'
                                                }}
                                            </button>
                                        </div>

                                        <div v-if="node.setup_command" class="mt-4">
                                            <div class="mb-2 flex items-center justify-between gap-3">
                                                <span class="text-[10px] font-black uppercase tracking-wide text-muted-foreground">
                                                    {{ t('ui.run_on') }} {{ node.source_node }}
                                                </span>

                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1.5 text-xs font-black text-hive transition hover:text-hive-light"
                                                    @click="copySetupCommand(node)"
                                                >
                                                    <Clipboard class="size-3.5" />
                                                    {{ copiedCommandFor === node.source_node ? 'Copied' : 'Copy Command' }}
                                                </button>
                                            </div>

                                            <pre
                                                class="max-h-44 overflow-auto whitespace-pre-wrap break-all rounded-button border border-border bg-black/40 p-3 font-mono text-[11px] leading-5 text-foreground"
                                                >{{ node.setup_command }}</pre
                                            >

                                            <p class="mt-2 text-xs leading-5 text-muted-foreground">
                                                {{ t('ui.the_generated_private_key_remains_encrypted_inside_this_migration') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        v-if="transferForm.nodes[node.source_node].protocol === 'local'"
                                        class="rounded-button border border-status-warning/20 bg-status-warning/5 p-4 md:col-span-2 xl:col-span-6"
                                    >
                                        <div class="flex items-start gap-3">
                                            <HardDrive class="mt-0.5 size-5 shrink-0 text-status-warning" />

                                            <div class="min-w-0">
                                                <div class="text-sm font-black text-foreground">
                                                    {{ t('ui.in_place_migration') }}
                                                </div>

                                                <p class="mt-1 max-w-4xl text-xs leading-5 text-muted-foreground">
                                                    {{ t('ui.the_mapped_hivepanel_worker_must_be_installed_on_the_same_physica') }}
                                                </p>

                                                <div class="mt-4 space-y-3">
                                                    <label class="flex items-start gap-3 text-xs leading-5 text-foreground">
                                                        <input
                                                            v-model="transferForm.nodes[node.source_node].same_machine_confirmed"
                                                            type="checkbox"
                                                            class="mt-1 size-4 rounded border-border bg-black/30"
                                                        />

                                                        <span>
                                                            {{ t('ui.i_confirm_the_mapped_hivepanel_worker_is_installed_on_the_same_ma') }}
                                                        </span>
                                                    </label>

                                                    <label class="flex items-start gap-3 text-xs leading-5 text-foreground">
                                                        <input
                                                            v-model="transferForm.nodes[node.source_node].source_servers_stopped_confirmed"
                                                            type="checkbox"
                                                            class="mt-1 size-4 rounded border-border bg-black/30"
                                                        />

                                                        <span>
                                                            {{ t('ui.i_have_stopped_the_selected_source_servers_and_will_keep_them_sto') }}
                                                        </span>
                                                    </label>
                                                </div>

                                                <div
                                                    v-if="localDetection(node)?.detected"
                                                    class="mt-4 rounded-button border border-status-success/20 bg-status-success/5 p-4"
                                                >
                                                    <div class="text-xs font-black uppercase tracking-wide text-status-success">
                                                        {{ t('ui.local_source_detected') }}
                                                    </div>

                                                    <div class="mt-2 font-mono text-sm font-black text-foreground">
                                                        {{ localDetection(node).data_path }}
                                                    </div>

                                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                        <span>
                                                            {{ t('ui.detected_from') }}
                                                            <strong class="text-foreground">
                                                                {{ localDetection(node).source || 'Worker filesystem scan' }}
                                                            </strong>
                                                        </span>

                                                        <span
                                                            v-if="localDetection(node).enough_space !== false"
                                                            class="inline-flex items-center gap-1 rounded-full border border-status-success/20 bg-status-success/10 px-2 py-0.5 font-black text-status-success"
                                                        >
                                                            <CircleCheck class="size-3" />
                                                            {{ t('ui.enough_disk_space') }}
                                                        </span>
                                                    </div>

                                                    <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2 xl:grid-cols-4">
                                                        <div>
                                                            <div class="font-black text-muted-foreground">{{ t('ui.confidence') }}</div>
                                                            <div class="mt-1 font-bold text-foreground">
                                                                {{ localDetection(node).confidence || 'Unknown' }}
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <div class="font-black text-muted-foreground">{{ t('ui.volumes_found') }}</div>
                                                            <div class="mt-1 font-bold text-foreground">
                                                                {{ localDetection(node).matched_uuids?.length ?? 0 }}
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <div class="font-black text-muted-foreground">{{ t('ui.source_size') }}</div>
                                                            <div class="mt-1 font-bold text-foreground">
                                                                {{ Math.ceil((localDetection(node).total_bytes ?? 0) / 1024 / 1024) }}
                                                                {{ t('ui.mb') }}
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <div class="font-black text-muted-foreground">{{ t('ui.free_space') }}</div>
                                                            <div class="mt-1 font-bold text-foreground">
                                                                {{
                                                                    localDetection(node).free_bytes
                                                                        ? `${Math.floor(localDetection(node).free_bytes / 1024 / 1024 / 1024)} GB`
                                                                        : 'Unknown'
                                                                }}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mt-4 rounded-button border p-4" :class="runtimeStateClass(node)">
                                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                            <div>
                                                                <div class="text-[10px] font-black uppercase tracking-wide opacity-70">
                                                                    {{ t('ui.source_server_state') }}
                                                                </div>

                                                                <div class="mt-1 text-sm font-black">
                                                                    {{ runtimeStateLabel(node) }}
                                                                </div>
                                                            </div>

                                                            <button
                                                                type="button"
                                                                class="border-current/30 rounded-button border bg-black/10 px-3 py-2 text-xs font-black transition hover:bg-black/20"
                                                                @click="detectLocalSource(node, true)"
                                                            >
                                                                {{ t('ui.check_again') }}
                                                            </button>
                                                        </div>

                                                        <div
                                                            v-if="runtimeCheck(node)?.available && runtimeCheck(node)?.active_containers?.length"
                                                            class="mt-3 space-y-2"
                                                        >
                                                            <div
                                                                v-for="container in runtimeCheck(node).active_containers"
                                                                :key="`${container.uuid}:${container.id}`"
                                                                class="border-current/20 rounded-button border bg-black/10 p-3"
                                                            >
                                                                <div class="font-mono text-xs font-black">
                                                                    {{ container.uuid }}
                                                                </div>

                                                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[11px] opacity-80">
                                                                    <span>{{ container.name || container.id }}</span>
                                                                    <span>{{ container.state }}</span>
                                                                    <span v-if="container.status">{{ container.status }}</span>
                                                                    <span v-if="container.matched_by?.length">
                                                                        {{ t('ui.matched_by') }} {{ container.matched_by.join(', ') }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <p v-else-if="!runtimeCheck(node)" class="mt-2 text-xs leading-5 opacity-80">
                                                            {{ t('ui.the_worker_did_not_return_source_runtime_information_update_or_re') }}
                                                        </p>

                                                        <p v-else-if="!runtimeCheck(node)?.available" class="mt-2 text-xs leading-5 opacity-80">
                                                            {{ t('ui.hivepanel_could_not_verify_the_source_container_runtime_automatic')
                                                            }}{{ runtimeCheck(node)?.error ? `: ${runtimeCheck(node).error}` : '.' }}
                                                            {{ t('ui.the_stopped_server_confirmation_remains_required_as_a_manual_safe') }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        v-if="localDetection(node).warnings?.length"
                                                        class="mt-3 space-y-1 text-xs leading-5 text-status-warning"
                                                    >
                                                        <div v-for="warning in localDetection(node).warnings" :key="warning">
                                                            {{ warning }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="localDetectionFor === node.source_node"
                                                    class="mt-4 rounded-button border border-hive/20 bg-hive/5 p-4 text-xs font-bold text-hive"
                                                >
                                                    {{ t('ui.detecting_local_source_storage_on_the_mapped_hivepanel_worker') }}
                                                </div>

                                                <div
                                                    v-else-if="localDetectionErrors[node.source_node]"
                                                    class="mt-4 rounded-button border border-status-danger/20 bg-status-danger/5 p-4"
                                                >
                                                    <div class="text-xs font-black text-status-danger">
                                                        {{ t('ui.automatic_detection_failed') }}
                                                    </div>

                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                                        {{ localDetectionErrors[node.source_node] }}
                                                    </p>

                                                    <button
                                                        type="button"
                                                        class="mt-3 rounded-button border border-border bg-black/20 px-3 py-2 text-xs font-black text-foreground transition hover:border-hive hover:text-hive"
                                                        @click="detectLocalSource(node, true)"
                                                    >
                                                        {{ t('ui.retry_detection') }}
                                                    </button>
                                                </div>

                                                <div
                                                    v-else-if="!localDetection(node)?.detected"
                                                    class="mt-4 rounded-button border border-border bg-black/20 p-4"
                                                >
                                                    <div class="text-xs font-black text-foreground">
                                                        {{ t('ui.local_source_not_verified') }}
                                                    </div>

                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                                        {{ t('ui.hivepanel_can_ask_the_mapped_worker_to_inspect_daemon_configurati') }}
                                                    </p>

                                                    <button
                                                        type="button"
                                                        class="mt-3 rounded-button border border-hive/40 bg-hive/10 px-3 py-2 text-xs font-black text-hive transition hover:bg-hive/20"
                                                        @click="detectLocalSource(node, true)"
                                                    >
                                                        {{ t('ui.detect_local_source') }}
                                                    </button>
                                                </div>

                                                <div
                                                    class="mt-4 rounded-button border border-border bg-black/20 p-3 text-xs leading-5 text-muted-foreground"
                                                >
                                                    <strong class="text-foreground">{{ t('ui.file_strategy') }}</strong>
                                                    {{ t('ui.copy_source_files_and_keep_the_original_source_data_unchanged_thi') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2 xl:col-span-6">
                                        <label class="text-xs font-black text-muted-foreground">
                                            {{
                                                transferForm.nodes[node.source_node].protocol === 'local'
                                                    ? 'Local Source Data Path'
                                                    : 'Source Volume Path Template'
                                            }}
                                        </label>
                                        <input
                                            v-model="transferForm.nodes[node.source_node].path_template"
                                            class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 font-mono text-sm font-bold text-foreground outline-none focus:border-hive"
                                            placeholder="/path/to/source/volumes/{uuid}"
                                            @input="markLocalPathOverride(node)"
                                        />

                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <p class="text-xs text-muted-foreground">
                                                {{
                                                    transferForm.nodes[node.source_node].protocol === 'local'
                                                        ? 'HivePanel detects the source data root automatically. Editing this field adds a manual candidate, but stronger daemon/filesystem evidence can still win. Use {uuid} for the source server UUID.'
                                                        : 'Path to server data on the underlying source node. Use {uuid} where the source server UUID should be inserted.'
                                                }}
                                            </p>

                                            <span
                                                v-if="node.path_detected && transferForm.nodes[node.source_node].protocol === 'sftp'"
                                                class="inline-flex items-center gap-1 rounded-full border border-status-success/30 bg-status-success/10 px-2 py-0.5 text-[10px] font-black text-status-success"
                                            >
                                                <CircleCheck class="size-3" />
                                                {{ t('ui.detected_from_wings_config') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="transferForm.errors.transfer"
                                class="rounded-button border border-status-danger/30 bg-status-danger/10 p-3 text-xs font-bold text-status-danger"
                            >
                                {{ transferForm.errors.transfer }}
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2.5 text-sm font-black text-black transition hover:bg-hive-light disabled:opacity-50"
                                :disabled="transferForm.processing"
                            >
                                <Save class="size-4" />
                                {{ transferForm.processing ? 'Saving...' : 'Save Transfer Configuration' }}
                            </button>
                        </form>
                    </section>

                    <section v-if="selectedDatabaseCount > 0" class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex items-center gap-2">
                            <Database class="size-5 text-hive" />
                            <h2 class="text-lg font-black">{{ t('ui.database_transfer_hosts') }}</h2>
                        </div>

                        <p class="mt-1 max-w-4xl text-sm leading-6 text-muted-foreground">
                            {{ selectedDatabaseCount }} {{ t('ui.selected_server_database') }}{{ selectedDatabaseCount === 1 ? '' : 's' }}
                            {{ t('ui.will_be_migrated_configure_credentials_for_each_source_database_h') }}
                        </p>

                        <form class="mt-5 space-y-4" @submit.prevent="saveDatabaseTransferAccess">
                            <div
                                v-for="host in databaseTransferHosts"
                                :key="host.key"
                                class="rounded-panel border border-border bg-surface-dark p-4 sm:p-5"
                            >
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <div class="font-black text-foreground">
                                            {{ host.name || host.host }}
                                        </div>

                                        <div class="mt-1 font-mono text-xs text-muted-foreground">{{ host.host }}:{{ host.port }}</div>
                                    </div>

                                    <span
                                        class="rounded-full border px-2.5 py-1 text-xs font-black"
                                        :class="
                                            host.verified
                                                ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                : 'border-status-warning/30 bg-status-warning/10 text-status-warning'
                                        "
                                    >
                                        {{ host.verified ? 'Verified' : 'Needs Configuration' }}
                                    </span>
                                </div>

                                <div class="grid gap-4 xl:grid-cols-2">
                                    <div class="rounded-button border border-border bg-black/20 p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.source_database_host') }}
                                        </div>

                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="text-xs font-black text-muted-foreground">{{ t('ui.username') }}</label>
                                                <input
                                                    v-model="databaseTransferForm.hosts[host.key].source_username"
                                                    class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-xs font-black text-muted-foreground">{{ t('ui.password') }}</label>
                                                <input
                                                    v-model="databaseTransferForm.hosts[host.key].source_password"
                                                    type="password"
                                                    autocomplete="off"
                                                    class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                    :placeholder="host.source_has_password ? 'Leave blank to keep saved password' : 'Required'"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-border bg-black/20 p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.hivepanel_destination') }}
                                        </div>

                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="text-xs font-black text-muted-foreground">{{ t('ui.host_4a82311') }}</label>
                                                <input
                                                    v-model="databaseTransferForm.hosts[host.key].destination_host"
                                                    class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-xs font-black text-muted-foreground">{{ t('ui.port') }}</label>
                                                <input
                                                    v-model.number="databaseTransferForm.hosts[host.key].destination_port"
                                                    type="number"
                                                    class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-xs font-black text-muted-foreground">{{ t('ui.username') }}</label>
                                                <input
                                                    v-model="databaseTransferForm.hosts[host.key].destination_username"
                                                    class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-xs font-black text-muted-foreground">{{ t('ui.password') }}</label>
                                                <input
                                                    v-model="databaseTransferForm.hosts[host.key].destination_password"
                                                    type="password"
                                                    autocomplete="off"
                                                    class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                    :placeholder="host.destination_has_password ? 'Leave blank to keep saved password' : 'Required'"
                                                />
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label class="text-xs font-black text-muted-foreground">{{ t('ui.database_prefix') }}</label>
                                                <input
                                                    v-model="databaseTransferForm.hosts[host.key].destination_prefix"
                                                    class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 font-mono text-sm font-bold text-foreground outline-none focus:border-hive"
                                                    :placeholder="t('ui.hive')"
                                                />

                                                <p class="mt-1 text-xs text-muted-foreground">
                                                    {{ t('ui.destination_databases_will_keep_their_source_name_with_this_prefi') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="databaseTransferForm.errors.database_transfer"
                                class="rounded-button border border-status-danger/30 bg-status-danger/10 p-3 text-xs font-bold text-status-danger"
                            >
                                {{ databaseTransferForm.errors.database_transfer }}
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2.5 text-sm font-black text-black transition hover:bg-hive-light disabled:opacity-50"
                                :disabled="databaseTransferForm.processing"
                            >
                                <Save class="size-4" />
                                {{ databaseTransferForm.processing ? 'Testing...' : 'Test & Save Database Hosts' }}
                            </button>
                        </form>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div class="border-b border-border p-5 sm:p-6">
                            <h2 class="text-lg font-black">{{ t('ui.server_plans') }}</h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.private_internal_pterodactyl_allocations_are_automatically_replac') }}
                            </p>
                        </div>

                        <div class="divide-y divide-border">
                            <div v-for="server in servers" :key="server.id" class="p-5 sm:p-6">
                                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-black text-foreground">{{ server.name }}</h3>

                                            <span
                                                class="rounded-full border px-2.5 py-1 text-xs font-black"
                                                :class="
                                                    server.execution_plan?.blocked
                                                        ? 'border-status-danger/30 bg-status-danger/10 text-status-danger'
                                                        : (server.execution_plan?.warnings?.length ?? 0) > 0
                                                          ? 'border-status-warning/30 bg-status-warning/10 text-status-warning'
                                                          : 'border-status-success/30 bg-status-success/10 text-status-success'
                                                "
                                            >
                                                {{
                                                    server.execution_plan?.blocked
                                                        ? 'Blocked'
                                                        : (server.execution_plan?.warnings?.length ?? 0) > 0
                                                          ? 'Ready with warnings'
                                                          : 'Ready'
                                                }}
                                            </span>
                                        </div>

                                        <div class="mt-1 text-xs text-muted-foreground">
                                            {{ server.destination_node?.name }} · {{ server.destination_owner?.email }}
                                        </div>
                                    </div>

                                    <div class="grid gap-2 sm:grid-cols-2 xl:min-w-[520px]">
                                        <div
                                            v-for="allocation in server.execution_plan?.allocations ?? []"
                                            :key="`${allocation.source?.ip}:${allocation.source?.port}`"
                                            class="rounded-button border border-border bg-surface-dark p-3"
                                        >
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="font-mono text-xs font-black text-foreground">
                                                    {{ allocation.source?.ip }}:{{ allocation.source?.port }}
                                                </div>

                                                <span
                                                    class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wide"
                                                    :class="actionClass(allocation.action)"
                                                >
                                                    {{ actionLabel(allocation.action) }}
                                                </span>
                                            </div>

                                            <div v-if="allocation.destination" class="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                                                <Network class="size-3" />
                                                → {{ allocation.destination.ip }}:{{ allocation.destination.port }}
                                            </div>

                                            <p v-if="allocation.message" class="mt-2 text-xs leading-5 text-muted-foreground">
                                                {{ allocation.message }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="server.execution_plan?.errors?.length"
                                    class="mt-4 rounded-button border border-status-danger/30 bg-status-danger/10 p-3"
                                >
                                    <div
                                        v-for="error in server.execution_plan.errors"
                                        :key="error"
                                        class="flex items-start gap-2 text-xs font-bold text-status-danger"
                                    >
                                        <CircleAlert class="mt-0.5 size-3.5 shrink-0" />
                                        {{ error }}
                                    </div>
                                </div>

                                <div
                                    v-if="server.execution_plan?.warnings?.length"
                                    class="mt-4 rounded-button border border-status-warning/30 bg-status-warning/10 p-3"
                                >
                                    <div
                                        v-for="warning in server.execution_plan.warnings"
                                        :key="warning"
                                        class="flex items-start gap-2 text-xs font-bold text-status-warning"
                                    >
                                        <TriangleAlert class="mt-0.5 size-3.5 shrink-0" />
                                        {{ warning }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-panel border p-5 sm:p-6"
                        :class="startReady ? 'border-status-success/30 bg-status-success/5' : 'border-status-warning/30 bg-status-warning/5'"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <CircleCheck v-if="startReady" class="mt-0.5 size-5 shrink-0 text-status-success" />

                                <TriangleAlert v-else class="mt-0.5 size-5 shrink-0 text-status-warning" />

                                <div>
                                    <h2 class="font-black text-foreground">
                                        {{ startReady ? 'Ready to prepare execution' : 'Execution is not ready yet' }}
                                    </h2>

                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                        <template v-if="summary.blocked > 0">
                                            {{ t('ui.resolve') }} {{ summary.blocked }} {{ t('ui.blocked_server_plan')
                                            }}{{ summary.blocked === 1 ? '' : 's' }} {{ t('ui.before_migration_can_be_prepared') }}
                                        </template>

                                        <template v-else-if="!transferComplete">
                                            {{ t('ui.configure_and_verify_file_access_for_every_source_pterodactyl_nod') }}
                                        </template>

                                        <template v-else-if="!databaseTransferComplete">
                                            {{ t('ui.test_and_save_database_transfer_hosts_for_the_selected_server_dat') }}
                                        </template>

                                        <template v-else>
                                            {{ t('ui.preparation_will_create_any_missing_users_combs_and_reserve_the_p') }}
                                        </template>
                                    </p>
                                </div>
                            </div>

                            <button
                                v-if="startReady"
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center rounded-button border border-status-success bg-status-success px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="preparing"
                                @click="prepareMigration"
                            >
                                {{ preparing ? 'Preparing...' : 'Prepare Migration' }}
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="$page.props.errors?.preparation"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger"
                    >
                        {{ $page.props.errors.preparation }}
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
