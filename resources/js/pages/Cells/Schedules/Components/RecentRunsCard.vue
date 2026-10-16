<script setup lang="ts">
import { t } from '@/composables/useLanguage';
defineProps<{
    schedule: any;
    formatDate: (value?: string) => string;
    statusClass: (status?: string) => string;
    actionName: (type: string) => string;
}>();
</script>

<template>
    <section class="rounded-panel border border-border bg-surface p-5">
        <h2 class="text-lg font-black">{{ t('ui.recent_runs') }}</h2>

        <div class="mt-4 space-y-3">
            <div v-for="run in schedule.runs" :key="run.id" class="rounded-button border border-border bg-surface-dark p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-bold text-foreground">
                        {{ formatDate(run.started_at) }}
                    </span>

                    <span class="rounded-full border px-2 py-0.5 text-xs font-bold capitalize" :class="statusClass(run.status)">
                        {{ run.status }}
                    </span>
                </div>

                <div class="mt-3 space-y-2">
                    <div
                        v-for="(log, index) in run.action_logs ?? []"
                        :key="log.id"
                        class="rounded-button border border-border bg-surface-light px-3 py-2"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-bold text-foreground"> {{ index + 1 }}. {{ actionName(log.type) }} </span>

                            <span class="rounded-full border px-2 py-0.5 text-[11px] font-bold capitalize" :class="statusClass(log.status)">
                                {{ log.status }}
                            </span>
                        </div>

                        <div v-if="log.error" class="mt-2 text-xs font-bold text-status-danger">
                            {{ log.error }}
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="!schedule.runs?.length"
                class="rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-muted-foreground"
            >
                {{ t('ui.this_schedule_has_not_run_yet') }}
            </div>
        </div>
    </section>
</template>
