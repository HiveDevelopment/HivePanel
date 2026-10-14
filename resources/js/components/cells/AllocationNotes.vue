<script setup lang="ts">
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
        <label :for="`notes-${allocationId}`" class="block text-[11px] font-medium text-zinc-400">Allocation notes</label>
        <textarea
            :id="`notes-${allocationId}`"
            v-model="form.notes"
            :disabled="!canEdit || form.processing"
            maxlength="1000"
            rows="2"
            placeholder="e.g. Game port, query port, voice chat…"
            class="mt-2 block w-full resize-y rounded-button border border-white/[0.08] bg-surface-dark px-3 py-2.5 text-xs leading-5 text-zinc-200 outline-none transition placeholder:text-zinc-600 focus:border-hive/40 disabled:opacity-60"
        />
        <div class="mt-2 flex items-center justify-between gap-3">
            <p v-if="form.errors.notes" role="alert" class="text-[11px] text-status-danger">{{ form.errors.notes }}</p>
            <span v-else class="text-[10px] text-zinc-600">{{ form.notes.length }}/1000</span>
            <button
                v-if="canEdit"
                type="button"
                :disabled="form.processing || !form.isDirty"
                class="inline-flex items-center gap-1.5 text-[11px] font-medium text-hive disabled:text-zinc-600"
                @click="save"
            >
                <Check v-if="form.recentlySuccessful" class="size-3" /><Save v-else class="size-3" />{{
                    form.processing ? 'Saving…' : form.recentlySuccessful ? 'Saved' : 'Save note'
                }}
            </button>
        </div>
    </div>
</template>
