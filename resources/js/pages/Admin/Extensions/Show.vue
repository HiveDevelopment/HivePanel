<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Package, Power, Settings, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Extension = {
    id: string;
    name: string;
    version: string;
    enabled: boolean;
    networkApproved?: boolean;
    navigationOverrides?: Record<string, { group: string }>;
    manifest: {
        permissions?: string[];
        network?: { http?: { hosts: string[] }; tcp?: { scope: string } };
        settings?: Array<{ key: string }>;
        frontend?: { slots?: string[]; pages?: Array<{ slug: string; title: string; sidebar?: { group?: string } }> };
        panelCompatibility?: string;
        description?: string;
        author?: string;
    };
};
const props = defineProps<{
    extension: Extension;
    networkAudits?: Array<{ id: number; operation: string; destination: string | null; outcome: string; created_at: string }>;
}>();
const page = usePage();
const actionError = computed(() => page.props.errors.enabled || page.props.errors.extension || page.props.errors.navigation_overrides);
const needsNetworkApproval = computed(
    () => !props.extension.networkApproved && props.extension.manifest.permissions?.some((permission) => permission.startsWith('network.')),
);
const pagePlacement = ref(
    Object.fromEntries(
        (props.extension.manifest.frontend?.pages ?? []).map((page) => [
            page.slug,
            { group: props.extension.navigationOverrides?.[page.slug]?.group ?? page.sidebar?.group ?? 'extensions' },
        ]),
    ),
);
const savePlacement = () =>
    router.patch(
        `/admin/extensions/${props.extension.id}`,
        { enabled: props.extension.enabled, navigation_overrides: pagePlacement.value },
        { preserveScroll: true },
    );
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
        <div class="min-h-screen bg-surface-dark px-4 py-5 text-foreground sm:px-6 sm:py-7 lg:px-8">
            <div class="mx-auto space-y-5">
                <Link href="/admin/extensions" class="inline-flex items-center gap-2 text-sm font-bold text-muted-foreground hover:text-hive"
                    ><ArrowLeft class="size-4" /> {{ t('ui.back_to_extensions') }}</Link
                >
                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <Package class="size-6 text-hive" />
                            <div>
                                <h1 class="text-2xl font-black">{{ extension.name }}</h1>
                                <p class="mt-2 font-mono text-xs text-muted-foreground">{{ extension.id }} · v{{ extension.version }}</p>
                            </div>
                        </div>
                        <span
                            class="rounded-full border px-3 py-1 text-xs font-bold"
                            :class="
                                extension.enabled
                                    ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                    : 'border-border bg-surface-light text-muted-foreground'
                            "
                            >{{ extension.enabled ? 'Enabled' : 'Disabled' }}</span
                        >
                    </div>
                </section>
                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                    <h2 class="text-lg font-black">{{ t('ui.details') }}</h2>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground">{{ t('ui.version') }}</dt>
                            <dd class="mt-1 font-bold">{{ extension.version }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('ui.panel_compatibility') }}</dt>
                            <dd class="mt-1 font-bold">{{ extension.manifest.panelCompatibility || 'Unknown' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('ui.author') }}</dt>
                            <dd class="mt-1 font-bold">{{ extension.manifest.author || 'Not specified' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('ui.ui_slots') }}</dt>
                            <dd class="mt-1 font-bold">{{ extension.manifest.frontend?.slots?.join(', ') || 'None' }}</dd>
                        </div>
                    </dl>
                    <p v-if="extension.manifest.description" class="mt-4 text-sm text-muted-foreground">{{ extension.manifest.description }}</p>
                </section>
                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                    <h2 class="text-lg font-black">{{ t('ui.permissions') }}</h2>
                    <p class="mt-3 text-sm text-muted-foreground">{{ extension.manifest.permissions?.join(', ') || 'No capabilities' }}</p>
                    <p v-if="extension.manifest.network?.http" class="mt-2 text-sm text-muted-foreground">
                        {{ t('ui.https_hosts') }} {{ extension.manifest.network.http.hosts.join(', ') }}
                    </p>
                    <p v-if="extension.manifest.network?.tcp" class="mt-2 text-sm text-muted-foreground">
                        {{ t('ui.tcp_scope') }} {{ extension.manifest.network.tcp.scope
                        }}{{ t('ui.requires_access_to_the_cell_and_console_command_permission') }}
                    </p>
                    <label v-if="needsNetworkApproval" class="my-4 flex items-start gap-3 text-sm text-foreground">
                        <input v-model="approveNetwork" type="checkbox" class="mt-1" />
                        {{ t('ui.approve_these_declared_network_permissions_when_enabling_this_ext') }}
                    </label>
                    <p v-if="needsNetworkApproval" class="mt-3 text-sm text-status-warning">
                        {{ t('ui.network_approval_is_required_review_the_hosts_and_tcp_scope_above') }}
                    </p>
                    <p v-else-if="extension.networkApproved" class="mt-3 text-sm text-status-success">
                        {{ t('ui.declared_network_permissions_approved') }}
                    </p>
                    <div v-if="extension.manifest.frontend?.pages?.length" class="mt-6 space-y-3">
                        <h2 class="text-lg font-black">{{ t('ui.sidebar_placement') }}</h2>
                        <label v-for="entry in extension.manifest.frontend.pages" :key="entry.slug" class="block text-sm text-muted-foreground">
                            {{ entry.title }}
                            <select
                                v-model="pagePlacement[entry.slug].group"
                                class="mt-2 block rounded-button border border-border bg-surface-dark px-3 py-2 text-foreground"
                            >
                                <option value="overview">{{ t('Overview & Access') }}</option>
                                <option value="management">{{ t('Management') }}</option>
                                <option value="automation">{{ t('Automation & Recovery') }}</option>
                                <option value="configuration">{{ t('Configuration') }}</option>
                                <option value="game">{{ t('ui.game') }}</option>
                                <option value="tools">{{ t('Tools') }}</option>
                                <option value="extensions">{{ t('Extensions') }}</option>
                            </select>
                        </label>
                        <button type="button" class="rounded-button border border-border px-3 py-2 text-sm font-bold" @click="savePlacement">
                            {{ t('ui.save_placement') }}
                        </button>
                    </div>
                    <h2 class="mt-6 text-lg font-black">{{ t('ui.manage') }}</h2>
                    <div
                        v-if="actionError"
                        role="alert"
                        class="mt-4 rounded-button border border-status-danger/30 bg-status-danger/10 p-3 text-sm font-bold text-status-danger"
                    >
                        {{ actionError }}
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">{{ t('ui.enabled_extensions_can_appear_in_the_panel_s_configured_nectar_sl') }}</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <Link
                            v-if="extension.manifest.settings?.length"
                            :href="`/admin/extensions/${extension.id}/settings`"
                            class="inline-flex items-center gap-2 rounded-button border border-border px-4 py-2 text-sm font-black text-foreground hover:bg-surface-light"
                        >
                            <Settings class="size-4" /> {{ t('ui.configure') }}
                        </Link>
                        <button
                            :disabled="busy"
                            class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black disabled:opacity-50"
                            @click="changeState"
                        >
                            <Power class="size-4" />
                            {{ extension.enabled ? 'Disable' : needsNetworkApproval ? 'Approve and enable' : 'Enable' }}</button
                        ><button
                            :disabled="busy"
                            class="inline-flex items-center gap-2 rounded-button border border-status-danger px-4 py-2 text-sm font-black text-status-danger hover:bg-status-danger/30 disabled:opacity-50"
                            @click="remove"
                        >
                            <Trash2 class="size-4" /> {{ t('ui.uninstall') }}
                        </button>
                    </div>
                </section>
                <section v-if="networkAudits?.length" class="rounded-panel border border-border bg-surface p-5">
                    <h2 class="text-lg font-black">{{ t('ui.recent_network_requests') }}</h2>
                    <div v-for="entry in networkAudits" :key="entry.id" class="mt-3 border-t border-border pt-3 text-sm text-muted-foreground">
                        {{ entry.created_at }} · {{ entry.operation }} · {{ entry.destination || 'No destination' }} · {{ entry.outcome }}
                    </div>
                </section>
            </div>
        </div></AppLayout
    >
</template>
