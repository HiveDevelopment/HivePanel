<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
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
        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">{{ node.name }} - Web Proxy</h1>
                                        <span
                                            v-if="node.location"
                                            class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive"
                                            >{{ node.location }}</span
                                        >
                                        <span
                                            v-if="node.scheme"
                                            class="rounded-full border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-xs font-bold text-zinc-400"
                                            >{{ node.scheme }}</span
                                        >
                                    </div>
                                    <p class="mt-2 font-mono text-sm text-zinc-500">{{ node.fqdn || node.public_fqdn }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                                    @click="router.reload({ only: ['status', 'connectionError'] })"
                                >
                                    <RefreshCw class="size-4" /> Refresh status
                                </button>
                                <Link
                                    href="/admin/nodes"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                                    ><ArrowLeft class="size-4" /> Back</Link
                                >
                            </div>
                        </div>
                    </section>
                    <NodeNavigation :node-id="node.id" active="web-proxy" />
                    <div
                        v-if="connectionError || status?.error || errors.length"
                        role="alert"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm text-red-300"
                    >
                        <p>{{ connectionError || status?.error }}</p>
                        <p v-for="error in errors" :key="String(error)">{{ error }}</p>
                    </div>
                    <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
                        <form class="rounded-panel border border-zinc-800 bg-surface" @submit.prevent="confirm = true">
                            <div class="flex items-center justify-between border-b border-zinc-800 p-5">
                                <h2 class="flex items-center gap-2 font-black text-white"><Globe class="size-4 text-hive" /> Node entry point</h2>
                                <span
                                    class="rounded-button px-2 py-1 text-xs font-bold"
                                    :class="status?.running ? 'bg-emerald-500/10 text-emerald-400' : 'bg-zinc-800 text-zinc-400'"
                                    >{{ status?.running ? 'Running' : 'Stopped' }}</span
                                >
                            </div>
                            <fieldset :disabled="!editable || form.processing" class="space-y-5 p-5 disabled:opacity-60 sm:p-6">
                                <label class="flex gap-3 text-sm font-bold text-zinc-200"
                                    ><input v-model="form.enabled" type="checkbox" /> Enable node proxy</label
                                ><label class="block text-xs font-black text-zinc-400"
                                    >Public DNS target<input
                                        v-model="form.dns_address"
                                        required
                                        placeholder="web01.example.com or public IP"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                    />
                                    <p class="mt-2 text-xs font-normal normal-case tracking-normal text-zinc-500">
                                        Users point their domains here. This address must reach this node.
                                    </p></label
                                ><label class="block text-xs font-black text-zinc-400"
                                    >Bind IP<input
                                        v-model="form.bind_ip"
                                        required
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive"
                                /></label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="block text-xs font-black text-zinc-400"
                                        >HTTP port<input
                                            v-model.number="form.http_port"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            required
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" /></label
                                    ><label class="block text-xs font-black text-zinc-400"
                                        >HTTPS port<input
                                            v-model.number="form.https_port"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            required
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                    /></label>
                                </div>
                                <label class="block text-xs font-black text-zinc-400"
                                    >Certificate account email<input
                                        v-model="form.acme_email"
                                        type="email"
                                        placeholder="admin@example.com"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                    />
                                    <p class="mt-2 text-xs font-normal normal-case tracking-normal text-zinc-500">
                                        Required for automatic HTTPS. HTTP-01 validation always uses public port 80.
                                    </p></label
                                >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block text-xs font-black text-zinc-400"
                                        >TCP proxy ports<input
                                            v-model="tcp"
                                            placeholder="25566, 25567"
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" /></label
                                    ><label class="block text-xs font-black text-zinc-400"
                                        >UDP proxy ports<input
                                            v-model="udp"
                                            placeholder="19133, 19134"
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                    /></label>
                                </div>
                                <p class="text-xs leading-5 text-zinc-500">
                                    Up to 32 dedicated ports per protocol. Keep these separate from Cell allocations. Changing the published ports
                                    recreates the proxy container.
                                </p>
                                <label class="block text-xs font-black text-zinc-400"
                                    >Trusted HTTP proxies (CIDR, optional)<textarea
                                        v-model="trusted"
                                        rows="3"
                                        placeholder="192.168.20.214/32"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-xs text-zinc-200 outline-none focus:border-hive"
                                    />
                                    <p class="mt-2 text-xs font-normal normal-case tracking-normal text-zinc-500">
                                        Only list proxies you control. Leave empty for direct connections.
                                    </p></label
                                >
                            </fieldset>
                            <footer v-if="editable" class="flex justify-end border-t border-zinc-800 p-5">
                                <button
                                    :disabled="form.processing || !status"
                                    class="rounded-button bg-hive px-5 py-2.5 text-sm font-black text-white disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Applying…' : 'Apply settings' }}
                                </button>
                            </footer>
                        </form>
                        <aside class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <h2 class="flex items-center gap-2 font-bold text-white">
                                    <ShieldCheck class="size-4 text-hive" /> Managed on this node
                                </h2>
                                <ul class="mt-4 space-y-3 text-sm leading-6 text-zinc-400">
                                    <li>The Worker deploys and reloads a separate Nginx container.</li>
                                    <li>Verified HTTP domains share the web ports. WebSockets are supported.</li>
                                    <li>TCP and UDP routes each reserve a dedicated public port.</li>
                                    <li>Certificates are issued after DNS verification and renewed automatically.</li>
                                </ul>
                            </section>
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <h2 class="font-bold text-zinc-200">Before enabling</h2>
                                <p class="mt-3 text-sm leading-6 text-zinc-400">
                                    Use a user-defined Docker network for the Worker’s Cells. Open or forward the web ports and your offered service
                                    ports. Keep the Worker API private. This service routes traffic; it does not give a private node a public IP.
                                </p>
                                <p class="mt-3 text-xs leading-5 text-zinc-500">
                                    Disabling removes the proxy container and retains its routes and certificates for later.
                                </p>
                            </section>
                        </aside>
                    </div>
                    <section v-if="status?.routes.length" class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <header class="border-b border-zinc-800 p-5"><h2 class="font-black text-white">Domains on this node</h2></header>
                        <div class="divide-y divide-zinc-800">
                            <div v-for="route in status.routes" :key="route.id" class="flex flex-wrap items-center justify-between gap-3 p-5">
                                <div>
                                    <p class="font-bold text-zinc-200">{{ route.domain }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">
                                        {{ route.protocol.toUpperCase() }} · Cell port {{ route.port
                                        }}<span v-if="route.protocol !== 'http'"> · Public port {{ route.public_port }}</span> ·
                                        {{ route.verified ? 'Verified' : 'Pending DNS' }}
                                    </p>
                                </div>
                                <button
                                    v-if="editable"
                                    class="p-2 text-zinc-500 hover:text-red-400"
                                    aria-label="Remove route"
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
            title="Apply node proxy settings?"
            description="The Worker will deploy or update this node’s Nginx container. Changes to bind addresses or published ports can briefly interrupt all domains on this node. Disabling stops their proxy traffic."
            confirm-text="Apply settings"
            :loading="form.processing"
            @cancel="confirm = false"
            @confirm="save" /><ConfirmationModal
            :open="Boolean(remove)"
            title="Remove this proxy route?"
            :description="`Traffic through ${remove?.domain ?? 'this domain'} will stop. The Cell remains available through its allocations.`"
            confirm-text="Remove route"
            danger
            :loading="deleting"
            @cancel="remove = null"
            @confirm="destroy"
    /></AppLayout>
</template>
