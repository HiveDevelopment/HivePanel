<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { ArrowUpRight, Check, Copy, LoaderCircle, Pencil, RefreshCw, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{ route: any; proxy: any; allocations: any[]; canEdit: boolean; busy: string; copied: string }>();
defineEmits(['edit', 'remove', 'action', 'copy']);
const dnsType = computed(() =>
    String(props.proxy?.dns_address ?? '').includes(':') ? 'AAAA' : /^\d+\.\d+\.\d+\.\d+$/.test(props.proxy?.dns_address ?? '') ? 'A' : 'CNAME',
);
function url(route: any) {
    const secure = route.https && route.certificate_state === 'ready';
    const port = secure ? props.proxy.https_port : props.proxy.http_port;
    return `${secure ? 'https' : 'http'}://${route.domain}${port === (secure ? 443 : 80) ? '' : ':' + port}`;
}
</script>
<template>
    <section class="overflow-hidden rounded-panel border border-foreground/[0.08] bg-surface">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-5">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-semibold text-foreground">{{ route.domain }}</h2>
                    <span class="rounded-button bg-surface-light px-2 py-1 text-[10px] font-black uppercase text-muted-foreground">{{
                        route.protocol === 'http' ? (route.https ? 'HTTPS' : 'HTTP') : route.protocol
                    }}</span
                    ><span class="text-xs" :class="route.verified ? 'text-status-success' : 'text-status-warning'">{{
                        route.verified ? 'Ownership verified' : 'DNS verification needed'
                    }}</span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ t('ui.cell_port') }} {{ route.port ?? allocations.find((a) => a.id === route.allocation_id)?.port
                    }}<span v-if="route.protocol !== 'http'"> {{ t('ui.public_port') }} {{ route.public_port }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a
                    v-if="route.verified && route.protocol === 'http' && (!route.https || route.certificate_state === 'ready')"
                    :href="url(route)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-button border border-border p-2 text-foreground"
                    :aria-label="t('ui.open_website')"
                    ><ArrowUpRight class="size-4" /></a
                ><button
                    v-if="canEdit && !route.managed"
                    class="rounded-button border border-border p-2 text-muted-foreground"
                    :aria-label="t('ui.edit_route')"
                    @click="$emit('edit', route)"
                >
                    <Pencil class="size-4" /></button
                ><button
                    v-if="canEdit && !route.managed"
                    class="rounded-button border border-border p-2 text-muted-foreground hover:text-status-danger"
                    :aria-label="t('ui.remove_route')"
                    @click="$emit('remove', route)"
                >
                    <Trash2 class="size-4" />
                </button>
            </div>
        </div>
        <div class="space-y-4 p-5">
            <div v-if="route.error" class="rounded-button bg-status-danger/10 p-3 text-sm text-status-danger">{{ route.error }}</div>
            <div v-if="!route.verified" class="space-y-3">
                <p class="text-sm text-muted-foreground">
                    {{ t('ui.add_an') }} <strong>{{ dnsType }}</strong> {{ t('ui.record_named') }}
                    <strong class="font-mono">{{ route.domain }}</strong
                    >{{ t('ui.pointing_to') }} <strong class="font-mono text-foreground">{{ proxy.dns_address }}</strong>
                    {{ t('ui.using_an_a_aaaa_or_cname_record_add_this_txt_record_to_prove_owne') }}
                </p>
                <div class="grid gap-3 rounded-button border border-border bg-surface-light p-3 sm:grid-cols-[1fr_1.5fr_auto]">
                    <div>
                        <p class="text-[10px] font-bold uppercase text-muted-foreground">{{ t('ui.txt_name') }}</p>
                        <p class="mt-1 break-all font-mono text-xs text-foreground">{{ t('ui.hivepanel') }}{{ route.domain }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-muted-foreground">{{ t('ui.txt_value') }}</p>
                        <p class="mt-1 break-all font-mono text-xs text-foreground">
                            {{ t('ui.hivepanel_verification') }}{{ route.verification_token || 'Save this route again to generate a token' }}
                        </p>
                    </div>
                    <button
                        v-if="route.verification_token"
                        class="self-center text-muted-foreground"
                        :aria-label="t('ui.copy_txt_value')"
                        @click="$emit('copy', 'hivepanel-verification=' + route.verification_token)"
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
                    @click="$emit('action', route, 'verify')"
                >
                    <LoaderCircle v-if="busy === route.id" class="size-4 animate-spin" /><ShieldCheck v-else class="size-4" />
                    {{ t('ui.verify_activate') }}
                </button>
            </div>
            <div v-if="route.verified && route.https" class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">
                    <ShieldCheck class="mr-1 inline size-4" /> {{ t('ui.certificate') }}
                    <span class="font-bold text-foreground">{{ route.certificate_state }}</span
                    ><span v-if="route.certificate_expires" class="ml-2 text-xs"
                        >{{ t('ui.expires') }} {{ new Date(route.certificate_expires).toLocaleDateString() }}</span
                    >
                </p>
                <button
                    v-if="canEdit && ['failed', 'none'].includes(route.certificate_state)"
                    :disabled="busy === route.id"
                    class="inline-flex items-center gap-2 rounded-button border border-border px-3 py-2 text-xs font-bold text-foreground"
                    @click="$emit('action', route, 'certificate')"
                >
                    <RefreshCw class="size-3" /> {{ t('ui.request_certificate') }}
                </button>
            </div>
            <p v-if="route.protocol === 'tcp'" class="text-xs leading-5 text-muted-foreground">
                {{ t('ui.connect_to') }} {{ route.domain }}:{{ route.public_port
                }}{{ t('ui.for_minecraft_java_an_optional_srv_record_named_minecraft_tcp') }}{{ route.domain }} {{ t('ui.with_value_0_5') }}
                {{ route.public_port }} {{ route.domain }}{{ t('ui.lets_players_use_the_hostname_alone') }}
            </p>
            <p v-if="route.protocol === 'udp'" class="text-xs leading-5 text-muted-foreground">
                {{ t('ui.this_udp_service_is_available_at') }} {{ route.domain }}:{{ route.public_port }}{{ t('ui.set_this_port_in_your_client') }}
            </p>
        </div>
    </section>
</template>
