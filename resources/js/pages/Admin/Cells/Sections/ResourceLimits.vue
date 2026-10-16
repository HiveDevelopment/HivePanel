<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { Cpu, HardDrive, MemoryStick } from 'lucide-vue-next';

const props = defineProps<{
    memoryMb: number;
    overheadMemoryMb: number;
    swapMb: number;
    diskMb: number;
    cpuPercent: number;
    cpuPinning: string;
    ioWeight: number;
    oomKiller: boolean;
    excludeFromResourceCalculation: boolean;
}>();

const emit = defineEmits<{
    'update:memoryMb': [value: number];
    'update:overheadMemoryMb': [value: number];
    'update:swapMb': [value: number];
    'update:diskMb': [value: number];
    'update:cpuPercent': [value: number];
    'update:cpuPinning': [value: string];
    'update:ioWeight': [value: number];
    'update:oomKiller': [value: boolean];
    'update:excludeFromResourceCalculation': [value: boolean];
}>();

function numberValue(event: Event) {
    return Number((event.target as HTMLInputElement).value);
}

function stringValue(event: Event) {
    return (event.target as HTMLInputElement).value;
}

function checkedValue(event: Event) {
    return (event.target as HTMLInputElement).checked;
}
</script>

<template>
    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3">
            <Cpu class="size-5 text-hive" />
            <h2 class="text-lg font-black">{{ t('ui.resource_management') }}</h2>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="flex items-center gap-2 text-sm font-bold text-muted-foreground">
                    <MemoryStick class="size-4 text-muted-foreground" />
                    {{ t('ui.memory') }}
                </label>

                <div class="relative mt-2">
                    <input
                        :value="memoryMb"
                        type="number"
                        min="0"
                        step="128"
                        class="w-full rounded-button border border-border bg-surface-dark px-4 py-3 pr-14 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                        @input="emit('update:memoryMb', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-muted-foreground">{{ t('ui.mib') }}</span>
                </div>

                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    {{ t('ui.set_to_0_for_unlimited_memory') }}
                </p>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm font-bold text-muted-foreground">
                    <HardDrive class="size-4 text-muted-foreground" />
                    {{ t('ui.disk_space') }}
                </label>

                <div class="relative mt-2">
                    <input
                        :value="diskMb"
                        type="number"
                        min="0"
                        step="1024"
                        class="w-full rounded-button border border-border bg-surface-dark px-4 py-3 pr-14 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                        @input="emit('update:diskMb', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-muted-foreground">{{ t('ui.mib') }}</span>
                </div>

                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    {{ t('ui.set_to_0_for_unlimited_disk_usage') }}
                </p>
            </div>

            <div>
                <label class="text-sm font-bold text-muted-foreground">{{ t('ui.cpu_limit') }}</label>

                <div class="relative mt-2">
                    <input
                        :value="cpuPercent"
                        type="number"
                        min="0"
                        max="1000"
                        step="1"
                        class="w-full rounded-button border border-border bg-surface-dark px-4 py-3 pr-12 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                        @input="emit('update:cpuPercent', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-muted-foreground">%</span>
                </div>

                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    {{ t('ui.0_means_unlimited_100_equals_one_full_thread') }}
                </p>
            </div>

            <div>
                <label class="text-sm font-bold text-muted-foreground">{{ t('ui.cpu_pinning') }}</label>

                <input
                    :value="cpuPinning"
                    type="text"
                    :placeholder="t('ui.0_1_3_or_0_3')"
                    class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                    @input="emit('update:cpuPinning', stringValue($event))"
                />

                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    {{ t('ui.optional_advanced_cpu_thread_list') }}
                </p>
            </div>

            <div>
                <label class="text-sm font-bold text-muted-foreground">{{ t('ui.overhead_memory') }}</label>

                <div class="relative mt-2">
                    <input
                        :value="overheadMemoryMb"
                        type="number"
                        min="0"
                        step="64"
                        class="w-full rounded-button border border-border bg-surface-dark px-4 py-3 pr-14 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                        @input="emit('update:overheadMemoryMb', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-muted-foreground">{{ t('ui.mib') }}</span>
                </div>
            </div>

            <div>
                <label class="text-sm font-bold text-muted-foreground">{{ t('ui.swap') }}</label>

                <div class="relative mt-2">
                    <input
                        :value="swapMb"
                        type="number"
                        min="-1"
                        step="128"
                        class="w-full rounded-button border border-border bg-surface-dark px-4 py-3 pr-14 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                        @input="emit('update:swapMb', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-muted-foreground">{{ t('ui.mib') }}</span>
                </div>

                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    {{ t('ui.0_disables_swap_1_allows_unlimited_swap') }}
                </p>
            </div>

            <div>
                <label class="text-sm font-bold text-muted-foreground">{{ t('ui.block_io_weight') }}</label>

                <input
                    :value="ioWeight"
                    type="number"
                    min="10"
                    max="1000"
                    step="1"
                    class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                    @input="emit('update:ioWeight', numberValue($event))"
                />

                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    {{ t('ui.relative_disk_io_priority_between_10_and_1000') }}
                </p>
            </div>

            <div class="space-y-3">
                <label class="flex cursor-pointer items-start gap-3 rounded-button border border-border bg-surface-dark p-4">
                    <input :checked="oomKiller" type="checkbox" class="mt-1" @change="emit('update:oomKiller', checkedValue($event))" />

                    <span>
                        <span class="block text-sm font-black text-foreground">{{ t('ui.enable_oom_killer') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-muted-foreground">
                            {{ t('ui.terminates_the_process_if_it_breaches_memory_limits') }}
                        </span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-button border border-border bg-surface-dark p-4">
                    <input
                        :checked="excludeFromResourceCalculation"
                        type="checkbox"
                        class="mt-1"
                        @change="emit('update:excludeFromResourceCalculation', checkedValue($event))"
                    />

                    <span>
                        <span class="block text-sm font-black text-foreground">{{ t('ui.exclude_from_resource_calculation') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-muted-foreground">
                            {{ t('ui.useful_for_testing_or_development_cells') }}
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </section>
</template>
