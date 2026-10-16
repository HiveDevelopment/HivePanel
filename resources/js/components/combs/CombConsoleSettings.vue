<script setup lang="ts">
import { t } from '@/composables/useLanguage';
defineProps<{ form: any }>();
</script>

<template>
    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
        <h2 class="text-lg font-black">{{ t('ui.console_and_shutdown') }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">{{ t('ui.define_commands_for_this_comb_suggestions_fill_the_console_input_') }}</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="text-sm font-bold"
                >{{ t('ui.command_suggestions_one_per_line') }}
                <textarea
                    v-model="form.console_commands_text"
                    rows="5"
                    :placeholder="t('ui.listplayers_help')"
                    class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-foreground"
                />
            </label>
            <div class="space-y-3">
                <label class="block text-sm font-bold"
                    >{{ t('ui.shutdown_command') }}
                    <input
                        v-model="form.stop_command"
                        :placeholder="t('ui.quit')"
                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-foreground"
                    />
                </label>
                <p class="text-xs text-muted-foreground">{{ t('ui.leave_blank_for_a_signal_only_server_use_the_shutdown_command_sup') }}</p>
                <label class="block text-sm font-bold"
                    >{{ t('ui.grace_period_seconds') }}
                    <input
                        v-model.number="form.stop_timeout"
                        type="number"
                        min="1"
                        max="600"
                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-foreground"
                    />
                </label>
                <label class="block text-sm font-bold"
                    >{{ t('ui.shutdown_signal') }}
                    <select
                        v-model="form.stop_signal"
                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-foreground"
                    >
                        <option>{{ t('ui.sigterm') }}</option>
                        <option>{{ t('ui.sigint') }}</option>
                        <option>{{ t('ui.sighup') }}</option>
                        <option>{{ t('ui.sigquit') }}</option>
                    </select>
                </label>
            </div>
        </div>
        <p class="mt-3 text-xs text-muted-foreground">{{ t('ui.shutdown_commands_get_the_grace_period_to_finish_before_the_signa') }}</p>
    </section>
</template>
