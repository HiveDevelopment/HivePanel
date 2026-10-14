<script setup lang="ts">
import AllocationNotes from '@/components/cells/AllocationNotes.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, ChevronDown, Copy, Network, Plus, Save, Star, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
type Allocation = { id: string; ip: string; port: number; alias?: string; notes?: string | null };
const props = defineProps<{
    cell: any;
    allocations: Allocation[];
    available: Allocation[];
    primaryId: string;
    selectedIds: string[];
    limit: number;
    canEdit: boolean;
}>();
const form = useForm({ allocation_ids: [...props.selectedIds], primary_id: props.primaryId });
const addOpen = ref(false);
const addId = ref('');
const copied = ref('');
const copyError = ref('');
const all = computed(() => [...props.allocations, ...props.available]);
const selected = computed(() => all.value.filter((allocation) => form.allocation_ids.includes(allocation.id)));
const unused = computed(() => all.value.filter((allocation) => !form.allocation_ids.includes(allocation.id)));
const max = computed(() => Math.max(1, props.limit));
const atLimit = computed(() => form.allocation_ids.length >= max.value);
const primary = computed(() => all.value.find((allocation) => allocation.id === form.primary_id));
function persisted(id: string) {
    return props.allocations.some((allocation) => allocation.id === id);
}
function address(allocation: Allocation) {
    const host = allocation.alias || allocation.ip;
    return `${host.includes(':') && !host.startsWith('[') ? `[${host}]` : host}:${allocation.port}`;
}
function add() {
    if (!props.canEdit || form.processing || atLimit.value || !unused.value.some((allocation) => allocation.id === addId.value)) return;
    form.allocation_ids.push(addId.value);
    addId.value = '';
    addOpen.value = false;
}
function remove(id: string) {
    if (id !== form.primary_id && props.canEdit && !form.processing) form.allocation_ids = form.allocation_ids.filter((value) => value !== id);
}
async function copy(allocation: Allocation) {
    try {
        await navigator.clipboard.writeText(address(allocation));
        copied.value = allocation.id;
        copyError.value = '';
    } catch {
        copyError.value = 'Unable to copy automatically. Select the address and copy it manually.';
    }
}
</script>

<template>
    <AppLayout context="server" :active-cell="cell">
        <Head :title="`${cell.name} - Network`" />
        <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-white/[0.08] bg-surface px-5 py-5 sm:px-6">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/[0.06]">
                        <Network class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">Network</h1>
                        <p class="mt-1 text-xs text-zinc-500">Manage connection ports and keep track of what each one is used for.</p>
                    </div>
                </div>
                <span class="rounded-full border border-white/[0.08] bg-white/[0.025] px-3 py-1.5 text-[11px] text-zinc-400"
                    >{{ selected.length }} of {{ max }} allocations</span
                >
            </section>

            <section
                v-if="primary"
                class="flex flex-wrap items-center justify-between gap-3 rounded-panel border border-hive/15 bg-hive/[0.04] px-5 py-4"
            >
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-hive">Primary connection</p>
                    <p class="mt-1 select-all font-mono text-sm font-semibold text-zinc-200">{{ address(primary) }}</p>
                    <p v-if="primary.ip === '0.0.0.0' || primary.ip === '::'" class="mt-1 text-[11px] text-zinc-500">
                        Wildcard bind address. Connect using the node’s public hostname or IP.
                    </p>
                </div>
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-button border border-hive/20 px-3 py-2 text-[11px] font-medium text-hive"
                    @click="copy(primary)"
                >
                    <Check v-if="copied === primary.id" class="size-3.5" /><Copy v-else class="size-3.5" />{{
                        copied === primary.id ? 'Copied' : 'Copy address'
                    }}
                </button>
            </section>
            <p v-if="copyError" role="status" class="text-xs text-status-warning">{{ copyError }}</p>

            <p
                v-if="cell.configuration_pending"
                class="rounded-button border border-status-warning/20 bg-status-warning/5 px-4 py-3 text-xs text-status-warning"
            >
                Saved configuration changes are waiting for the Cell’s next start.
            </p>
            <form class="space-y-4" @submit.prevent="form.patch(`/cells/${cell.id}/network`, { preserveScroll: true })">
                <section
                    v-for="allocation in selected"
                    :key="allocation.id"
                    class="overflow-hidden rounded-panel border bg-surface"
                    :class="allocation.id === form.primary_id ? 'border-hive/20' : 'border-white/[0.08]'"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-3">
                        <span class="flex items-center gap-2 text-xs font-semibold text-zinc-300"
                            ><Network class="size-3.5 text-zinc-500" />{{
                                allocation.id === form.primary_id ? 'Primary allocation' : 'Additional allocation'
                            }}<span v-if="!persisted(allocation.id)" class="rounded bg-hive/10 px-2 py-0.5 text-[10px] text-hive">New</span></span
                        >
                        <div class="flex items-center gap-3">
                            <span v-if="allocation.id === form.primary_id" class="flex items-center gap-1 text-[10px] font-medium text-hive"
                                ><Star class="size-3" /> Primary</span
                            ><button
                                v-else-if="canEdit"
                                type="button"
                                :disabled="form.processing"
                                class="text-[11px] text-zinc-400 hover:text-hive"
                                @click="form.primary_id = allocation.id"
                            >
                                Make primary</button
                            ><button
                                v-if="canEdit && allocation.id !== form.primary_id"
                                type="button"
                                :disabled="form.processing"
                                :aria-label="`Remove ${address(allocation)}`"
                                class="text-zinc-600 hover:text-status-danger"
                                @click="remove(allocation.id)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <div class="grid items-start gap-5 p-5 lg:grid-cols-[200px_100px_minmax(0,1fr)]">
                        <div>
                            <p class="text-[10px] font-medium uppercase tracking-wider text-zinc-600">
                                {{ allocation.alias ? 'Hostname' : 'IP address' }}
                            </p>
                            <p class="mt-2 select-all break-all font-mono text-sm text-zinc-200">{{ allocation.alias || allocation.ip }}</p>
                            <p v-if="allocation.alias" class="mt-2 text-[10px] text-zinc-600">Bind: {{ allocation.ip }}</p>
                            <button
                                type="button"
                                class="mt-3 flex items-center gap-1.5 text-[11px] text-zinc-500 hover:text-zinc-200"
                                @click="copy(allocation)"
                            >
                                <Check v-if="copied === allocation.id" class="size-3" /><Copy v-else class="size-3" />{{
                                    copied === allocation.id ? 'Copied' : 'Copy connection'
                                }}
                            </button>
                        </div>
                        <div>
                            <p class="text-[10px] font-medium uppercase tracking-wider text-zinc-600">Port</p>
                            <p
                                class="mt-2 inline-block rounded-lg border border-white/[0.06] bg-surface-dark px-3 py-1.5 font-mono text-sm text-zinc-200"
                            >
                                {{ allocation.port }}
                            </p>
                        </div>
                        <AllocationNotes
                            v-if="persisted(allocation.id)"
                            :cell-id="cell.id"
                            :allocation-id="allocation.id"
                            :notes="allocation.notes"
                            :can-edit="canEdit && !form.processing"
                        />
                        <p v-else class="py-5 text-xs text-zinc-500">Save allocation changes before adding a note to this port.</p>
                    </div>
                </section>

                <section v-if="canEdit" class="rounded-panel border border-dashed border-white/[0.12] bg-surface/50 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xs font-semibold text-zinc-300">Need another port?</h2>
                            <p class="mt-1 text-[11px] text-zinc-500">
                                {{
                                    atLimit
                                        ? 'Allocation limit reached. Ask an administrator to increase it.'
                                        : unused.length
                                          ? 'Choose an available allocation from this node.'
                                          : 'No free allocations are available on this node.'
                                }}
                            </p>
                        </div>
                        <button
                            type="button"
                            :disabled="atLimit || !unused.length || form.processing"
                            class="flex items-center gap-2 rounded-button border border-white/[0.08] px-3 py-2 text-xs text-zinc-300 disabled:opacity-40"
                            @click="addOpen = !addOpen"
                        >
                            <Plus class="size-3.5" /> Add allocation
                        </button>
                    </div>
                    <div v-if="addOpen && !atLimit" class="mt-4 flex flex-wrap gap-3">
                        <div class="relative min-w-0 flex-1">
                            <select
                                v-model="addId"
                                aria-label="Available allocation"
                                class="h-10 w-full appearance-none rounded-button border border-white/[0.08] bg-surface-dark pl-3 pr-9 text-xs text-zinc-200"
                            >
                                <option value="" disabled>Choose a port</option>
                                <option v-for="allocation in unused" :key="allocation.id" :value="allocation.id">
                                    {{ address(allocation) }}
                                </option></select
                            ><ChevronDown class="pointer-events-none absolute right-3 top-3 size-4 text-zinc-500" />
                        </div>
                        <button
                            type="button"
                            :disabled="!addId || form.processing"
                            class="rounded-button bg-hive px-4 py-2 text-xs font-semibold text-black disabled:opacity-40"
                            @click="add"
                        >
                            Add port
                        </button>
                    </div>
                </section>
                <div
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="rounded-panel border border-status-danger/20 bg-status-danger/5 p-4 text-xs text-status-danger"
                >
                    <p v-for="(message, key) in form.errors" :key="key">{{ message }}</p>
                </div>
                <footer class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-white/[0.08] bg-surface px-5 py-4">
                    <div>
                        <p class="text-xs font-medium text-zinc-300">{{ form.isDirty ? 'Unsaved allocation changes' : 'Allocation settings' }}</p>
                        <p class="mt-1 text-[11px] text-zinc-500">
                            Port changes apply on the next start. Notes save separately and apply immediately.
                        </p>
                    </div>
                    <button
                        v-if="canEdit"
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 rounded-button bg-hive px-5 py-2.5 text-xs font-bold text-black disabled:opacity-50"
                    >
                        <Save class="size-4" />{{ form.processing ? 'Saving…' : 'Save changes' }}</button
                    ><span v-else class="text-xs text-zinc-500">View only</span>
                    <p v-if="form.recentlySuccessful" role="status" class="w-full text-xs text-status-success">Allocation settings saved.</p>
                </footer>
            </form>
        </main>
    </AppLayout>
</template>
