<script setup lang="ts">
import CellToolsShell from '@/components/CellToolsShell.vue';
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import { useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
const props = defineProps<{ cell: any; state: any; connectionError: string; allocations: any[]; canEdit: boolean }>();
const form = useForm({
    enabled: Boolean(props.state?.policy.enabled),
    default: props.state?.policy.default ?? 'allow',
    rules: (props.state?.policy.rules ?? []) as Array<{ source: string; action: string; port: number; note: string }>,
});
const confirm = ref(false);
function add() {
    form.rules.push({ source: '', action: 'deny', port: 0, note: '' });
}
function move(index: number, direction: number) {
    const to = index + direction;
    if (to < 0 || to >= form.rules.length) return;
    const [rule] = form.rules.splice(index, 1);
    form.rules.splice(to, 0, rule);
}
function save() {
    form.put(`/cells/${props.cell.id}/firewall`, { preserveScroll: true, onSuccess: () => (confirm.value = false) });
}
</script>
<template>
    <CellToolsShell :cell="cell" :title="t('ui.firewall')" :description="t('ui.allow_or_block_source_ips_for_this_cell_s_published_services_and_')"
        ><div
            v-if="connectionError || state?.error"
            role="alert"
            class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm text-status-danger"
        >
            {{ connectionError || state.error }}
        </div>
        <form class="space-y-5" @submit.prevent="confirm = true">
            <fieldset :disabled="!canEdit || form.processing" class="space-y-5 disabled:opacity-60">
                <section class="rounded-panel border border-border bg-surface p-5">
                    <label class="flex items-center gap-3 text-sm font-bold text-foreground"
                        ><input v-model="form.enabled" type="checkbox" /> {{ t('ui.enable_firewall_rules') }}</label
                    >
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="text-xs font-semibold text-muted-foreground"
                            >{{ t('ui.default_action')
                            }}<select
                                v-model="form.default"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                            >
                                <option value="allow">{{ t('ui.allow_everyone_except_blocked_addresses') }}</option>
                                <option value="deny">{{ t('ui.block_everyone_except_allowed_addresses') }}</option>
                            </select></label
                        >
                        <p class="self-center text-xs leading-6 text-muted-foreground">
                            {{ t('ui.rules_are_checked_from_top_to_bottom_the_first_matching_rule_wins') }}
                        </p>
                    </div>
                </section>
                <section class="overflow-hidden rounded-panel border border-border bg-surface">
                    <header class="flex items-center justify-between border-b border-border p-5">
                        <h2 class="text-sm font-bold text-foreground">{{ t('ui.source_ip_rules') }}</h2>
                        <button
                            type="button"
                            :disabled="form.rules.length >= 100"
                            class="inline-flex items-center gap-2 text-xs font-semibold text-hive"
                            @click="add"
                        >
                            <Plus class="size-4" /> {{ t('ui.add_rule') }}
                        </button>
                    </header>
                    <p v-if="!form.rules.length" class="p-5 text-sm text-muted-foreground">
                        {{ t('ui.no_individual_rules_the_default_action_applies_to_all_incoming_cl') }}
                    </p>
                    <div
                        v-for="(rule, index) in form.rules"
                        :key="index"
                        class="grid items-end gap-3 border-b border-border p-5 lg:grid-cols-[1.3fr_120px_150px_1fr_auto]"
                    >
                        <label class="text-xs text-muted-foreground"
                            >{{ t('ui.ip_address_or_cidr')
                            }}<input
                                v-model="rule.source"
                                required
                                :placeholder="t('ui.203_0_113_10_or_203_0_113_0_24')"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 font-mono text-xs text-foreground" /></label
                        ><label class="text-xs text-muted-foreground"
                            >{{ t('ui.action')
                            }}<select
                                v-model="rule.action"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                            >
                                <option value="deny">{{ t('ui.block') }}</option>
                                <option value="allow">{{ t('ui.allow') }}</option>
                            </select></label
                        ><label class="text-xs text-muted-foreground"
                            >{{ t('ui.service_port')
                            }}<select
                                v-model.number="rule.port"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                            >
                                <option :value="0">{{ t('ui.all_allocations') }}</option>
                                <option v-for="allocation in allocations" :key="allocation.id" :value="allocation.port">{{ allocation.port }}</option>
                            </select></label
                        ><label class="text-xs text-muted-foreground"
                            >{{ t('ui.note')
                            }}<input
                                v-model="rule.note"
                                maxlength="200"
                                class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                        /></label>
                        <div class="flex items-center gap-1 pb-2">
                            <button
                                type="button"
                                :disabled="index === 0"
                                class="p-1 text-muted-foreground disabled:opacity-30"
                                :aria-label="t('ui.move_rule_up')"
                                @click="move(index, -1)"
                            >
                                <ArrowUp class="size-4" /></button
                            ><button
                                type="button"
                                :disabled="index === form.rules.length - 1"
                                class="p-1 text-muted-foreground disabled:opacity-30"
                                :aria-label="t('ui.move_rule_down')"
                                @click="move(index, 1)"
                            >
                                <ArrowDown class="size-4" /></button
                            ><button
                                type="button"
                                class="p-1 text-status-danger"
                                :aria-label="t('ui.remove_rule')"
                                @click="form.rules.splice(index, 1)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                </section>
                <footer class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-border bg-surface p-5">
                    <p class="text-xs text-muted-foreground">
                        {{ t('ui.changes_apply_to_new_traffic_immediately_block_rules_may_also_int') }}
                    </p>
                    <button
                        v-if="canEdit"
                        :disabled="form.processing"
                        class="rounded-button bg-hive px-5 py-2.5 text-sm font-bold text-primary-foreground"
                    >
                        {{ t('ui.apply_firewall') }}
                    </button>
                </footer>
            </fieldset>
        </form>
        <ConfirmationModal
            :open="confirm"
            :title="t('ui.apply_firewall_rules')"
            :description="
                form.enabled && form.default === 'deny'
                    ? 'Only matching allowed clients will be able to reach this Cell. Check that you have added the addresses you want to keep.'
                    : 'The rules will apply to this Cell’s service ports and reverse-proxied domains.'
            "
            :confirm-text="t('ui.apply_rules')"
            :loading="form.processing"
            @cancel="confirm = false"
            @confirm="save"
    /></CellToolsShell>
</template>
