<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { LoaderCircle, Terminal } from 'lucide-vue-next';
defineProps<{ job: { state: string; message?: string; logs?: string[] } }>();
</script>

<template>
    <section class="overflow-hidden rounded-panel border border-foreground/[0.08] bg-surface">
        <div class="flex items-center gap-3 border-b border-foreground/[0.06] px-5 py-4">
            <LoaderCircle v-if="job.state === 'installing'" class="size-4 animate-spin text-hive" />
            <Terminal v-else class="size-4 text-hive" />
            <h2 class="text-sm font-semibold text-foreground">
                {{ job.state === 'complete' ? 'Installation complete' : job.state === 'failed' ? 'Installation failed' : 'Installing in your Cell' }}
            </h2>
        </div>
        <div class="p-5">
            <p role="status" class="break-words text-xs leading-5 text-muted-foreground">{{ job.message }}</p>
            <details open class="mt-4">
                <summary class="cursor-pointer text-xs text-hive">{{ t('ui.installation_logs') }}</summary>
                <pre
                    class="mt-3 max-h-[60vh] min-h-48 overflow-auto whitespace-pre-wrap break-words rounded-button bg-surface-dark p-4 font-mono text-[11px] leading-5 text-muted-foreground"
                    >{{ job.logs?.join('\n') || 'Waiting for output…' }}</pre
                >
            </details>
        </div>
    </section>
</template>
