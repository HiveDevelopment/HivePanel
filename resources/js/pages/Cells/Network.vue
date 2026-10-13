<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Network, Save } from 'lucide-vue-next';
type Allocation = { id: string; ip: string; port: number; alias?: string };
const props = defineProps<{ cell: any; allocations: Allocation[]; available: Allocation[]; primaryId: string; selectedIds: string[]; limit: number; canEdit: boolean }>();
const form = useForm({ allocation_ids: [...props.selectedIds], primary_id: props.primaryId });
function toggle(id: string) {
    if (form.allocation_ids.includes(id)) {
        if (id === form.primary_id) return;
        form.allocation_ids = form.allocation_ids.filter(value => value !== id);
    } else if (form.allocation_ids.length < Math.max(1, props.limit)) form.allocation_ids.push(id);
}
</script>

<template>
    <AppLayout context="server" :active-cell="cell">
        <Head :title="`${cell.name} - Network`" />
        <main class="space-y-5 p-5 sm:p-8">
            <section class="rounded-panel border border-white/[0.08] bg-surface p-5"><div class="flex items-center gap-3"><Network class="size-5 text-hive" /><div><h1 class="text-xl font-bold">Network</h1><p class="mt-1 text-xs text-zinc-500">Choose ports from this node’s allocations. Running Cells apply changes on their next start.</p></div></div></section>
            <form class="space-y-5" @submit.prevent="form.patch(`/cells/${cell.id}/network`, { preserveScroll: true })">
                <section class="overflow-hidden rounded-panel border border-white/[0.08] bg-surface">
                    <div class="flex items-center justify-between border-b border-white/[0.06] p-5"><h2 class="text-sm font-semibold">Port allocations</h2><span class="text-xs text-zinc-500">{{ form.allocation_ids.length }} / {{ Math.max(1, limit) }} selected</span></div>
                    <fieldset :disabled="!canEdit || form.processing" class="divide-y divide-white/[0.06]">
                        <div v-for="allocation in [...allocations, ...available]" :key="allocation.id" class="flex flex-wrap items-center justify-between gap-4 p-5">
                            <label class="flex items-center gap-3"><input type="checkbox" :checked="form.allocation_ids.includes(allocation.id)" :disabled="allocation.id === form.primary_id || (!form.allocation_ids.includes(allocation.id) && form.allocation_ids.length >= Math.max(1, limit))" @change="toggle(allocation.id)" /><span><span class="block font-mono text-sm text-zinc-200">{{ allocation.alias || allocation.ip }}:{{ allocation.port }}</span><span v-if="allocation.alias" class="mt-1 block text-xs text-zinc-500">Binds to {{ allocation.ip }}</span></span></label>
                            <label v-if="form.allocation_ids.includes(allocation.id)" class="flex items-center gap-2 text-xs text-zinc-400"><input v-model="form.primary_id" type="radio" :value="allocation.id" name="primary-allocation" /> Primary</label>
                        </div>
                    </fieldset>
                    <p v-if="!allocations.length && !available.length" class="p-5 text-sm text-zinc-500">No allocations are available. Ask an administrator to add ports to this node.</p>
                </section>
                <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-button border border-status-danger/20 p-4 text-xs text-status-danger"><p v-for="(message, key) in form.errors" :key="key">{{ message }}</p></div>
                <p v-if="cell.configuration_pending" class="text-xs text-status-warning">Old ports remain reserved until the Cell restarts and uses the new configuration.</p>
                <button v-if="canEdit" type="submit" :disabled="form.processing" class="flex items-center gap-2 rounded-button bg-hive px-5 py-3 text-xs font-bold text-black disabled:opacity-50"><Save class="size-4" />{{ form.processing ? 'Saving…' : 'Save allocations' }}</button>
                <p v-if="form.recentlySuccessful" role="status" class="text-xs text-status-success">Network settings saved.</p>
            </form>
        </main>
    </AppLayout>
</template>
