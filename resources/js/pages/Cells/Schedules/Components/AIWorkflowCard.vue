<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { Bot } from 'lucide-vue-next';

defineProps<{
    modelValue: string;
    loading: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    generate: [];
}>();
</script>

<template>
    <section class="rounded-panel border border-hive/30 bg-hive/10 p-5">
        <div class="flex items-start gap-3">
            <Bot class="mt-1 size-5 text-hive" />

            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-black text-hive">{{ t('ui.ai_workflow_generator') }}</h2>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">
                    {{ t('ui.describe_what_you_want_this_schedule_to_do_hivepanel_will_generat') }}
                </p>

                <textarea
                    :value="modelValue"
                    class="mt-4 min-h-28 w-full resize-none rounded-button border border-border bg-surface px-4 py-3 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-hive"
                    :placeholder="t('ui.warn_players_5_minutes_before_restart_take_a_backup_restart_the_s')"
                    @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
                />

                <div class="mt-3 flex justify-end">
                    <button
                        class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-primary-foreground transition hover:bg-hive-light disabled:opacity-50"
                        :disabled="loading"
                        @click="emit('generate')"
                    >
                        <Bot class="size-4" />
                        {{ loading ? 'Generating...' : 'Generate Workflow' }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
