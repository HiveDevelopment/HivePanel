<script setup lang="ts">
import { t } from '@/composables/useLanguage';
defineProps<{
    form: any;
    cron: any;
}>();
</script>

<template>
    <section class="rounded-panel border border-border bg-surface p-5">
        <h2 class="text-lg font-black">{{ t('ui.general') }}</h2>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.name') }}</span>
                <input
                    v-model="form.name"
                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.timezone') }}</span>
                <input
                    v-model="form.timezone"
                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                />
            </label>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-5">
            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.minute') }}</span>
                <input
                    v-model="cron.minute"
                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 font-mono text-sm font-bold text-foreground outline-none focus:border-hive"
                />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.hour') }}</span>
                <input
                    v-model="cron.hour"
                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 font-mono text-sm font-bold text-foreground outline-none focus:border-hive"
                />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.day') }}</span>
                <input
                    v-model="cron.day"
                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 font-mono text-sm font-bold text-foreground outline-none focus:border-hive"
                />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.month') }}</span>
                <input
                    v-model="cron.month"
                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 font-mono text-sm font-bold text-foreground outline-none focus:border-hive"
                />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.day_of_week') }}</span>
                <input
                    v-model="cron.dayOfWeek"
                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 font-mono text-sm font-bold text-foreground outline-none focus:border-hive"
                />
            </label>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
            <label class="flex items-center justify-between gap-4 rounded-button border border-border bg-surface-light px-4 py-3">
                <span>
                    <span class="block text-sm font-bold text-foreground">{{ t('ui.enabled') }}</span>
                    <span class="text-xs text-muted-foreground">{{ t('ui.allow_this_schedule_to_run_automatically') }}</span>
                </span>
                <input v-model="form.enabled" type="checkbox" class="accent-hive" />
            </label>

            <label class="flex items-center justify-between gap-4 rounded-button border border-border bg-surface-light px-4 py-3">
                <span>
                    <span class="block text-sm font-bold text-foreground">{{ t('ui.only_online') }}</span>
                    <span class="text-xs text-muted-foreground">{{ t('ui.only_run_when_the_server_is_online') }}</span>
                </span>
                <input v-model="form.only_when_online" type="checkbox" class="accent-hive" />
            </label>

            <label class="flex items-center justify-between gap-4 rounded-button border border-border bg-surface-light px-4 py-3">
                <span>
                    <span class="block text-sm font-bold text-foreground">{{ t('ui.continue_on_fail') }}</span>
                    <span class="text-xs text-muted-foreground">{{ t('ui.continue_remaining_actions_if_one_fails') }}</span>
                </span>
                <input v-model="form.continue_on_failure" type="checkbox" class="accent-hive" />
            </label>
        </div>
    </section>
</template>
