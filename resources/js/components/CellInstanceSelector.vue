<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import { Link, router } from '@inertiajs/vue3';
import { Layers } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
const props = defineProps<{ cellId: string; canEdit: boolean }>();
const state = ref<any>(null);
const target = ref('');
const pending = ref<any>(null);
const busy = ref(false);
onMounted(async () => {
    try {
        const response = await fetch(`/cells/${props.cellId}/instances/status`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (response.ok) {
            state.value = await response.json();
            target.value = state.value.active;
        }
    } catch {}
});
function select() {
    if (target.value === state.value.active) return;
    pending.value = state.value.instances.find((row: any) => row.id === target.value);
    target.value = state.value.active;
}
function activate() {
    busy.value = true;
    router.post(
        `/cells/${props.cellId}/instances/${pending.value.id}/activate?return_to=files`,
        {},
        { onSuccess: () => (pending.value = null), onFinish: () => (busy.value = false) },
    );
}
</script>
<template>
    <section v-if="state" class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-border bg-surface p-4">
        <div class="flex flex-wrap items-center gap-3">
            <Layers class="size-4 text-hive" /><label class="text-xs font-bold text-muted-foreground"
                >{{ t('ui.active_instance')
                }}<select
                    v-model="target"
                    :disabled="!canEdit || busy"
                    class="ml-3 rounded-button border border-border bg-surface-light px-3 py-2 text-sm text-foreground"
                    @change="select"
                >
                    <option v-for="row in state.instances" :key="row.id" :value="row.id">{{ row.name }}</option>
                </select></label
            ><span class="text-xs text-muted-foreground">{{ t('ui.the_file_manager_shows_this_instance_s_files') }}</span>
        </div>
        <Link :href="`/cells/${cellId}/instances`" class="text-xs font-semibold text-hive">{{ t('ui.manage_instances') }}</Link
        ><ConfirmationModal
            :open="Boolean(pending)"
            :title="t('ui.activate_this_instance')"
            :description="t('ui.stop_the_cell_first_the_current_files_will_be_kept_and_the_select')"
            :confirm-text="t('ui.activate_instance')"
            :loading="busy"
            @cancel="pending = null"
            @confirm="activate"
        />
    </section>
</template>
