<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { Copy, ExternalLink, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ open: boolean; busy: boolean; title: string; error?: string; url?: string; description?: string }>();
const emit = defineEmits<{ close: []; confirm: [] }>();
const copied = ref(false);
const canShare = computed(() => !props.busy && !props.url);
async function copyLink() {
    if (!props.url) return;
    try {
        await navigator.clipboard.writeText(props.url);
        copied.value = true;
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/75 p-4" @click.self="emit('close')">
        <div
            role="dialog"
            aria-modal="true"
            :aria-label="t('ui.share_via_hivepaste')"
            class="w-full max-w-lg rounded-panel border border-border bg-surface p-5 shadow-2xl sm:p-6"
        >
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-bold text-foreground">{{ t('ui.share_via_hivepaste') }}</h2>
                <button type="button" class="text-muted-foreground hover:text-foreground" :aria-label="t('ui.close')" @click="emit('close')">
                    <X class="size-5" />
                </button>
            </div>
            <p class="mt-3 break-words text-sm font-semibold text-foreground">{{ title }}</p>
            <p class="mt-3 text-sm leading-6 text-muted-foreground">
                {{
                    description ||
                    'This will upload text to HivePaste. Anyone with the generated link can view it. Check for passwords, tokens, personal information and IP addresses before sharing.'
                }}
            </p>
            <p v-if="error" class="mt-3 rounded-lg border border-status-danger bg-status-danger/40 p-3 text-sm text-status-danger">{{ error }}</p>
            <div v-if="url" class="mt-4 space-y-3">
                <input
                    :value="url"
                    readonly
                    :aria-label="t('ui.share_url')"
                    class="w-full rounded-lg border border-border bg-surface-dark p-3 text-sm text-foreground"
                />
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-bold text-primary-foreground"
                        @click="copyLink"
                    >
                        <Copy class="size-4" />{{ copied ? 'Copied' : 'Copy link' }}</button
                    ><a
                        :href="url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-button border border-border px-4 py-2 text-sm text-foreground"
                        ><ExternalLink class="size-4" />{{ t('ui.open_paste') }}</a
                    >
                </div>
            </div>
            <div v-else class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-button border border-border px-4 py-2 text-sm text-foreground" @click="emit('close')">
                    {{ t('ui.cancel') }}</button
                ><button
                    type="button"
                    :disabled="!canShare"
                    class="rounded-button bg-hive px-4 py-2 text-sm font-bold text-primary-foreground disabled:opacity-50"
                    @click="emit('confirm')"
                >
                    {{ busy ? 'Sharing…' : 'Create share link' }}
                </button>
            </div>
        </div>
    </div>
</template>
