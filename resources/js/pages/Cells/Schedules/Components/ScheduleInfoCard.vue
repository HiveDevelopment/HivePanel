<script setup lang="ts">
import { t } from '@/composables/useLanguage';
defineProps<{
    schedule: any;
    formatDate: (value?: string) => string;
}>();
</script>

<template>
    <section class="rounded-panel border border-border bg-surface p-5">
        <h2 class="text-lg font-black">{{ t('ui.schedule_info') }}</h2>

        <div class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">{{ t('ui.last_run') }}</span>
                <span class="font-bold text-foreground">{{ formatDate(schedule.last_run_at) }}</span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">{{ t('ui.next_run') }}</span>
                <span class="font-bold text-foreground">{{ formatDate(schedule.next_run_at) }}</span>
            </div>
        </div>
    </section>
</template>
