<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CpuIcon, Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import NodeNavigation from './NodeNavigation.vue';

const props = defineProps<{
    node: any;
}>();

const saving = ref(false);

const form = ref({
    name: props.node.name ?? '',
    description: props.node.description ?? '',
    location: props.node.location ?? '',

    public_fqdn: props.node.public_fqdn ?? props.node.fqdn ?? '',
    internal_fqdn: props.node.internal_fqdn ?? '',

    scheme: props.node.scheme ?? 'http',
    port: props.node.port ?? props.node.daemon_port ?? 8080,
    daemon_port: props.node.daemon_port ?? props.node.port ?? 8080,
    sftp_port: props.node.sftp_port ?? 2022,
    sftp_enabled: props.node.sftp_enabled ?? true,
    sftp_fqdn: props.node.sftp_fqdn ?? '',

    behind_proxy: props.node.behind_proxy ?? false,
    maintenance_mode: props.node.maintenance_mode ?? false,
    is_active: props.node.is_active ?? true,

    cpu_threads: props.node.cpu_threads ?? '',
    memory_mib: props.node.memory_mib ?? '',
    memory_overallocate: props.node.memory_overallocate ?? 0,
    disk_mib: props.node.disk_mib ?? '',
    disk_overallocate: props.node.disk_overallocate ?? 0,
    max_upload_mib: props.node.max_upload_mib ?? 100,
});

const connectionMode = computed({
    get: () => {
        if (form.value.scheme === 'https' && form.value.behind_proxy) return 'proxy';
        if (form.value.scheme === 'https') return 'native';
        return 'none';
    },
    set: (mode: string) => {
        if (mode === 'proxy') {
            form.value.scheme = 'https';
            form.value.behind_proxy = true;
            if (Number(form.value.port) === Number(form.value.daemon_port)) form.value.port = 443;
            return;
        }

        if (mode === 'native') {
            form.value.scheme = 'https';
            form.value.behind_proxy = false;
            if (Number(form.value.port) === 443) form.value.port = Number(form.value.daemon_port);
            return;
        }

        form.value.scheme = 'http';
        form.value.behind_proxy = false;
        if (Number(form.value.port) === 443) form.value.port = Number(form.value.daemon_port);
    },
});

watch(
    () => form.value.daemon_port,
    (value, previous) => {
        if (connectionMode.value !== 'proxy' && Number(form.value.port) === Number(previous)) {
            form.value.port = Number(value);
        }
    },
);

function save() {
    saving.value = true;

    router.patch(`/admin/nodes/${props.node.id}/settings`, form.value, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${node.name} Settings`" />

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

                                        <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
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

                    <NodeNavigation :node-id="node.id" active="settings" />

                    <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
                        <div class="space-y-5">
                            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">{{ t('ui.connection') }}</h2>

                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.name') }}</span>
                                        <input
                                            v-model="form.name"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.location') }}</span>
                                        <input
                                            v-model="form.location"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>

                                    <label class="space-y-2 md:col-span-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.description')
                                        }}</span>
                                        <textarea
                                            v-model="form.description"
                                            rows="3"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.public_fqdn_ip')
                                        }}</span>
                                        <input
                                            v-model="form.public_fqdn"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.internal_fqdn_ip')
                                        }}</span>
                                        <input
                                            v-model="form.internal_fqdn"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                            :placeholder="t('ui.leave_blank_to_use_public_fqdn')"
                                        />
                                    </label>

                                    <label class="space-y-2 md:col-span-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.connection_ssl_mode')
                                        }}</span>
                                        <select
                                            v-model="connectionMode"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        >
                                            <option value="none">{{ t('ui.no_ssl_http_ws') }}</option>
                                            <option value="native">{{ t('ui.native_ssl_hiveworker_https_wss') }}</option>
                                            <option value="proxy">{{ t('ui.proxy_ssl_reverse_proxy_https_wss') }}</option>
                                        </select>
                                        <span class="block text-xs text-muted-foreground">
                                            <template v-if="connectionMode === 'native'">
                                                {{ t('ui.hiveworker_terminates_tls_using_etc_hivepanel_ssl_fullchain_pem_a') }}
                                            </template>
                                            <template v-else-if="connectionMode === 'proxy'">
                                                {{ t('ui.the_reverse_proxy_terminates_tls_while_hiveworker_remains_http_in') }}
                                            </template>
                                            <template v-else>
                                                {{ t('ui.plain_http_ws_this_cannot_provide_browser_console_websockets_from') }}
                                            </template>
                                        </span>
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.public_port_cb65c3e')
                                        }}</span>
                                        <input
                                            v-model.number="form.port"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                        <span class="block text-xs text-muted-foreground">{{
                                            t('ui.externally_reachable_worker_websocket_port')
                                        }}</span>
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.worker_port')
                                        }}</span>
                                        <input
                                            v-model.number="form.daemon_port"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                        <span class="block text-xs text-muted-foreground">{{ t('ui.port_hiveworker_listens_on_locally') }}</span>
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.max_upload_mib')
                                        }}</span>
                                        <input
                                            v-model="form.max_upload_mib"
                                            type="number"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>
                                </div>
                            </section>

                            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <div>
                                    <h2 class="text-lg font-black text-foreground">
                                        {{ t('ui.sftp') }}
                                    </h2>

                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ t('ui.configure_the_public_sftp_endpoint_users_will_connect_to') }}
                                    </p>
                                </div>

                                <div class="mt-5 space-y-5">
                                    <label
                                        class="flex cursor-pointer items-center justify-between rounded-button border border-border bg-surface-dark p-4"
                                    >
                                        <div>
                                            <div class="font-black text-foreground">
                                                {{ t('ui.enable_sftp') }}
                                            </div>
                                            <div class="text-sm text-muted-foreground">
                                                {{ t('ui.allow_users_to_connect_to_this_node_using_sftp') }}
                                            </div>
                                        </div>

                                        <input v-model="form.sftp_enabled" type="checkbox" class="accent-hive" />
                                    </label>

                                    <div class="grid gap-5 lg:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.sftp_fqdn') }}
                                            </label>

                                            <input
                                                v-model="form.sftp_fqdn"
                                                type="text"
                                                class="mt-2 w-full rounded-button border border-border bg-surface-dark px-3 py-2 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                                :placeholder="t('ui.sftp_node01_example_com')"
                                            />

                                            <p class="mt-2 text-xs text-muted-foreground">
                                                {{ t('ui.leave_blank_to_use_the_node_fqdn') }}
                                            </p>
                                        </div>

                                        <div>
                                            <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.sftp_port') }}
                                            </label>

                                            <input
                                                v-model="form.sftp_port"
                                                type="number"
                                                min="1"
                                                max="65535"
                                                class="mt-2 w-full rounded-button border border-border bg-surface-dark px-3 py-2 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                                placeholder="2022"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">{{ t('ui.allocation_limits') }}</h2>

                                <div class="mt-5 grid gap-4 md:grid-cols-3">
                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.cpu_threads')
                                        }}</span>
                                        <input
                                            v-model="form.cpu_threads"
                                            type="number"
                                            step="0.01"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.memory_mib') }}</span>
                                        <input
                                            v-model="form.memory_mib"
                                            type="number"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.memory_overallocate')
                                        }}</span>
                                        <input
                                            v-model="form.memory_overallocate"
                                            type="number"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.disk_mib') }}</span>
                                        <input
                                            v-model="form.disk_mib"
                                            type="number"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.disk_overallocate')
                                        }}</span>
                                        <input
                                            v-model="form.disk_overallocate"
                                            type="number"
                                            class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                        />
                                    </label>
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-5">
                            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">{{ t('ui.node_state') }}</h2>

                                <label
                                    class="mt-5 flex items-center justify-between gap-4 rounded-button border border-border bg-surface-light px-4 py-3"
                                >
                                    <span>
                                        <span class="block text-sm font-bold text-foreground">{{ t('ui.active_9234069') }}</span>
                                        <span class="text-xs text-muted-foreground">{{ t('ui.allow_new_cells_to_be_created_on_this_node') }}</span>
                                    </span>
                                    <input v-model="form.is_active" type="checkbox" class="accent-hive" />
                                </label>

                                <label
                                    class="mt-3 flex items-center justify-between gap-4 rounded-button border border-border bg-surface-light px-4 py-3"
                                >
                                    <span>
                                        <span class="block text-sm font-bold text-foreground">{{ t('ui.maintenance_mode') }}</span>
                                        <span class="text-xs text-muted-foreground">{{ t('ui.prevent_users_accessing_servers_on_this_node') }}</span>
                                    </span>
                                    <input v-model="form.maintenance_mode" type="checkbox" class="accent-hive" />
                                </label>

                                <label
                                    class="mt-3 flex items-center justify-between gap-4 rounded-button border border-border bg-surface-light px-4 py-3"
                                >
                                    <span>
                                        <span class="block text-sm font-bold text-foreground">{{ t('ui.behind_proxy_1086318') }}</span>
                                        <span class="text-xs text-muted-foreground">{{
                                            t('ui.use_when_the_worker_sits_behind_cloudflare_or_another_proxy')
                                        }}</span>
                                    </span>
                                    <input v-model="form.behind_proxy" type="checkbox" class="accent-hive" />
                                </label>
                            </section>

                            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">{{ t('ui.save_changes') }}</h2>

                                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                    {{ t('ui.changing_the_node_url_affects_how_the_panel_communicates_with_the') }}
                                </p>

                                <button
                                    class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-3 text-sm font-black text-primary-foreground transition hover:bg-hive-light disabled:opacity-50"
                                    :disabled="saving"
                                    @click="save"
                                >
                                    <Save class="size-4" />
                                    {{ saving ? 'Saving...' : 'Save Node Settings' }}
                                </button>
                            </section>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
