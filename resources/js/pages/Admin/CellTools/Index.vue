<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Globe, Layers, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const props = defineProps<{ settings: any; combs: any[]; zones: any[] }>();
const page = usePage();
const canEdit = computed(
    () => Boolean((page.props.auth as any)?.user?.is_admin) || ((page.props.auth as any)?.adminPermissions ?? []).includes('admin.settings.update'),
);
const errors = computed(() => Object.values(page.props.errors ?? {}));
const settings = useForm({ ...props.settings });
const zone = useForm({ id: null as string | null, domain: '', provider_zone_id: '', api_token: '', enabled: true, reserved_labels: [] as string[] });
const reserved = ref('');
const editing = ref(false);
const remove = ref<any>(null);
const deleting = ref(false);
function newZone() {
    zone.reset();
    reserved.value = '';
    editing.value = true;
}
function edit(item: any) {
    zone.id = item.id;
    zone.domain = item.domain;
    zone.provider_zone_id = item.provider_zone_id;
    zone.api_token = '';
    zone.enabled = item.enabled;
    reserved.value = (item.reserved_labels ?? []).join(', ');
    editing.value = true;
}
function saveZone() {
    zone.reserved_labels = reserved.value.split(/[\s,]+/).filter(Boolean);
    zone.post('/admin/cell-tools/zones', { preserveScroll: true, onSuccess: () => (editing.value = false) });
}
function destroy() {
    deleting.value = true;
    router.delete(`/admin/cell-tools/zones/${remove.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (remove.value = null),
        onFinish: () => (deleting.value = false),
    });
}
</script>
<template>
    <AppLayout context="admin"
        ><Head :title="t('ui.cell_features')" />
        <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <section class="flex items-center gap-4 rounded-panel border border-border bg-surface p-5 sm:p-6">
                <Layers class="size-6 text-hive" />
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ t('ui.cell_features') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('ui.control_offered_combs_user_feature_limits_and_managed_subdomain_z') }}</p>
                </div>
            </section>
            <div
                v-if="errors.length"
                role="alert"
                class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm text-status-danger"
            >
                <p v-for="(error, index) in errors" :key="index">{{ error }}</p>
            </div>
            <form
                class="space-y-5 rounded-panel border border-border bg-surface p-5 sm:p-6"
                @submit.prevent="settings.put('/admin/cell-tools', { preserveScroll: true })"
            >
                <fieldset :disabled="!canEdit || settings.processing" class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="text-xs font-semibold text-muted-foreground"
                            >{{ t('ui.splits_per_parent')
                            }}<input
                                v-model.number="settings.split_limit"
                                type="number"
                                min="0"
                                max="20"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground" /></label
                        ><label class="text-xs font-semibold text-muted-foreground"
                            >{{ t('ui.instances_per_cell')
                            }}<input
                                v-model.number="settings.instance_limit"
                                type="number"
                                min="1"
                                max="20"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground" /></label
                        ><label class="text-xs font-semibold text-muted-foreground"
                            >{{ t('ui.subdomains_per_cell')
                            }}<input
                                v-model.number="settings.subdomain_limit"
                                type="number"
                                min="0"
                                max="20"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                        /></label>
                    </div>
                    <label class="flex gap-3 text-sm text-foreground"
                        ><input v-model="settings.firewall_enabled" type="checkbox" /> {{ t('ui.allow_users_to_manage_their_cell_firewall') }}</label
                    >
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-sm font-bold text-foreground">{{ t('ui.combs_offered_for_switching_and_splitting') }}</h2>
                            <button type="button" class="text-xs font-semibold text-hive" @click="settings.allowed_comb_ids = combs.map((c) => c.id)">
                                {{ t('ui.select_all') }}
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ t('ui.users_can_always_reinstall_their_current_comb_new_choices_are_lim') }}
                        </p>
                        <div class="mt-4 grid gap-2 md:grid-cols-3">
                            <label
                                v-for="comb in combs"
                                :key="comb.id"
                                class="flex gap-3 rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                                ><input v-model="settings.allowed_comb_ids" type="checkbox" :value="comb.id" /> {{ comb.name }}</label
                            >
                        </div>
                    </div>
                    <button
                        v-if="canEdit"
                        :disabled="settings.processing"
                        class="rounded-button bg-hive px-5 py-2.5 text-sm font-bold text-primary-foreground"
                    >
                        {{ t('ui.save_feature_settings') }}
                    </button>
                </fieldset>
            </form>
            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                <header class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-foreground">
                        <Globe class="size-4 text-hive" /> {{ t('ui.subdomain_zones') }}
                    </h2>
                    <button
                        v-if="canEdit"
                        class="rounded-button border border-hive/30 bg-hive/10 px-4 py-2 text-xs font-bold text-hive"
                        @click="newZone"
                    >
                        {{ t('ui.add_domain') }}
                    </button>
                </header>
                <p class="mt-3 text-xs leading-5 text-muted-foreground">
                    {{ t('ui.cloudflare_dns_tokens_stay_encrypted_on_the_panel_use_a_token_sco') }}
                </p>
                <div class="mt-4 space-y-3">
                    <div v-for="item in zones" :key="item.id" class="flex items-center justify-between gap-3 rounded-button border border-border p-4">
                        <div>
                            <p class="font-mono text-sm font-bold text-foreground">{{ item.domain }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ item.enabled ? 'Offered to users' : 'Disabled' }}</p>
                        </div>
                        <div v-if="canEdit" class="flex gap-3">
                            <button class="text-muted-foreground" :aria-label="t('ui.edit_zone')" @click="edit(item)">
                                <Pencil class="size-4" /></button
                            ><button class="text-status-danger" :aria-label="t('ui.delete_zone')" @click="remove = item">
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
                <form v-if="editing" class="mt-5 space-y-4 border-t border-border pt-5" @submit.prevent="saveZone">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-xs text-muted-foreground"
                            >{{ t('ui.domain_offered_to_users')
                            }}<input
                                v-model="zone.domain"
                                required
                                :placeholder="t('ui.example_com')"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground" /></label
                        ><label class="text-xs text-muted-foreground"
                            >{{ t('ui.cloudflare_zone_id')
                            }}<input
                                v-model="zone.provider_zone_id"
                                required
                                pattern="[a-f0-9]{32}"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 font-mono text-sm text-foreground" /></label
                        ><label class="text-xs text-muted-foreground"
                            >{{ t('ui.scoped_api_token')
                            }}<input
                                v-model="zone.api_token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="zone.id ? 'Leave blank to keep the existing token' : ''"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground" /></label
                        ><label class="text-xs text-muted-foreground"
                            >{{ t('ui.additional_reserved_labels')
                            }}<input
                                v-model="reserved"
                                :placeholder="t('ui.status_support_billing')"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                        /></label>
                    </div>
                    <label class="flex gap-3 text-sm text-foreground"
                        ><input v-model="zone.enabled" type="checkbox" /> {{ t('ui.offer_this_domain_to_users') }}</label
                    >
                    <div class="flex justify-end gap-3">
                        <button type="button" class="px-4 py-2 text-sm text-muted-foreground" @click="editing = false">{{ t('ui.cancel') }}</button
                        ><button :disabled="zone.processing" class="rounded-button bg-hive px-5 py-2 text-sm font-bold text-primary-foreground">
                            {{ t('ui.save_dns_zone') }}
                        </button>
                    </div>
                </form>
            </section>
        </main>
        <ConfirmationModal
            :open="Boolean(remove)"
            :title="t('ui.delete_this_dns_zone')"
            :description="t('ui.all_generated_subdomains_must_be_removed_first_existing_unrelated')"
            :confirm-text="t('ui.delete_zone')"
            danger
            :loading="deleting"
            @cancel="remove = null"
            @confirm="destroy"
    /></AppLayout>
</template>
