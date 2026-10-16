<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Server } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const form = useForm({
    name: '',
    description: '',
    location: '',

    public_fqdn: '',
    internal_fqdn: '',

    scheme: 'http',
    port: 8080,
    daemon_port: 8080,
    sftp_port: 2022,

    behind_proxy: false,
    maintenance_mode: false,
    is_active: true,

    cpu_threads: null as number | null,
    memory_mib: null as number | null,
    memory_overallocate: 0,
    disk_mib: null as number | null,
    disk_overallocate: 0,
    max_upload_mib: 100,
});

const connectionMode = computed({
    get: () => {
        if (form.scheme === 'https' && form.behind_proxy) return 'proxy';
        if (form.scheme === 'https') return 'native';
        return 'none';
    },
    set: (mode: string) => {
        if (mode === 'proxy') {
            form.scheme = 'https';
            form.behind_proxy = true;
            if (Number(form.port) === Number(form.daemon_port)) form.port = 443;
            return;
        }

        if (mode === 'native') {
            form.scheme = 'https';
            form.behind_proxy = false;
            if (Number(form.port) === 443) form.port = Number(form.daemon_port);
            return;
        }

        form.scheme = 'http';
        form.behind_proxy = false;
        if (Number(form.port) === 443) form.port = Number(form.daemon_port);
    },
});

watch(
    () => form.daemon_port,
    (value, previous) => {
        if (connectionMode.value !== 'proxy' && Number(form.port) === Number(previous)) {
            form.port = Number(value);
        }
    },
);

function submit() {
    form.post('/admin/nodes');
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="t('ui.create_node')" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <Server class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        {{ t('ui.new_node') }}
                                    </h1>

                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.add_a_worker_host_registration_and_api_token_setup_happens_on_the') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/nodes"
                                class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back') }}
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black text-foreground">
                                {{ t('ui.node_details') }}
                            </h2>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.name') }}
                                    </label>

                                    <input
                                        v-model="form.name"
                                        type="text"
                                        :placeholder="t('ui.eu01')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.location') }}
                                    </label>

                                    <input
                                        v-model="form.location"
                                        type="text"
                                        :placeholder="t('ui.london_uk')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.location" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.description') }}
                                    </label>

                                    <textarea
                                        v-model="form.description"
                                        rows="3"
                                        :placeholder="t('ui.optional_notes_about_this_worker_node')"
                                        class="mt-2 w-full resize-none rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.description" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black text-foreground">
                                {{ t('Network') }}
                            </h2>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.public_fqdn_ip') }}
                                    </label>

                                    <input
                                        v-model="form.public_fqdn"
                                        type="text"
                                        :placeholder="t('ui.worker_example_com')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.public_fqdn" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.internal_fqdn_ip') }}
                                    </label>

                                    <input
                                        v-model="form.internal_fqdn"
                                        type="text"
                                        placeholder="10.0.0.12"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.internal_fqdn" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.connection_ssl_mode') }}
                                    </label>

                                    <select
                                        v-model="connectionMode"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    >
                                        <option value="none">{{ t('ui.no_ssl_http_ws') }}</option>
                                        <option value="native">{{ t('ui.native_ssl_hiveworker_https_wss') }}</option>
                                        <option value="proxy">{{ t('ui.proxy_ssl_reverse_proxy_https_wss') }}</option>
                                    </select>

                                    <p class="mt-2 text-xs text-muted-foreground">
                                        <template v-if="connectionMode === 'native'">
                                            {{ t('ui.hiveworker_terminates_tls_directly_using_etc_hivepanel_ssl_fullch') }}
                                        </template>
                                        <template v-else-if="connectionMode === 'proxy'">
                                            {{ t('ui.your_reverse_proxy_terminates_tls_hiveworker_itself_remains_http_') }}
                                        </template>
                                        <template v-else>
                                            {{ t('ui.hiveworker_uses_plain_http_ws_browsers_cannot_use_this_mode_for_c') }}
                                        </template>
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.public_port_cb65c3e') }}
                                    </label>

                                    <input
                                        v-model.number="form.port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <p class="mt-2 text-xs text-muted-foreground">
                                        {{ t('ui.externally_reachable_port_used_by_hivepanel_and_browser_websocket') }}
                                    </p>

                                    <InputError class="mt-2" :message="form.errors.port" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.worker_port') }}
                                    </label>

                                    <input
                                        v-model.number="form.daemon_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <p class="mt-2 text-xs text-muted-foreground">
                                        {{ t('ui.port_hiveworker_listens_on_locally') }}
                                    </p>

                                    <InputError class="mt-2" :message="form.errors.daemon_port" />
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
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.sftp_port" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black text-foreground">
                                {{ t('ui.resource_limits') }}
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.leave_cpu_memory_or_disk_empty_to_use_live_worker_reported_capaci') }}
                            </p>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.cpu_threads') }}
                                    </label>

                                    <input
                                        v-model="form.cpu_threads"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :placeholder="t('ui.auto')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.cpu_threads" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.memory_mib') }}
                                    </label>

                                    <input
                                        v-model="form.memory_mib"
                                        type="number"
                                        min="0"
                                        :placeholder="t('ui.auto')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.memory_mib" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.memory_overallocation') }}
                                    </label>

                                    <input
                                        v-model="form.memory_overallocate"
                                        type="number"
                                        min="0"
                                        max="1000"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.memory_overallocate" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.disk_mib') }}
                                    </label>

                                    <input
                                        v-model="form.disk_mib"
                                        type="number"
                                        min="0"
                                        :placeholder="t('ui.auto')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.disk_mib" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.disk_overallocation') }}
                                    </label>

                                    <input
                                        v-model="form.disk_overallocate"
                                        type="number"
                                        min="0"
                                        max="1000"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.disk_overallocate" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.max_upload_mib') }}
                                    </label>

                                    <input
                                        v-model="form.max_upload_mib"
                                        type="number"
                                        min="1"
                                        max="1024"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.max_upload_mib" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black text-foreground">
                                {{ t('ui.options') }}
                            </h2>

                            <div class="mt-5 grid gap-3 md:grid-cols-3">
                                <label class="flex cursor-pointer items-center gap-3 rounded-button border border-border bg-surface-dark p-4">
                                    <input
                                        v-model="form.behind_proxy"
                                        type="checkbox"
                                        class="size-4 rounded border-border bg-surface-dark text-hive focus:ring-hive"
                                    />
                                    <span class="text-sm font-bold text-foreground">{{ t('ui.behind_proxy') }}</span>
                                </label>

                                <label class="flex cursor-pointer items-center gap-3 rounded-button border border-border bg-surface-dark p-4">
                                    <input
                                        v-model="form.maintenance_mode"
                                        type="checkbox"
                                        class="size-4 rounded border-border bg-surface-dark text-hive focus:ring-hive"
                                    />
                                    <span class="text-sm font-bold text-foreground">{{ t('ui.maintenance') }}</span>
                                </label>

                                <label class="flex cursor-pointer items-center gap-3 rounded-button border border-border bg-surface-dark p-4">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="size-4 rounded border-border bg-surface-dark text-hive focus:ring-hive"
                                    />
                                    <span class="text-sm font-bold text-foreground">{{ t('ui.active_9234069') }}</span>
                                </label>
                            </div>
                        </section>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save class="size-4" />
                                {{ form.processing ? 'Creating...' : 'Create Node' }}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
