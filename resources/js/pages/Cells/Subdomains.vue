<script setup lang="ts">
import CellToolsShell from '@/components/CellToolsShell.vue';
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Globe, Trash2 } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
const props = defineProps<{ cell: any; zones: any[]; subdomains: any[]; allocations: any[]; limit: number; canEdit: boolean }>();
const form = useForm({
    zone_id: props.zones[0]?.id ?? '',
    label: '',
    allocation_id: props.allocations[0]?.id ?? '',
    kind: props.cell.comb_data?.category === 'web' ? 'web' : 'minecraft',
});
const remove = ref<any>(null);
const busy = ref(false);
function save() {
    form.post(`/cells/${props.cell.id}/subdomains`, { preserveScroll: true, onSuccess: () => form.reset('label') });
}
function destroy() {
    busy.value = true;
    router.delete(`/cells/${props.cell.id}/subdomains/${remove.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (remove.value = null),
        onFinish: () => (busy.value = false),
    });
}
let timer: ReturnType<typeof setInterval>;
onMounted(() => (timer = setInterval(() => router.reload({ only: ['subdomains'] }), 15000)));
onUnmounted(() => clearInterval(timer));
</script>
<template>
    <CellToolsShell :cell="cell" :title="t('ui.subdomains')" :description="t('ui.generate_a_hostname_from_a_domain_offered_by_your_administrator')"
        ><section v-if="!zones.length" class="rounded-panel border border-border bg-surface p-6">
            <Globe class="mb-3 size-6 text-hive" />
            <h2 class="font-bold text-foreground">{{ t('ui.no_domains_are_offered_yet') }}</h2>
            <p class="mt-2 text-sm text-muted-foreground">{{ t('ui.an_administrator_can_add_a_dns_zone_under_cell_features') }}</p>
        </section>
        <div v-if="subdomains.length" class="space-y-3">
            <section v-for="item in subdomains" :key="item.id" class="rounded-panel border border-border bg-surface p-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="break-all font-mono text-sm font-bold text-foreground">{{ item.hostname }}</h2>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{
                                item.kind === 'minecraft'
                                    ? 'Minecraft Java · automatic SRV record'
                                    : item.kind === 'web'
                                      ? 'Website · automatic HTTPS'
                                      : 'Service hostname · use your allocation port'
                            }}
                            · {{ item.state }}
                        </p>
                    </div>
                    <div v-if="canEdit" class="flex items-center gap-3">
                        <button
                            v-if="item.state === 'failed'"
                            class="text-xs font-semibold text-hive"
                            @click="router.post(`/cells/${cell.id}/subdomains/${item.id}/retry`, {}, { preserveScroll: true })"
                        >
                            {{ t('ui.retry_setup') }}</button
                        ><button class="text-status-danger" :aria-label="t('ui.remove_subdomain')" @click="remove = item">
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
                <p v-if="item.error" class="mt-3 text-xs text-status-danger">{{ item.error }}</p>
                <Link v-if="item.kind === 'web'" :href="`/cells/${cell.id}/domains`" class="mt-3 inline-block text-xs font-semibold text-hive">{{
                    t('ui.view_domain_and_certificate_status')
                }}</Link>
            </section>
        </div>
        <form v-if="canEdit && zones.length" class="space-y-5 rounded-panel border border-border bg-surface p-5 sm:p-6" @submit.prevent="save">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-foreground">{{ t('ui.generate_a_subdomain') }}</h2>
                <span class="text-xs text-muted-foreground">{{ subdomains.length }} / {{ limit }}</span>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-xs text-muted-foreground"
                    >{{ t('ui.subdomain_label')
                    }}<input
                        v-model="form.label"
                        pattern="[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?"
                        required
                        maxlength="63"
                        :placeholder="t('ui.play')"
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 font-mono text-sm text-foreground" /></label
                ><label class="text-xs text-muted-foreground"
                    >{{ t('ui.offered_domain')
                    }}<select
                        v-model="form.zone_id"
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                    >
                        <option v-for="zone in zones" :key="zone.id" :value="zone.id">.{{ zone.domain }}</option>
                    </select></label
                ><label class="text-xs text-muted-foreground"
                    >{{ t('ui.existing_allocation')
                    }}<select
                        v-model="form.allocation_id"
                        required
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                    >
                        <option v-for="allocation in allocations" :key="allocation.id" :value="allocation.id">
                            {{ t('ui.port') }} {{ allocation.port }}
                        </option>
                    </select></label
                ><label class="text-xs text-muted-foreground"
                    >{{ t('ui.purpose')
                    }}<select
                        v-model="form.kind"
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                    >
                        <option value="minecraft">{{ t('ui.minecraft_java') }}</option>
                        <option value="web">{{ t('ui.website_web_map') }}</option>
                        <option value="service">{{ t('ui.other_service_bedrock') }}</option>
                    </select></label
                >
            </div>
            <p class="text-xs leading-5 text-muted-foreground">
                {{ t('ui.dns_records_are_created_automatically_minecraft_java_uses_an_srv_') }}
            </p>
            <button
                :disabled="form.processing || subdomains.length >= limit"
                class="rounded-button bg-hive px-5 py-2.5 text-sm font-bold text-primary-foreground disabled:opacity-50"
            >
                {{ form.processing ? 'Creating…' : 'Generate subdomain' }}
            </button>
        </form>
        <ConfirmationModal
            :open="Boolean(remove)"
            :title="t('ui.remove_this_subdomain')"
            :description="t('ui.its_managed_dns_records_and_website_route_will_be_removed_the_cel')"
            :confirm-text="t('ui.remove_subdomain')"
            danger
            :loading="busy"
            @cancel="remove = null"
            @confirm="destroy"
    /></CellToolsShell>
</template>
