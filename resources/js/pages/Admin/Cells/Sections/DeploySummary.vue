<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { Boxes, Cpu, HardDrive, MemoryStick, Network, Plus, Server } from 'lucide-vue-next';

defineProps<{
    node: any;
    allocation: any;
    additionalAllocations: any[];
    name: string;
    comb: string;
    version: string;
    ownerEmail: string;
    memoryMb: number;
    diskMb: number;
    cpuPercent: number;
    dockerImage: string;
    startupCommand: string;
    processing: boolean;
    canSubmit: boolean;
}>();

function formatMb(value: number) {
    if (!value) return 'Unlimited';

    if (value >= 1024) {
        return `${(value / 1024).toFixed(value % 1024 === 0 ? 0 : 1)} GB`;
    }

    return `${value} MiB`;
}
</script>

<template>
    <aside class="space-y-5 xl:sticky xl:top-6 xl:self-start">
        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
            <h2 class="text-lg font-black">{{ t('ui.deploy_summary') }}</h2>

            <div class="mt-5 space-y-4">
                <div class="rounded-button border border-border bg-surface-dark p-4">
                    <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-muted-foreground">
                        <Server class="size-4" />
                        {{ t('ui.node') }}
                    </div>
                    <div class="mt-1 font-black text-foreground">
                        {{ node?.name || 'Not selected' }}
                    </div>
                    <div class="mt-1 text-xs font-bold text-muted-foreground">
                        {{ node?.location || 'No location' }}
                    </div>
                </div>

                <div class="rounded-button border border-border bg-surface-dark p-4">
                    <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-muted-foreground">
                        <Network class="size-4" />
                        {{ t('ui.primary_allocation') }}
                    </div>
                    <div class="mt-1 font-mono font-black text-foreground">
                        <template v-if="allocation"> {{ allocation.ip }}:{{ allocation.port }} </template>
                        <template v-else>
                            {{ t('ui.not_selected') }}
                        </template>
                    </div>
                    <div class="mt-1 text-xs font-bold text-muted-foreground">
                        {{ additionalAllocations.length }} {{ t('ui.additional_allocation_s') }}
                    </div>
                </div>

                <div class="rounded-button border border-border bg-surface-dark p-4">
                    <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-muted-foreground">
                        <Boxes class="size-4" />
                        {{ t('ui.cell') }}
                    </div>
                    <div class="mt-1 font-black text-foreground">
                        {{ name || 'Unnamed Cell' }}
                    </div>
                    <div class="mt-1 text-xs font-bold text-muted-foreground">
                        {{ ownerEmail || 'No owner email set' }}
                    </div>
                </div>

                <div class="rounded-button border border-border bg-surface-dark p-4">
                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                        {{ t('ui.comb') }}
                    </div>
                    <div class="mt-1 font-black text-foreground">
                        {{ comb }}
                    </div>
                    <div class="mt-1 text-xs font-bold text-muted-foreground">{{ t('ui.version') }} {{ version }}</div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-button border border-border bg-surface-dark p-4">
                        <MemoryStick class="size-4 text-hive" />
                        <div class="mt-2 text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.ram') }}</div>
                        <div class="mt-1 font-black text-foreground">{{ formatMb(memoryMb) }}</div>
                    </div>

                    <div class="rounded-button border border-border bg-surface-dark p-4">
                        <Cpu class="size-4 text-hive" />
                        <div class="mt-2 text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.cpu') }}</div>
                        <div class="mt-1 font-black text-foreground">{{ cpuPercent }}%</div>
                    </div>

                    <div class="rounded-button border border-border bg-surface-dark p-4">
                        <HardDrive class="size-4 text-hive" />
                        <div class="mt-2 text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.disk') }}</div>
                        <div class="mt-1 font-black text-foreground">{{ formatMb(diskMb) }}</div>
                    </div>
                </div>

                <div class="rounded-button border border-border bg-surface-dark p-4">
                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                        {{ t('ui.docker_image') }}
                    </div>
                    <div class="mt-1 break-all font-mono text-xs font-bold text-foreground">
                        {{ dockerImage || 'Default image' }}
                    </div>
                </div>

                <div class="rounded-button border border-border bg-surface-dark p-4">
                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                        {{ t('Startup') }}
                    </div>
                    <div class="mt-1 line-clamp-3 break-all font-mono text-xs font-bold text-foreground">
                        {{ startupCommand || 'Default startup command' }}
                    </div>
                </div>
            </div>

            <button
                type="submit"
                class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-button bg-hive px-4 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="processing || !canSubmit"
            >
                <Plus class="size-4" />
                {{ t('ui.deploy_cell') }}
            </button>
        </section>
    </aside>
</template>
