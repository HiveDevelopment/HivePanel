<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { useForm } from '@inertiajs/vue3';
import { Check, Save } from 'lucide-vue-next';
const props = defineProps<{ cellId: string; allocationId: string; notes?: string | null; canEdit: boolean }>();
const form = useForm({ notes: props.notes ?? '' });
function save() {
    form.patch(`/cells/${props.cellId}/network/${props.allocationId}/notes`, { preserveScroll: true });
}
</script>

<template>
    <div>
        <label :for="`notes-${allocationId}`" class="block text-[11px] font-medium text-muted-foreground">{{ t('ui.allocation_notes') }}</label>
        <textarea
            :id="`notes-${allocationId}`"
            v-model="form.notes"
            :disabled="!canEdit || form.processing"
            maxlength="1000"
            rows="2"
            :placeholder="t('ui.e_g_game_port_query_port_voice_chat')"
            class="mt-2 block w-full resize-y rounded-button border border-foreground/[0.08] bg-surface-dark px-3 py-2.5 text-xs leading-5 text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/40 disabled:opacity-60"
        />
        <div class="mt-2 flex items-center justify-between gap-3">
            <p v-if="form.errors.notes" role="alert" class="text-[11px] text-status-danger">{{ form.errors.notes }}</p>
            <span v-else class="text-[10px] text-muted-foreground">{{ form.notes.length }}/1000</span>
            <button
                v-if="canEdit"
                type="button"
                :disabled="form.processing || !form.isDirty"
                class="inline-flex items-center gap-1.5 text-[11px] font-medium text-hive disabled:text-muted-foreground"
                @click="save"
            >
                <Check v-if="form.recentlySuccessful" class="size-3" /><Save v-else class="size-3" />{{
                    form.processing ? 'Saving…' : form.recentlySuccessful ? 'Saved' : 'Save note'
                }}
            </button>
        </div>
    </div>
</template>
