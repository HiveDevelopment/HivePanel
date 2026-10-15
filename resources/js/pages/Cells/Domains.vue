<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Copy, Globe, LoaderCircle, Pencil, Plus, RefreshCw, ShieldCheck, Trash2, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
const props = defineProps<{ cell: any; proxy: any; connectionError: string; allocations: any[]; canEdit: boolean }>();
const state = ref(props.proxy);
const connectionError = ref(props.connectionError);
const page = usePage();
const base = `/cells/${props.cell.id}/domains`;
const editor = ref(false);
const busy = ref('');
const remove = ref<any>(null);
const copied = ref('');
const form = useForm({
    id: null as string | null,
    domain: '',
    allocation_id: props.allocations[0]?.id ?? '',
    protocol: 'http',
    public_port: 0,
    https: false,
    redirect: true,
    max_body_mb: 32,
});
const pool = computed<number[]>(() => state.value?.[form.protocol === 'udp' ? 'udp_ports' : 'tcp_ports'] ?? []);
const errors = computed(() => Object.values({ ...page.props.errors, ...form.errors }));
function create() {
    form.reset();
    form.clearErrors();
    editor.value = true;
}
function edit(route: any) {
    form.clearErrors();
    Object.assign(form, {
        id: route.id,
        domain: route.domain,
        allocation_id: route.allocation_id,
        protocol: route.protocol,
        public_port: route.public_port,
        https: route.https,
        redirect: route.redirect,
        max_body_mb: route.max_body_mb,
    });
    editor.value = true;
}
function save() {
    if (form.protocol === 'http') form.public_port = 0;
    else {
        form.https = false;
        form.redirect = false;
    }
    form.post(base, {
        preserveScroll: true,
        onSuccess: () => {
            editor.value = false;
            refresh();
        },
    });
}
function action(route: any, kind: string) {
    busy.value = route.id;
    router.post(
        `${base}/${route.id}/${kind}`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = '';
                refresh();
            },
        },
    );
}
function destroy() {
    busy.value = remove.value.id;
    router.delete(`${base}/${remove.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            remove.value = null;
            refresh();
        },
        onFinish: () => (busy.value = ''),
    });
}
async function refresh() {
    try {
        const r = await fetch(`${base}/status`, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
        const data = await r.json();
        if (!r.ok) throw new Error(data.message || 'Node connection unavailable.');
        state.value = data;
        connectionError.value = '';
    } catch (e) {
        connectionError.value = e instanceof Error ? e.message : 'Node connection unavailable.';
    }
}
async function copy(text: string) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = text;
    } catch {
        copied.value = '';
    }
}
function url(route: any) {
    const secure = route.https && route.certificate_state === 'ready';
    const port = secure ? state.value.https_port : state.value.http_port;
    return `${secure ? 'https' : 'http'}://${route.domain}${port === (secure ? 443 : 80) ? '' : ':' + port}`;
}
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => (timer = setInterval(refresh, 10000)));
onUnmounted(() => clearInterval(timer));
</script>
<template>
    <AppLayout context="server" :active-cell="cell">
        <Head :title="`${cell.name} - Domains & Proxies`" />
        <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-white/[0.08] bg-surface px-5 py-5 sm:px-6">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/[0.06]">
                        <Globe class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">Domains & Proxies</h1>
                        <p class="mt-1 text-xs text-zinc-500">Give your website, web map or service its own address.</p>
                    </div>
                </div>
                <button
                    v-if="canEdit && state?.enabled"
                    class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2.5 text-sm font-bold text-white"
                    @click="create"
                >
                    <Plus class="size-4" /> Add domain
                </button>
            </section>
            <div
                v-if="connectionError || errors.length"
                role="alert"
                class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm text-red-200"
            >
                <p v-if="connectionError">{{ connectionError }}</p>
                <p v-for="error in errors" :key="String(error)">{{ error }}</p>
                <button class="mt-2 font-bold underline" @click="refresh">Retry connection</button>
            </div>
            <section v-if="!state?.enabled && !connectionError" class="rounded-panel border border-white/[0.08] bg-surface p-5 sm:p-6">
                <Globe class="mb-3 size-8 text-zinc-500" />
                <h2 class="font-bold text-white">This node has no proxy enabled yet</h2>
                <p class="mt-2 text-xs leading-5 text-zinc-500">
                    An administrator can enable it under Nodes → Web Proxy. Your Cell and its existing allocations continue to work.
                </p>
            </section>
            <div v-if="state?.enabled" class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-panel border border-white/[0.08] bg-surface p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">DNS target</p>
                    <p class="mt-2 break-all font-mono text-sm text-zinc-200">{{ state.dns_address }}</p>
                </div>
                <div class="rounded-panel border border-white/[0.08] bg-surface p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Website ports</p>
                    <p class="mt-2 text-sm text-zinc-200">HTTP {{ state.http_port }} · HTTPS {{ state.https_port }}</p>
                </div>
                <div class="rounded-panel border border-white/[0.08] bg-surface p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Certificate management</p>
                    <p class="mt-2 text-sm text-zinc-200">
                        {{ state.https_available ? 'Automatic issuance & renewal' : 'Ask an administrator to enable HTTPS' }}
                    </p>
                </div>
            </div>
            <section v-if="state?.enabled && !state.routes.length" class="rounded-panel border border-dashed border-zinc-700 p-10 text-center">
                <Globe class="mx-auto mb-3 size-8 text-zinc-500" />
                <h2 class="font-bold text-white">A home for your service</h2>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-zinc-400">
                    Add a domain, select the Cell port it should reach, then verify your DNS record. HTTP domains share the node’s website ports. TCP
                    and UDP routes use a dedicated public port.
                </p>
            </section>
            <section v-for="route in state?.routes ?? []" :key="route.id" class="overflow-hidden rounded-panel border border-white/[0.08] bg-surface">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-800 p-5">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-semibold text-white">{{ route.domain }}</h2>
                            <span class="rounded-button bg-surface-light px-2 py-1 text-[10px] font-black uppercase text-zinc-400">{{
                                route.protocol === 'http' ? (route.https ? 'HTTPS' : 'HTTP') : route.protocol
                            }}</span
                            ><span class="text-xs" :class="route.verified ? 'text-emerald-400' : 'text-amber-400'">{{
                                route.verified ? 'Ownership verified' : 'DNS verification needed'
                            }}</span>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500">
                            Cell port {{ route.port ?? allocations.find((a) => a.id === route.allocation_id)?.port
                            }}<span v-if="route.protocol !== 'http'"> · Public port {{ route.public_port }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            v-if="route.verified && route.protocol === 'http' && (!route.https || route.certificate_state === 'ready')"
                            :href="url(route)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-button border border-zinc-700 p-2 text-zinc-300"
                            aria-label="Open website"
                            ><ArrowUpRight class="size-4" /></a
                        ><button
                            v-if="canEdit"
                            class="rounded-button border border-zinc-700 p-2 text-zinc-400"
                            aria-label="Edit route"
                            @click="edit(route)"
                        >
                            <Pencil class="size-4" /></button
                        ><button
                            v-if="canEdit"
                            class="rounded-button border border-zinc-700 p-2 text-zinc-400 hover:text-red-400"
                            aria-label="Remove route"
                            @click="remove = route"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
                <div class="space-y-4 p-5">
                    <div v-if="route.error" class="rounded-button bg-status-danger/10 p-3 text-sm text-red-300">{{ route.error }}</div>
                    <div v-if="!route.verified" class="space-y-3">
                        <p class="text-sm text-zinc-400">
                            Point this hostname to <strong class="font-mono text-zinc-200">{{ state.dns_address }}</strong> using an A/AAAA or CNAME
                            record. Add this TXT record to prove ownership:
                        </p>
                        <div class="grid gap-3 rounded-button border border-zinc-800 bg-surface-light p-3 sm:grid-cols-[1fr_1.5fr_auto]">
                            <div>
                                <p class="text-[10px] font-bold uppercase text-zinc-500">TXT name</p>
                                <p class="mt-1 break-all font-mono text-xs text-zinc-300">_hivepanel.{{ route.domain }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-zinc-500">TXT value</p>
                                <p class="mt-1 break-all font-mono text-xs text-zinc-300">
                                    hivepanel-verification={{ route.verification_token || 'Save this route again to generate a token' }}
                                </p>
                            </div>
                            <button
                                v-if="route.verification_token"
                                class="self-center text-zinc-400"
                                aria-label="Copy TXT value"
                                @click="copy('hivepanel-verification=' + route.verification_token)"
                            >
                                <Check v-if="copied === 'hivepanel-verification=' + route.verification_token" class="size-4 text-hive" /><Copy
                                    v-else
                                    class="size-4"
                                />
                            </button>
                        </div>
                        <button
                            v-if="canEdit && route.verification_token"
                            :disabled="busy === route.id"
                            class="inline-flex items-center gap-2 rounded-button bg-hive/10 px-4 py-2 text-sm font-bold text-hive disabled:opacity-50"
                            @click="action(route, 'verify')"
                        >
                            <LoaderCircle v-if="busy === route.id" class="size-4 animate-spin" /><ShieldCheck v-else class="size-4" /> Verify DNS
                        </button>
                    </div>
                    <div v-if="route.verified && route.https" class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-zinc-400">
                            <ShieldCheck class="mr-1 inline size-4" /> Certificate:
                            <span class="font-bold text-zinc-200">{{ route.certificate_state }}</span
                            ><span v-if="route.certificate_expires" class="ml-2 text-xs"
                                >Expires {{ new Date(route.certificate_expires).toLocaleDateString() }}</span
                            >
                        </p>
                        <button
                            v-if="canEdit && ['failed', 'none'].includes(route.certificate_state)"
                            :disabled="busy === route.id"
                            class="inline-flex items-center gap-2 rounded-button border border-zinc-700 px-3 py-2 text-xs font-bold text-zinc-200"
                            @click="action(route, 'certificate')"
                        >
                            <RefreshCw class="size-3" /> Request certificate
                        </button>
                    </div>
                    <p v-if="route.protocol === 'tcp'" class="text-xs leading-5 text-zinc-500">
                        Connect to {{ route.domain }}:{{ route.public_port }}. For Minecraft Java, an optional SRV record named _minecraft._tcp.{{
                            route.domain
                        }}
                        with value “0 5 {{ route.public_port }} {{ route.domain }}” lets players use the hostname alone.
                    </p>
                    <p v-if="route.protocol === 'udp'" class="text-xs leading-5 text-zinc-500">
                        This UDP service is available at {{ route.domain }}:{{ route.public_port }}. Set this port in your client.
                    </p>
                </div>
            </section>
            <p v-if="state?.enabled" class="text-xs leading-5 text-zinc-500">
                The service must listen on its selected Cell port and on 0.0.0.0. HTTPS validation needs public port 80 to reach this node. TCP and
                UDP hostnames must resolve directly to the node; standard Cloudflare HTTP proxying cannot carry these routes.
            </p>
            <Link
                :href="`/cells/${cell.id}/network`"
                class="inline-flex items-center gap-2 rounded-button border border-white/[0.08] bg-surface px-4 py-2.5 text-xs font-semibold text-zinc-300 transition hover:border-hive/30 hover:text-hive"
                >Manage Cell allocations <ArrowUpRight class="size-4"
            /></Link>
        </main>
        <Teleport to="body"
            ><div
                v-if="editor"
                class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                @click.self="!form.processing && (editor = false)"
            >
                <form
                    class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-panel border border-zinc-800 bg-surface shadow-2xl"
                    @submit.prevent="save"
                >
                    <header class="flex items-center justify-between border-b border-zinc-800 px-6 py-5">
                        <h2 class="text-sm font-semibold text-white">{{ form.id ? 'Edit route' : 'Add domain' }}</h2>
                        <button type="button" aria-label="Close" :disabled="form.processing" @click="editor = false">
                            <X class="size-5 text-zinc-500" />
                        </button>
                    </header>
                    <div class="space-y-5 p-6">
                        <div v-if="Object.keys(form.errors).length" class="text-sm text-red-300">
                            <p v-for="error in form.errors" :key="error">{{ error }}</p>
                        </div>
                        <label class="block text-xs font-bold text-zinc-400"
                            >Hostname<input
                                v-model="form.domain"
                                :readonly="Boolean(form.id)"
                                placeholder="www.example.com"
                                required
                                class="mt-2 w-full rounded-button border border-zinc-700 bg-surface-light p-3 text-sm text-white"
                        /></label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-xs font-bold text-zinc-400"
                                >Service<select
                                    v-model="form.protocol"
                                    class="mt-2 w-full rounded-button border border-zinc-700 bg-surface-light p-3 text-sm text-white"
                                >
                                    <option value="http">Website / HTTP / web map</option>
                                    <option value="tcp">TCP service / Minecraft Java</option>
                                    <option value="udp">UDP service / Minecraft Bedrock</option>
                                </select></label
                            ><label class="block text-xs font-bold text-zinc-400"
                                >Cell allocation<select
                                    v-model="form.allocation_id"
                                    required
                                    class="mt-2 w-full rounded-button border border-zinc-700 bg-surface-light p-3 text-sm text-white"
                                >
                                    <option v-for="allocation in allocations" :key="allocation.id" :value="allocation.id">
                                        Port {{ allocation.port }} {{ allocation.alias ? `· ${allocation.alias}` : '' }}
                                    </option>
                                </select></label
                            >
                        </div>
                        <label v-if="form.protocol !== 'http'" class="block text-xs font-bold text-zinc-400"
                            >Dedicated public port<select
                                v-model.number="form.public_port"
                                required
                                class="mt-2 w-full rounded-button border border-zinc-700 bg-surface-light p-3 text-sm text-white"
                            >
                                <option :value="0" disabled>Select a proxy port</option>
                                <option v-for="port in pool" :key="port" :value="port">{{ port }}</option>
                            </select>
                            <p v-if="!pool.length" class="mt-2 text-amber-400">Ask an administrator to offer ports for this protocol.</p></label
                        ><template v-if="form.protocol === 'http'"
                            ><label class="flex gap-3 text-sm text-zinc-300"
                                ><input v-model="form.https" type="checkbox" :disabled="!state?.https_available" /> Automatically issue an HTTPS
                                certificate</label
                            ><label v-if="form.https" class="flex gap-3 text-sm text-zinc-300"
                                ><input v-model="form.redirect" type="checkbox" /> Redirect HTTP to HTTPS</label
                            ><label class="block text-xs font-bold text-zinc-400"
                                >Maximum upload size (MB)<input
                                    v-model.number="form.max_body_mb"
                                    type="number"
                                    min="1"
                                    max="128"
                                    class="mt-2 w-full rounded-button border border-zinc-700 bg-surface-light p-3 text-sm text-white" /></label
                        ></template>
                        <p class="text-xs leading-5 text-zinc-500">
                            You’ll receive DNS instructions after saving. Traffic is routed only once ownership is verified.
                        </p>
                    </div>
                    <footer class="flex justify-end gap-3 border-t border-zinc-800 p-5">
                        <button type="button" :disabled="form.processing" class="px-4 py-2 text-sm font-bold text-zinc-400" @click="editor = false">
                            Cancel</button
                        ><button
                            :disabled="form.processing || !allocations.length"
                            class="rounded-button bg-hive px-5 py-2 text-sm font-black text-white disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving…' : 'Save route' }}
                        </button>
                    </footer>
                </form>
            </div></Teleport
        >
        <ConfirmationModal
            :open="Boolean(remove)"
            title="Remove this domain?"
            :description="`Traffic through ${remove?.domain ?? 'this domain'} will stop. Your Cell files and allocations will be kept.`"
            confirm-text="Remove domain"
            danger
            :loading="Boolean(busy)"
            @cancel="remove = null"
            @confirm="destroy"
        />
    </AppLayout>
</template>
