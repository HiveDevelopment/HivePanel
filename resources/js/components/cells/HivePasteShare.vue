<script setup lang="ts">
import { computed, ref } from 'vue'
import { Copy, ExternalLink, X } from 'lucide-vue-next'

const props = defineProps<{ open: boolean; busy: boolean; title: string; error?: string; url?: string; description?: string }>()
const emit = defineEmits<{ close: []; confirm: [] }>()
const copied = ref(false)
const canShare = computed(() => !props.busy && !props.url)
async function copyLink() {
    if (!props.url) return
    try { await navigator.clipboard.writeText(props.url); copied.value = true } catch { copied.value = false }
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/75 p-4" @click.self="emit('close')">
        <div role="dialog" aria-modal="true" aria-label="Share via HivePaste" class="w-full max-w-lg rounded-panel border border-zinc-700 bg-surface p-5 shadow-2xl sm:p-6">
            <div class="flex items-center justify-between gap-4"><h2 class="text-lg font-bold text-white">Share via HivePaste</h2><button type="button" class="text-zinc-400 hover:text-white" aria-label="Close" @click="emit('close')"><X class="size-5" /></button></div>
            <p class="mt-3 break-words text-sm font-semibold text-zinc-200">{{ title }}</p>
            <p class="mt-3 text-sm leading-6 text-zinc-400">{{ description || 'This will upload text to HivePaste. Anyone with the generated link can view it. Check for passwords, tokens, personal information and IP addresses before sharing.' }}</p>
            <p v-if="error" class="mt-3 rounded-lg border border-red-800 bg-red-950/40 p-3 text-sm text-red-300">{{ error }}</p>
            <div v-if="url" class="mt-4 space-y-3"><input :value="url" readonly aria-label="Share URL" class="w-full rounded-lg border border-zinc-700 bg-surface-dark p-3 text-sm text-zinc-200" /><div class="flex flex-wrap gap-2"><button type="button" class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-bold text-white" @click="copyLink"><Copy class="size-4" />{{ copied ? 'Copied' : 'Copy link' }}</button><a :href="url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-button border border-zinc-700 px-4 py-2 text-sm text-zinc-200"><ExternalLink class="size-4" />Open paste</a></div></div>
            <div v-else class="mt-5 flex justify-end gap-2"><button type="button" class="rounded-button border border-zinc-700 px-4 py-2 text-sm text-zinc-300" @click="emit('close')">Cancel</button><button type="button" :disabled="!canShare" class="rounded-button bg-hive px-4 py-2 text-sm font-bold text-white disabled:opacity-50" @click="emit('confirm')">{{ busy ? 'Sharing…' : 'Create share link' }}</button></div>
        </div>
    </div>
</template>
