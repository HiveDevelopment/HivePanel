<script setup lang="ts">
import CellToolsShell from '@/components/CellToolsShell.vue';
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ArrowUpRight, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const props = defineProps<{ cell: any; combs: any[]; limit: number; splits: any[]; allocations: any[] }>();
const limits = computed(() => props.cell.limits ?? {});
const form = useForm({
    name: '',
    comb_id: props.combs[0]?.id ?? '',
    allocation_id: props.allocations[0]?.id ?? '',
    memory_mb: 512,
    disk_mb: 1024,
    cpu_percent: 50,
    overhead_memory_mb: 0,
    swap_mb: 0,
    variables: {} as Record<string, string>,
});
const chosen = computed(() => props.combs.find((c) => c.id === form.comb_id));
const schema = computed(
    () => chosen.value?.data?.variables_schema?.filter((v: any) => !['memory', 'server_port', 'server_ip'].includes(v.name)) ?? [],
);
const confirmation = ref<'create' | 'delete' | null>(null);
const remove = ref<any>(null);
const deleting = ref(false);
const finite = computed(
    () => ['memory_mb', 'disk_mb', 'cpu_percent'].every((k) => Number(limits.value[k]) > 0) && Number(limits.value.swap_mb ?? 0) >= 0,
);
function run() {
    if (confirmation.value === 'create') {
        form.post(`/cells/${props.cell.id}/splitter`, { onSuccess: () => (confirmation.value = null) });
    } else {
        deleting.value = true;
        router.delete(`/cells/${props.cell.id}/splitter/${remove.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                confirmation.value = null;
                remove.value = null;
            },
            onFinish: () => (deleting.value = false),
        });
    }
}
</script>
<template>
    <CellToolsShell
        :cell="cell"
        :title="t('ui.server_splitter')"
        :description="t('ui.divide_this_cell_s_existing_resource_budget_into_separate_servers')"
        ><div class="grid gap-3 sm:grid-cols-3">
            <section
                v-for="[label, key, suffix] in [
                    ['Memory', 'memory_mb', 'MiB'],
                    ['CPU', 'cpu_percent', '%'],
                    ['Disk', 'disk_mb', 'MiB'],
                ]"
                :key="key"
                class="rounded-panel border border-border bg-surface p-5"
            >
                <p class="text-xs text-muted-foreground">{{ label }} {{ t('ui.remaining_on_parent') }}</p>
                <p class="mt-2 text-xl font-bold text-foreground">{{ limits[key] ?? 0 }} {{ suffix }}</p>
            </section>
        </div>
        <section class="space-y-4 rounded-panel border border-border bg-surface p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-foreground">{{ t('ui.your_splits') }}</h2>
                <span class="text-xs text-muted-foreground">{{ splits.length }} / {{ limit }}</span>
            </div>
            <p v-if="!splits.length" class="text-sm text-muted-foreground">
                {{ t('ui.no_splits_yet_each_split_uses_an_additional_allocation_and_resour') }}
            </p>
            <div
                v-for="item in splits"
                :key="item.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-button border border-border p-4"
            >
                <div>
                    <Link :href="`/cells/${item.child_id}`" class="inline-flex items-center gap-2 text-sm font-bold text-hive"
                        >{{ item.name }} <ArrowUpRight class="size-3"
                    /></Link>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ item.resources.memory_mb }} {{ t('ui.mib_b90afa7') }} {{ item.resources.cpu_percent }}{{ t('ui.cpu_52a58e1') }}
                        {{ item.resources.disk_mb }} {{ t('ui.mib_disk') }}
                    </p>
                    <p v-if="item.error" class="mt-2 text-xs text-status-danger">{{ item.error }}</p>
                </div>
                <button
                    v-if="['active', 'merging', 'recovery_required'].includes(item.state)"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-status-danger"
                    @click="
                        remove = item;
                        confirmation = 'delete';
                    "
                >
                    <Trash2 class="size-4" /> {{ t('ui.delete_return_resources') }}
                </button>
            </div>
        </section>
        <form class="space-y-5 rounded-panel border border-border bg-surface p-5 sm:p-6" @submit.prevent="confirmation = 'create'">
            <h2 class="text-sm font-bold text-foreground">{{ t('ui.create_a_split') }}</h2>
            <p v-if="!finite" class="rounded-button bg-status-warning/10 p-3 text-sm text-status-warning">
                {{ t('ui.splitting_requires_finite_memory_cpu_disk_and_swap_limits_ask_an_') }}
            </p>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-xs text-muted-foreground"
                    >{{ t('ui.server_name_21babfe')
                    }}<input
                        v-model="form.name"
                        required
                        maxlength="100"
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground" /></label
                ><label class="text-xs text-muted-foreground"
                    >{{ t('ui.comb')
                    }}<select
                        v-model="form.comb_id"
                        required
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                    >
                        <option v-for="comb in combs" :key="comb.id" :value="comb.id">{{ comb.name }}</option>
                    </select></label
                ><label class="text-xs text-muted-foreground"
                    >{{ t('ui.parent_s_additional_allocation')
                    }}<select
                        v-model="form.allocation_id"
                        required
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                    >
                        <option v-for="allocation in allocations" :key="allocation.id" :value="allocation.id">
                            {{ t('ui.port') }} {{ allocation.port }}
                        </option>
                    </select></label
                ><label
                    v-for="[label, key, min] in [
                        ['Memory (MiB)', 'memory_mb', 128],
                        ['Disk (MiB)', 'disk_mb', 128],
                        ['CPU (%)', 'cpu_percent', 1],
                        ['Overhead memory (MiB)', 'overhead_memory_mb', 0],
                        ['Swap (MiB)', 'swap_mb', 0],
                    ]"
                    :key="String(key)"
                    class="text-xs text-muted-foreground"
                    >{{ label
                    }}<input
                        v-model.number="(form as any)[key]"
                        type="number"
                        :min="Number(min)"
                        :max="Number(limits[key] ?? 0)"
                        required
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground" /></label
                ><label v-for="variable in schema" :key="variable.name" class="text-xs text-muted-foreground"
                    >{{ variable.label ?? variable.name
                    }}<input
                        v-model="form.variables[variable.name]"
                        :placeholder="String(variable.default ?? '')"
                        class="mt-2 w-full rounded-button border border-border bg-surface-light p-3 text-sm text-foreground"
                /></label>
            </div>
            <p class="text-xs leading-5 text-muted-foreground">
                {{ t('ui.stop_the_parent_first_a_split_receives_its_own_files_and_installa') }}
            </p>
            <button
                :disabled="form.processing || !finite || splits.length >= limit || !allocations.length"
                class="rounded-button bg-hive px-5 py-2.5 text-sm font-bold text-primary-foreground disabled:opacity-50"
            >
                {{ t('ui.create_split') }}
            </button>
        </form>
        <ConfirmationModal
            :open="Boolean(confirmation)"
            :title="confirmation === 'create' ? 'Create this split?' : 'Delete this split?'"
            :description="
                confirmation === 'create'
                    ? 'The parent must be stopped. Its limits will be reduced before the new server is installed.'
                    : 'Stop both servers first. The split’s files will be deleted and its assigned resources returned to the parent.'
            "
            :confirm-text="confirmation === 'create' ? 'Create split' : 'Delete split'"
            :danger="confirmation === 'delete'"
            :loading="form.processing || deleting"
            @cancel="confirmation = null"
            @confirm="run"
    /></CellToolsShell>
</template>
