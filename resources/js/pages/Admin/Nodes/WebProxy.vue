<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CpuIcon, Globe, RefreshCw, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import NodeNavigation from './NodeNavigation.vue';
const props = defineProps<{ node: any; status: any; connectionError: string }>();
const c = props.status?.config ?? {};
const base = `/admin/nodes/${props.node.id}/web-proxy`;
const page = usePage();
const editable = computed(
    () => Boolean((page.props.auth as any)?.user?.is_admin) || ((page.props.auth as any)?.adminPermissions ?? []).includes('admin.nodes.update'),
);
const form = useForm({
    enabled: Boolean(c.enabled),
    bind_ip: c.bind_ip ?? '0.0.0.0',
    http_port: c.http_port ?? 80,
    https_port: c.https_port ?? 443,
    dns_address: c.dns_address || props.node.public_fqdn,
    acme_email: c.acme_email ?? '',
    tcp_ports: c.tcp_ports ?? ([] as number[]),
    udp_ports: c.udp_ports ?? ([] as number[]),
    trusted_proxies: c.trusted_proxies ?? ([] as string[]),
});
const tcp = ref((c.tcp_ports ?? []).join(', '));
const udp = ref((c.udp_ports ?? []).join(', '));
const trusted = ref((c.trusted_proxies ?? []).join('\n'));
const confirm = ref(false);
const remove = ref<any>(null);
const deleting = ref(false);
const errors = computed(() => Object.values({ ...page.props.errors, ...form.errors }));
function save() {
    form.tcp_ports = tcp.value.trim() ? tcp.value.split(/[\s,]+/).map(Number) : [];
    form.udp_ports = udp.value.trim() ? udp.value.split(/[\s,]+/).map(Number) : [];
    form.trusted_proxies = trusted.value.split(/\s+/).filter(Boolean);
    form.patch(base, { preserveScroll: true, onSuccess: () => (confirm.value = false) });
}
function destroy() {
    deleting.value = true;
    router.delete(`${base}/${remove.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (remove.value = null),
        onFinish: () => (deleting.value = false),
    });
}
</script>
<template>
    <AppLayout context="admin">
        <Head :title="`${node.name} · Web Proxy`" />
        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">{{ node.name }} {{ t('ui.web_proxy') }}</h1>
                                        <span
                                            v-if="node.location"
                                            class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive"
                                            >{{ node.location }}</span
                                        >
                                        <span
                                            v-if="node.scheme"
                                            class="rounded-full border border-border bg-surface-light px-2 py-0.5 text-xs font-bold text-muted-foreground"
                                            >{{ node.scheme }}</span
                                        >
                                    </div>
                                    <p class="mt-2 font-mono text-sm text-muted-foreground">{{ node.fqdn || node.public_fqdn }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                                    @click="router.reload({ only: ['status', 'connectionError'] })"
                                >
                                    <RefreshCw class="size-4" /> {{ t('ui.refresh_status') }}
                                </button>
                                <Link
                                    href="/admin/nodes"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                                    ><ArrowLeft class="size-4" /> {{ t('ui.back') }}</Link
                                >
                            </div>
                        </div>
                    </section>
                    <NodeNavigation :node-id="node.id" active="web-proxy" />
                    <div
                        v-if="connectionError || status?.error || errors.length"
                        role="alert"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm text-status-danger"
                    >
                        <p>{{ connectionError || status?.error }}</p>
                        <p v-for="error in errors" :key="String(error)">{{ error }}</p>
                    </div>
                    <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
                        <form class="rounded-panel border border-border bg-surface" @submit.prevent="confirm = true">
                            <div class="flex items-center justify-between border-b border-border p-5">
                                <h2 class="flex items-center gap-2 font-black text-foreground">
                                    <Globe class="size-4 text-hive" /> {{ t('ui.node_entry_point') }}
                                </h2>
                                <span
                                    class="rounded-button px-2 py-1 text-xs font-bold"
                                    :class="status?.running ? 'bg-status-success/10 text-status-success' : 'bg-surface-light text-muted-foreground'"
                                    >{{ status?.running ? 'Running' : 'Stopped' }}</span
                                >
                            </div>
                            <fieldset :disabled="!editable || form.processing" class="space-y-5 p-5 disabled:opacity-60 sm:p-6">
                                <label class="flex gap-3 text-sm font-bold text-foreground"
                                    ><input v-model="form.enabled" type="checkbox" /> {{ t('ui.enable_node_proxy') }}</label
                                ><label class="block text-xs font-black text-muted-foreground"
                                    >{{ t('ui.public_dns_target')
                                    }}<input
                                        v-model="form.dns_address"
                                        required
                                        :placeholder="t('ui.web01_example_com_or_public_ip')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                    />
                                    <p class="mt-2 text-xs font-normal normal-case tracking-normal text-muted-foreground">
                                        {{ t('ui.users_point_their_domains_here_this_address_must_reach_this_node') }}
                                    </p></label
                                ><label class="block text-xs font-black text-muted-foreground"
                                    >{{ t('ui.bind_ip')
                                    }}<input
                                        v-model="form.bind_ip"
                                        required
                                        class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 font-mono text-sm text-foreground outline-none focus:border-hive"
                                /></label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="block text-xs font-black text-muted-foreground"
                                        >{{ t('ui.http_port')
                                        }}<input
                                            v-model.number="form.http_port"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            required
                                            class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive" /></label
                                    ><label class="block text-xs font-black text-muted-foreground"
                                        >{{ t('ui.https_port')
                                        }}<input
                                            v-model.number="form.https_port"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            required
                                            class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                    /></label>
                                </div>
                                <label class="block text-xs font-black text-muted-foreground"
                                    >{{ t('ui.certificate_account_email')
                                    }}<input
                                        v-model="form.acme_email"
                                        type="email"
                                        :placeholder="t('ui.admin_example_com')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                    />
                                    <p class="mt-2 text-xs font-normal normal-case tracking-normal text-muted-foreground">
                                        {{ t('ui.required_for_automatic_https_http_01_validation_always_uses_publi') }}
                                    </p></label
                                >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block text-xs font-black text-muted-foreground"
                                        >{{ t('ui.tcp_proxy_ports')
                                        }}<input
                                            v-model="tcp"
                                            placeholder="25566, 25567"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive" /></label
                                    ><label class="block text-xs font-black text-muted-foreground"
                                        >{{ t('ui.udp_proxy_ports')
                                        }}<input
                                            v-model="udp"
                                            placeholder="19133, 19134"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                    /></label>
                                </div>
                                <p class="text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.up_to_32_dedicated_ports_per_protocol_keep_these_separate_from_ce') }}
                                </p>
                                <label class="block text-xs font-black text-muted-foreground"
                                    >{{ t('ui.trusted_http_proxies_cidr_optional')
                                    }}<textarea
                                        v-model="trusted"
                                        rows="3"
                                        placeholder="192.168.20.214/32"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-light px-4 py-3 font-mono text-xs text-foreground outline-none focus:border-hive"
                                    />
                                    <p class="mt-2 text-xs font-normal normal-case tracking-normal text-muted-foreground">
                                        {{ t('ui.only_list_proxies_you_control_leave_empty_for_direct_connections') }}
                                    </p></label
                                >
                            </fieldset>
                            <footer v-if="editable" class="flex justify-end border-t border-border p-5">
                                <button
                                    :disabled="form.processing || !status"
                                    class="rounded-button bg-hive px-5 py-2.5 text-sm font-black text-primary-foreground disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Applying…' : 'Apply settings' }}
                                </button>
                            </footer>
                        </form>
                        <aside class="space-y-5">
                            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <h2 class="flex items-center gap-2 font-bold text-foreground">
                                    <ShieldCheck class="size-4 text-hive" /> {{ t('ui.managed_on_this_node') }}
                                </h2>
                                <ul class="mt-4 space-y-3 text-sm leading-6 text-muted-foreground">
                                    <li>{{ t('ui.the_worker_deploys_and_reloads_a_separate_nginx_container') }}</li>
                                    <li>{{ t('ui.verified_http_domains_share_the_web_ports_websockets_are_supporte') }}</li>
                                    <li>{{ t('ui.tcp_and_udp_routes_each_reserve_a_dedicated_public_port') }}</li>
                                    <li>{{ t('ui.certificates_are_issued_after_dns_verification_and_renewed_automa') }}</li>
                                </ul>
                            </section>
                            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <h2 class="font-bold text-foreground">{{ t('ui.before_enabling') }}</h2>
                                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                                    {{ t('ui.use_a_user_defined_docker_network_for_the_worker_s_cells_open_or_') }}
                                </p>
                                <p class="mt-3 text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.disabling_removes_the_proxy_container_and_retains_its_routes_and_') }}
                                </p>
                            </section>
                        </aside>
                    </div>
                    <section v-if="status?.routes.length" class="overflow-hidden rounded-panel border border-border bg-surface">
                        <header class="border-b border-border p-5">
                            <h2 class="font-black text-foreground">{{ t('ui.domains_on_this_node') }}</h2>
                        </header>
                        <div class="divide-y divide-border">
                            <div v-for="route in status.routes" :key="route.id" class="flex flex-wrap items-center justify-between gap-3 p-5">
                                <div>
                                    <p class="font-bold text-foreground">{{ route.domain }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ route.protocol.toUpperCase() }} {{ t('ui.cell_port_61cbd9c') }} {{ route.port
                                        }}<span v-if="route.protocol !== 'http'"> {{ t('ui.public_port') }} {{ route.public_port }}</span> ·
                                        {{ route.verified ? 'Verified' : 'Pending DNS' }}
                                    </p>
                                </div>
                                <button
                                    v-if="editable"
                                    class="p-2 text-muted-foreground hover:text-status-danger"
                                    :aria-label="t('ui.remove_route')"
                                    @click="remove = route"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
        <ConfirmationModal
            :open="confirm"
            :title="t('ui.apply_node_proxy_settings')"
            :description="t('ui.the_worker_will_deploy_or_update_this_node_s_nginx_container_chan')"
            :confirm-text="t('ui.apply_settings')"
            :loading="form.processing"
            @cancel="confirm = false"
            @confirm="save" /><ConfirmationModal
            :open="Boolean(remove)"
            :title="t('ui.remove_this_proxy_route')"
            :description="`Traffic through ${remove?.domain ?? 'this domain'} will stop. The Cell remains available through its allocations.`"
            :confirm-text="t('ui.remove_route')"
            danger
            :loading="deleting"
            @cancel="remove = null"
            @confirm="destroy"
    /></AppLayout>
</template>
