<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Package, Power, Trash2, Settings } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Extension = {
    id: string;
    name: string;
    version: string;
    enabled: boolean;
    networkApproved?: boolean;
    manifest: { permissions?: string[]; network?: { http?: { hosts: string[] }; tcp?: { scope: string } }; settings?: Array<{ key: string }>; frontend?: { slots?: string[] }; panelCompatibility?: string; description?: string; author?: string };
};
const props = defineProps<{ extension: Extension; networkAudits?: Array<{ id: number; operation: string; destination: string | null; outcome: string; created_at: string }> }>();
const page = usePage();
const actionError = computed(() => page.props.errors.enabled || page.props.errors.extension);
const needsNetworkApproval = computed(() => !props.extension.networkApproved && props.extension.manifest.permissions?.some(permission => permission.startsWith('network.')));
const busy = ref(false);
const approveNetwork = ref(false);
const changeState = () => {
    busy.value = true;
    router.patch(
        `/admin/extensions/${props.extension.id}`,
        { enabled: !props.extension.enabled, approve_network: approveNetwork.value },
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
};
const remove = () => {
    if (!window.confirm(`Uninstall ${props.extension.name}? This deletes its installed files.`)) return;
    busy.value = true;
    router.delete(`/admin/extensions/${props.extension.id}`, { onFinish: () => (busy.value = false) });
};
</script>
<template>
    <AppLayout context="admin"
        ><Head :title="extension.name" />
        <div class="min-h-screen bg-surface-dark px-4 py-5 text-white sm:px-6 sm:py-7 lg:px-8">
            <div class="mx-auto space-y-5">
                <Link href="/admin/extensions" class="inline-flex items-center gap-2 text-sm font-bold text-zinc-400 hover:text-hive"
                    ><ArrowLeft class="size-4" /> Back to Extensions</Link
                >
                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <Package class="size-6 text-hive" />
                            <div>
                                <h1 class="text-2xl font-black">{{ extension.name }}</h1>
                                <p class="mt-2 font-mono text-xs text-zinc-500">{{ extension.id }} · v{{ extension.version }}</p>
                            </div>
                        </div>
                        <span
                            class="rounded-full border px-3 py-1 text-xs font-bold"
                            :class="
                                extension.enabled
                                    ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                    : 'border-zinc-700 bg-zinc-800 text-zinc-400'
                            "
                            >{{ extension.enabled ? 'Enabled' : 'Disabled' }}</span
                        >
                    </div>
                </section>
                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                    <h2 class="text-lg font-black">Details</h2>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-zinc-500">Version</dt>
                            <dd class="mt-1 font-bold">{{ extension.version }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Panel compatibility</dt>
                            <dd class="mt-1 font-bold">{{ extension.manifest.panelCompatibility || 'Unknown' }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Author</dt>
                            <dd class="mt-1 font-bold">{{ extension.manifest.author || 'Not specified' }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">UI slots</dt>
                            <dd class="mt-1 font-bold">{{ extension.manifest.frontend?.slots?.join(', ') || 'None' }}</dd>
                        </div>
                    </dl>
                    <p v-if="extension.manifest.description" class="mt-4 text-sm text-zinc-400">{{ extension.manifest.description }}</p>
                </section>
                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                    <h2 class="text-lg font-black">Permissions</h2>
                    <p class="mt-3 text-sm text-zinc-400">{{ extension.manifest.permissions?.join(', ') || 'No capabilities' }}</p>
                    <p v-if="extension.manifest.network?.http" class="mt-2 text-sm text-zinc-400">HTTPS hosts: {{ extension.manifest.network.http.hosts.join(', ') }}</p>
                    <p v-if="extension.manifest.network?.tcp" class="mt-2 text-sm text-zinc-400">TCP scope: {{ extension.manifest.network.tcp.scope }}. Requires access to the Cell and console command permission.</p>
                    <label v-if="needsNetworkApproval" class="my-4 flex items-start gap-3 text-sm text-zinc-300">
                        <input v-model="approveNetwork" type="checkbox" class="mt-1" /> Approve these declared network permissions when enabling this extension.
                    </label>
                    <p v-if="needsNetworkApproval" class="mt-3 text-sm text-amber-400">Network approval is required. Review the hosts and TCP scope above, tick the approval checkbox, then select Approve and enable.</p>
                    <p v-else-if="extension.networkApproved" class="mt-3 text-sm text-status-success">Declared network permissions approved.</p>
                    <h2 class="mt-6 text-lg font-black">Manage</h2>
                    <div v-if="actionError" role="alert" class="mt-4 rounded-button border border-status-danger/30 bg-status-danger/10 p-3 text-sm font-bold text-status-danger">{{ actionError }}</div>
                    <p class="mt-2 text-sm text-zinc-500">Enabled extensions can appear in the panel's configured Nectar slots.</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <Link v-if="extension.manifest.settings?.length" :href="`/admin/extensions/${extension.id}/settings`"
                            class="inline-flex items-center gap-2 rounded-button border border-zinc-700 px-4 py-2 text-sm font-black text-white hover:bg-zinc-800">
                            <Settings class="size-4" /> Configure
                        </Link>
                        <button
                            :disabled="busy"
                            class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black disabled:opacity-50"
                            @click="changeState"
                        >
                            <Power class="size-4" /> {{ extension.enabled ? 'Disable' : needsNetworkApproval ? 'Approve and enable' : 'Enable' }}</button
                        ><button
                            :disabled="busy"
                            class="inline-flex items-center gap-2 rounded-button border border-red-900 px-4 py-2 text-sm font-black text-red-400 hover:bg-red-950/30 disabled:opacity-50"
                            @click="remove"
                        >
                            <Trash2 class="size-4" /> Uninstall
                        </button>
                    </div>
                </section>
                <section v-if="networkAudits?.length" class="rounded-panel border border-zinc-800 bg-surface p-5">
                    <h2 class="text-lg font-black">Recent network requests</h2>
                    <div v-for="entry in networkAudits" :key="entry.id" class="mt-3 border-t border-zinc-800 pt-3 text-sm text-zinc-400">
                        {{ entry.created_at }} · {{ entry.operation }} · {{ entry.destination || 'No destination' }} · {{ entry.outcome }}
                    </div>
                </section>
            </div>
        </div></AppLayout
    >
</template>
