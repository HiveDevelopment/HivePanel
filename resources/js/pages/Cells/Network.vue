<script setup lang="ts">
import AllocationNotes from '@/components/cells/AllocationNotes.vue';
import { t } from '@/composables/useLanguage';
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
            <section
                class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface px-5 py-5 sm:px-6"
            >
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/[0.06]">
                        <Network class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-foreground">{{ t('Network') }}</h1>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ t('ui.manage_connection_ports_and_keep_track_of_what_each_one_is_used_f') }}
                        </p>
                    </div>
                </div>
                <span class="rounded-full border border-foreground/[0.08] bg-foreground/[0.025] px-3 py-1.5 text-[11px] text-muted-foreground"
                    >{{ selected.length }} {{ t('ui.of') }} {{ max }} {{ t('ui.allocations_4b41d56') }}</span
                >
            </section>

            <section
                v-if="primary"
                class="flex flex-wrap items-center justify-between gap-3 rounded-panel border border-hive/15 bg-hive/[0.04] px-5 py-4"
            >
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-hive">{{ t('ui.primary_connection') }}</p>
                    <p class="mt-1 select-all font-mono text-sm font-semibold text-foreground">{{ address(primary) }}</p>
                    <p v-if="primary.ip === '0.0.0.0' || primary.ip === '::'" class="mt-1 text-[11px] text-muted-foreground">
                        {{ t('ui.wildcard_bind_address_connect_using_the_node_s_public_hostname_or') }}
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
                {{ t('ui.port_changes_are_saved_stop_and_start_the_cell_to_recreate_its_do') }}
            </p>
            <form class="space-y-4" @submit.prevent="form.patch(`/cells/${cell.id}/network`, { preserveScroll: true })">
                <section
                    v-for="allocation in selected"
                    :key="allocation.id"
                    class="overflow-hidden rounded-panel border bg-surface"
                    :class="allocation.id === form.primary_id ? 'border-hive/20' : 'border-foreground/[0.08]'"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-foreground/[0.06] px-5 py-3">
                        <span class="flex items-center gap-2 text-xs font-semibold text-foreground"
                            ><Network class="size-3.5 text-muted-foreground" />{{
                                allocation.id === form.primary_id ? 'Primary allocation' : 'Additional allocation'
                            }}<span v-if="!persisted(allocation.id)" class="rounded bg-hive/10 px-2 py-0.5 text-[10px] text-hive">{{
                                t('ui.new')
                            }}</span></span
                        >
                        <div class="flex items-center gap-3">
                            <span v-if="allocation.id === form.primary_id" class="flex items-center gap-1 text-[10px] font-medium text-hive"
                                ><Star class="size-3" /> {{ t('ui.primary') }}</span
                            ><button
                                v-else-if="canEdit"
                                type="button"
                                :disabled="form.processing"
                                class="text-[11px] text-muted-foreground hover:text-hive"
                                @click="form.primary_id = allocation.id"
                            >
                                {{ t('ui.make_primary') }}</button
                            ><button
                                v-if="canEdit && allocation.id !== form.primary_id"
                                type="button"
                                :disabled="form.processing"
                                :aria-label="`Remove ${address(allocation)}`"
                                class="text-muted-foreground hover:text-status-danger"
                                @click="remove(allocation.id)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <div class="grid items-start gap-5 p-5 lg:grid-cols-[200px_100px_minmax(0,1fr)]">
                        <div>
                            <p class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
                                {{ allocation.alias ? 'Hostname' : 'IP address' }}
                            </p>
                            <p class="mt-2 select-all break-all font-mono text-sm text-foreground">{{ allocation.alias || allocation.ip }}</p>
                            <p v-if="allocation.alias" class="mt-2 text-[10px] text-muted-foreground">{{ t('ui.bind') }} {{ allocation.ip }}</p>
                            <button
                                type="button"
                                class="mt-3 flex items-center gap-1.5 text-[11px] text-muted-foreground hover:text-foreground"
                                @click="copy(allocation)"
                            >
                                <Check v-if="copied === allocation.id" class="size-3" /><Copy v-else class="size-3" />{{
                                    copied === allocation.id ? 'Copied' : 'Copy connection'
                                }}
                            </button>
                        </div>
                        <div>
                            <p class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">{{ t('ui.port') }}</p>
                            <p
                                class="mt-2 inline-block rounded-lg border border-foreground/[0.06] bg-surface-dark px-3 py-1.5 font-mono text-sm text-foreground"
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
                        <p v-else class="py-5 text-xs text-muted-foreground">
                            {{ t('ui.save_allocation_changes_before_adding_a_note_to_this_port') }}
                        </p>
                    </div>
                </section>

                <section v-if="canEdit" class="rounded-panel border border-dashed border-foreground/[0.12] bg-surface/50 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xs font-semibold text-foreground">{{ t('ui.need_another_port') }}</h2>
                            <p class="mt-1 text-[11px] text-muted-foreground">
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
                            class="flex items-center gap-2 rounded-button border border-foreground/[0.08] px-3 py-2 text-xs text-foreground disabled:opacity-40"
                            @click="addOpen = !addOpen"
                        >
                            <Plus class="size-3.5" /> {{ t('ui.add_allocation') }}
                        </button>
                    </div>
                    <div v-if="addOpen && !atLimit" class="mt-4 flex flex-wrap gap-3">
                        <div class="relative min-w-0 flex-1">
                            <select
                                v-model="addId"
                                :aria-label="t('ui.available_allocation')"
                                class="h-10 w-full appearance-none rounded-button border border-foreground/[0.08] bg-surface-dark pl-3 pr-9 text-xs text-foreground"
                            >
                                <option value="" disabled>{{ t('ui.choose_a_port') }}</option>
                                <option v-for="allocation in unused" :key="allocation.id" :value="allocation.id">
                                    {{ address(allocation) }}
                                </option></select
                            ><ChevronDown class="pointer-events-none absolute right-3 top-3 size-4 text-muted-foreground" />
                        </div>
                        <button
                            type="button"
                            :disabled="!addId || form.processing"
                            class="rounded-button bg-hive px-4 py-2 text-xs font-semibold text-black disabled:opacity-40"
                            @click="add"
                        >
                            {{ t('ui.add_port') }}
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
                <footer class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface px-5 py-4">
                    <div>
                        <p class="text-xs font-medium text-foreground">{{ form.isDirty ? 'Unsaved allocation changes' : 'Allocation settings' }}</p>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            {{ t('ui.port_changes_apply_on_the_next_start_notes_save_separately_and_ap') }}
                        </p>
                    </div>
                    <button
                        v-if="canEdit"
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 rounded-button bg-hive px-5 py-2.5 text-xs font-bold text-black disabled:opacity-50"
                    >
                        <Save class="size-4" />{{ form.processing ? 'Saving…' : 'Save changes' }}</button
                    ><span v-else class="text-xs text-muted-foreground">{{ t('ui.view_only') }}</span>
                    <p v-if="form.recentlySuccessful" role="status" class="w-full text-xs text-status-success">
                        {{ t('ui.allocation_settings_saved') }}
                    </p>
                </footer>
            </form>
        </main>
    </AppLayout>
</template>
