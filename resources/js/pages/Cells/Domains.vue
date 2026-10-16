<script setup lang="ts">
import DomainRouteCard from '@/components/DomainRouteCard.vue';
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Globe, Plus, X } from 'lucide-vue-next';
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
    redirect: true as boolean,
    max_body_mb: 32,
});
const pool = computed<number[]>(() => state.value?.[form.protocol === 'udp' ? 'udp_ports' : 'tcp_ports'] ?? []);
const errors = computed(() => Object.values({ ...page.props.errors, ...form.errors }));
const webRoutes = computed(() => state.value?.routes?.filter((route: any) => route.protocol === 'http') ?? []);
const serviceRoutes = computed(() => state.value?.routes?.filter((route: any) => route.protocol !== 'http') ?? []);
const advanced = ref(false);
function create(protocol: 'http' | 'tcp' = 'http') {
    form.reset();
    form.clearErrors();
    form.protocol = protocol;
    form.https = protocol === 'http' && Boolean(state.value?.https_available);
    form.allocation_id = props.allocations.find((a) => a.id === props.cell.primary_allocation_id)?.id ?? props.allocations[0]?.id ?? '';
    advanced.value = false;
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
    advanced.value = false;
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
            <section
                class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface px-5 py-5 sm:px-6"
            >
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/[0.06]">
                        <Globe class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-foreground">{{ t('ui.domains_proxies') }}</h1>
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('ui.give_your_website_web_map_or_service_its_own_address') }}</p>
                    </div>
                </div>
                <button
                    v-if="canEdit && state?.enabled"
                    class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2.5 text-sm font-bold text-primary-foreground"
                    @click="create()"
                >
                    <Plus class="size-4" /> {{ t('ui.add_domain') }}
                </button>
            </section>
            <div
                v-if="connectionError || errors.length"
                role="alert"
                class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm text-status-danger"
            >
                <p v-if="connectionError">{{ connectionError }}</p>
                <p v-for="error in errors" :key="String(error)">{{ error }}</p>
                <button class="mt-2 font-bold underline" @click="refresh">{{ t('ui.retry_connection') }}</button>
            </div>
            <section v-if="!state?.enabled && !connectionError" class="rounded-panel border border-foreground/[0.08] bg-surface p-5 sm:p-6">
                <Globe class="mb-3 size-8 text-muted-foreground" />
                <h2 class="font-bold text-foreground">{{ t('ui.this_node_has_no_proxy_enabled_yet') }}</h2>
                <p class="mt-2 text-xs leading-5 text-muted-foreground">
                    {{ t('ui.an_administrator_can_enable_it_under_nodes_web_proxy_your_cell_an') }}
                </p>
            </section>
            <div v-if="state?.enabled" class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-panel border border-foreground/[0.08] bg-surface p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ t('ui.dns_target') }}</p>
                    <p class="mt-2 break-all font-mono text-sm text-foreground">{{ state.dns_address }}</p>
                </div>
                <div class="rounded-panel border border-foreground/[0.08] bg-surface p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ t('ui.website_ports') }}</p>
                    <p class="mt-2 text-sm text-foreground">{{ t('ui.http') }} {{ state.http_port }} {{ t('ui.https') }} {{ state.https_port }}</p>
                </div>
                <div class="rounded-panel border border-foreground/[0.08] bg-surface p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ t('ui.certificate_management') }}</p>
                    <p class="mt-2 text-sm text-foreground">
                        {{ state.https_available ? 'Automatic issuance & renewal' : 'Ask an administrator to enable HTTPS' }}
                    </p>
                </div>
            </div>
            <section v-if="state?.enabled && !webRoutes.length" class="rounded-panel border border-dashed border-border p-10 text-center">
                <Globe class="mx-auto mb-3 size-8 text-muted-foreground" />
                <h2 class="font-bold text-foreground">{{ t('ui.a_home_for_your_service') }}</h2>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-muted-foreground">
                    {{ t('ui.add_a_domain_select_the_cell_port_it_should_reach_then_verify_you') }}
                </p>
            </section>
            <DomainRouteCard
                v-for="route in webRoutes"
                :key="route.id"
                :route="route"
                :proxy="state"
                :allocations="allocations"
                :can-edit="canEdit"
                :busy="busy"
                :copied="copied"
                @edit="edit"
                @remove="remove = $event"
                @action="action"
                @copy="copy"
            />
            <details v-if="state?.enabled" class="rounded-panel border border-border bg-surface p-5">
                <summary class="cursor-pointer text-sm font-bold text-foreground">
                    {{ t('ui.advanced_service_proxies') }} <span class="ml-2 text-xs font-normal text-muted-foreground">{{ t('ui.tcp_udp') }}</span>
                </summary>
                <div class="mt-4 space-y-4">
                    <p class="text-xs leading-5 text-muted-foreground">
                        {{ t('ui.use_these_for_raw_game_or_service_traffic_websites_and_minecraft_') }}
                    </p>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="rounded-button border border-border px-4 py-2 text-xs font-bold text-foreground"
                        @click="create('tcp')"
                    >
                        {{ t('ui.add_service_proxy') }}
                    </button>
                    <DomainRouteCard
                        v-for="route in serviceRoutes"
                        :key="route.id"
                        :route="route"
                        :proxy="state"
                        :allocations="allocations"
                        :can-edit="canEdit"
                        :busy="busy"
                        :copied="copied"
                        @edit="edit"
                        @remove="remove = $event"
                        @action="action"
                        @copy="copy"
                    />
                </div>
            </details>
            <p v-if="state?.enabled" class="text-xs leading-5 text-muted-foreground">
                {{ t('ui.the_service_must_listen_on_its_selected_cell_port_and_on_0_0_0_0_') }}
            </p>
            <Link
                :href="`/cells/${cell.id}/network`"
                class="inline-flex items-center gap-2 rounded-button border border-foreground/[0.08] bg-surface px-4 py-2.5 text-xs font-semibold text-foreground transition hover:border-hive/30 hover:text-hive"
                >{{ t('ui.manage_cell_allocations') }} <ArrowUpRight class="size-4"
            /></Link>
        </main>
        <Teleport to="body"
            ><div
                v-if="editor"
                class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                @click.self="!form.processing && (editor = false)"
            >
                <form
                    class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-panel border border-border bg-surface shadow-2xl"
                    @submit.prevent="save"
                >
                    <header class="flex items-center justify-between border-b border-border px-6 py-5">
                        <h2 class="text-sm font-semibold text-foreground">
                            {{ form.id ? 'Edit route' : form.protocol === 'http' ? 'Add domain' : 'Add service proxy' }}
                        </h2>
                        <button type="button" :aria-label="t('ui.close')" :disabled="form.processing" @click="editor = false">
                            <X class="size-5 text-muted-foreground" />
                        </button>
                    </header>
                    <div class="space-y-5 p-6">
                        <div v-if="Object.keys(form.errors).length" class="text-sm text-status-danger">
                            <p v-for="error in form.errors" :key="error">{{ error }}</p>
                        </div>
                        <label class="block text-xs font-bold text-muted-foreground"
                            >{{ t('ui.hostname')
                            }}<input
                                v-model="form.domain"
                                :readonly="Boolean(form.id)"
                                :placeholder="t('ui.www_example_com')"
                                required
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                        /></label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label v-if="form.protocol !== 'http'" class="block text-xs font-bold text-muted-foreground"
                                >{{ t('ui.protocol')
                                }}<select
                                    v-model="form.protocol"
                                    class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                                >
                                    <option value="tcp">{{ t('ui.tcp') }}</option>
                                    <option value="udp">{{ t('ui.udp') }}</option>
                                </select></label
                            ><label class="block text-xs font-bold text-muted-foreground"
                                >{{ t('ui.cell_allocation')
                                }}<select
                                    v-model="form.allocation_id"
                                    required
                                    class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                                >
                                    <option v-for="allocation in allocations" :key="allocation.id" :value="allocation.id">
                                        {{ t('ui.port') }} {{ allocation.port }} {{ allocation.alias ? `· ${allocation.alias}` : '' }}
                                    </option>
                                </select></label
                            >
                        </div>
                        <label v-if="form.protocol !== 'http'" class="block text-xs font-bold text-muted-foreground"
                            >{{ t('ui.dedicated_public_port')
                            }}<select
                                v-model.number="form.public_port"
                                required
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                            >
                                <option :value="0" disabled>{{ t('ui.select_a_proxy_port') }}</option>
                                <option v-for="port in pool" :key="port" :value="port">{{ port }}</option>
                            </select>
                            <p v-if="!pool.length" class="mt-2 text-status-warning">
                                {{ t('ui.ask_an_administrator_to_offer_ports_for_this_protocol') }}
                            </p></label
                        ><template v-if="form.protocol === 'http'"
                            ><label class="flex gap-3 text-sm text-foreground"
                                ><input v-model="form.https" type="checkbox" :disabled="!state?.https_available" />
                                {{ t('ui.automatically_issue_an_https_certificate') }}</label
                            >
                            <p v-if="!state?.https_available" class="text-xs text-muted-foreground">
                                {{ t('ui.your_administrator_must_configure_certificate_issuance_before_htt') }}
                            </p>
                            <details class="rounded-button border border-border p-3">
                                <summary class="cursor-pointer text-xs font-bold text-muted-foreground">{{ t('ui.advanced_options') }}</summary>
                                <div class="mt-4 space-y-4">
                                    <label v-if="form.https" class="flex gap-3 text-sm text-foreground"
                                        ><input v-model="form.redirect" type="checkbox" /> {{ t('ui.redirect_http_to_https') }}</label
                                    >
                                    <label class="block text-xs font-bold text-muted-foreground"
                                        >{{ t('ui.maximum_upload_size_mb')
                                        }}<input
                                            v-model.number="form.max_body_mb"
                                            type="number"
                                            min="1"
                                            max="128"
                                            class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                                    /></label>
                                </div>
                            </details>
                        </template>
                        <p class="text-xs leading-5 text-muted-foreground">
                            {{ t('ui.you_ll_receive_dns_instructions_after_saving_traffic_is_routed_on') }}
                        </p>
                    </div>
                    <footer class="flex justify-end gap-3 border-t border-border p-5">
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="px-4 py-2 text-sm font-bold text-muted-foreground"
                            @click="editor = false"
                        >
                            {{ t('ui.cancel') }}</button
                        ><button
                            :disabled="form.processing || !allocations.length"
                            class="rounded-button bg-hive px-5 py-2 text-sm font-black text-primary-foreground disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving…' : 'Save route' }}
                        </button>
                    </footer>
                </form>
            </div></Teleport
        >
        <ConfirmationModal
            :open="Boolean(remove)"
            :title="t('ui.remove_this_domain')"
            :description="`Traffic through ${remove?.domain ?? 'this domain'} will stop. Your Cell files and allocations will be kept.`"
            :confirm-text="t('ui.remove_domain')"
            danger
            :loading="Boolean(busy)"
            @cancel="remove = null"
            @confirm="destroy"
        />
    </AppLayout>
</template>
