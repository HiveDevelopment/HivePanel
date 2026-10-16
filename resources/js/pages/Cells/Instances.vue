<script setup lang="ts">
import CellToolsShell from '@/components/CellToolsShell.vue';
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import { router, useForm } from '@inertiajs/vue3';
import { Check, Layers, Play, RefreshCw, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
const props = defineProps<{ cell: any; state: any; connectionError: string; limit: number; canEdit: boolean; activationPending?: string }>();
const form = useForm({ name: '', empty: false });
const confirmation = ref<any>(null);
const busy = ref(false);
let poll: ReturnType<typeof setInterval>;
onMounted(() => {
    poll = setInterval(() => router.reload({ only: ['state', 'connectionError'] }), 5000);
});
onUnmounted(() => clearInterval(poll));
const copying = computed(() => props.state?.operation?.state === 'copying');
const usage = computed(() => (props.state?.instances ?? []).reduce((n: number, row: any) => n + row.bytes, 0));
function size(bytes: number) {
    return `${(bytes / 1024 / 1024).toFixed(1)} MiB`;
}
function save() {
    form.post(`/cells/${props.cell.id}/instances`, { preserveScroll: true, onSuccess: () => form.reset() });
}
function run() {
    busy.value = true;
    const item = confirmation.value;
    const url = `/cells/${props.cell.id}/instances/${item.row.id}`;
    const options = { preserveScroll: true, onSuccess: () => (confirmation.value = null), onFinish: () => (busy.value = false) };
    if (item.kind === 'delete') router.delete(url, options);
    else router.post(url + '/activate', {}, options);
}
</script>
<template>
    <CellToolsShell :cell="cell" :title="t('Instances')" :description="t('ui.save_different_servers_or_modpacks_and_choose_which_one_this_cell')"
        ><template #actions
            ><button
                class="inline-flex items-center gap-2 text-xs font-semibold text-muted-foreground"
                @click="router.reload({ only: ['state', 'cell', 'connectionError'] })"
            >
                <RefreshCw class="size-4" /> {{ t('ui.refresh') }}
            </button></template
        >
        <div
            v-if="connectionError"
            role="alert"
            class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm text-status-danger"
        >
            {{ connectionError }}
        </div>
        <section class="flex flex-wrap items-center justify-between gap-3 rounded-panel border border-hive/20 bg-hive/5 p-5">
            <p class="text-sm text-foreground">
                {{ state?.instances?.length ?? 0 }} / {{ limit }} {{ t('ui.instances') }} {{ size(usage) }} {{ t('ui.total_files') }}
            </p>
            <p class="text-xs text-muted-foreground">{{ t('ui.all_instances_share_this_cell_s_disk_allowance_only_the_active_in') }}</p>
        </section>
        <div
            v-if="state?.operation?.state === 'copying'"
            role="status"
            class="rounded-panel border border-hive/30 bg-hive/5 p-4 text-sm text-primary-foreground"
        >
            {{ t('ui.saving') }} {{ state.operation.name }}{{ t('ui.keep_this_cell_stopped_while_the_files_are_copied_you_can_leave_t') }}
        </div>
        <div
            v-if="state?.operation?.state === 'failed'"
            role="alert"
            class="rounded-panel border border-status-danger/30 p-4 text-sm text-status-danger"
        >
            {{ state.operation.error }}
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <section
                v-for="instance in state?.instances ?? []"
                :key="instance.id"
                class="rounded-panel border bg-surface p-5"
                :class="instance.active ? 'border-hive/40' : 'border-border'"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <Layers class="size-5 text-hive" />
                        <h2 class="font-bold text-foreground">{{ instance.name }}</h2>
                    </div>
                    <span v-if="instance.active" class="inline-flex items-center gap-1 text-xs font-semibold text-hive"
                        ><Check class="size-3" /> {{ t('ui.active_9234069') }}</span
                    >
                </div>
                <p class="mt-3 text-xs text-muted-foreground">{{ instance.comb }} · {{ size(instance.bytes) }}</p>
                <p class="mt-1 break-all text-xs text-muted-foreground">{{ instance.image }}</p>
                <div v-if="canEdit && (!instance.active || activationPending === instance.id)" class="mt-5 flex items-center justify-between">
                    <button
                        class="inline-flex items-center gap-2 rounded-button border border-border px-3 py-2 text-xs font-bold text-foreground"
                        @click="confirmation = { row: instance, kind: 'activate' }"
                    >
                        <Play class="size-3" /> {{ t('ui.activate') }}</button
                    ><button
                        class="text-status-danger"
                        :aria-label="t('ui.delete_instance')"
                        @click="confirmation = { row: instance, kind: 'delete' }"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </section>
        </div>
        <form v-if="canEdit" class="space-y-4 rounded-panel border border-border bg-surface p-5" @submit.prevent="save">
            <h2 class="text-sm font-bold text-foreground">{{ t('ui.save_another_instance') }}</h2>
            <div class="grid items-end gap-4 sm:grid-cols-[1fr_auto]">
                <label class="text-xs font-semibold text-muted-foreground"
                    >{{ t('ui.instance_name')
                    }}<input
                        v-model="form.name"
                        required
                        maxlength="80"
                        :placeholder="t('ui.survival_world_before_modpack')"
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground" /></label
                ><button
                    :disabled="copying || form.processing || (state?.instances?.length ?? 0) >= limit"
                    class="rounded-button bg-hive px-5 py-3 text-sm font-bold text-primary-foreground disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save instance' }}
                </button>
            </div>
            <label class="flex gap-3 text-sm text-foreground"
                ><input v-model="form.empty" type="checkbox" /> {{ t('ui.create_an_empty_instance_instead_of_copying_the_active_files') }}</label
            >
            <p class="text-xs leading-5 text-muted-foreground">
                {{ t('ui.stop_the_cell_first_a_copy_keeps_its_current_files_and_runtime_se') }}
            </p>
        </form>
        <ConfirmationModal
            :open="Boolean(confirmation)"
            :title="confirmation?.kind === 'delete' ? 'Delete saved instance?' : 'Activate this instance?'"
            :description="
                confirmation?.kind === 'delete'
                    ? 'This permanently removes the saved files. The active instance stays intact.'
                    : 'Stop the Cell first. Its current files will be kept as a saved instance, and the selected files and runtime will become active.'
            "
            :confirm-text="confirmation?.kind === 'delete' ? 'Delete instance' : 'Activate instance'"
            :danger="confirmation?.kind === 'delete'"
            :loading="busy"
            @cancel="confirmation = null"
            @confirm="run"
    /></CellToolsShell>
</template>
