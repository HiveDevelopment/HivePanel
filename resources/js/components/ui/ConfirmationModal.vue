<!-- resources/js/components/ui/ConfirmationModal.vue -->
<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { X } from 'lucide-vue-next'

defineProps<{
    open: boolean
    title: string
    description?: string
    confirmText?: string
    cancelText?: string
    danger?: boolean
    loading?: boolean
}>()

const emit = defineEmits<{
    cancel: []
    confirm: []
}>()
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
    >
        <div class="w-full max-w-md rounded-panel border border-border bg-surface shadow-[0_25px_80px_rgba(0,0,0,0.55)]">
            <div class="flex items-center justify-between border-b border-border px-6 py-5">
                <h2 class="text-lg font-black text-foreground">{{ t(title ?? '') }}</h2>

                <button
                    class="text-muted-foreground transition hover:text-foreground disabled:opacity-50"
                    :disabled="loading"
                    @click="emit('cancel')"
                >
                    <X class="size-5" />
                </button>
            </div>

            <div class="px-6 py-5">
                <p class="text-sm leading-6 text-muted-foreground">
                    {{ t(description ?? '') }}
                </p>
            </div>

            <div class="flex justify-end gap-3 border-t border-border px-6 py-4">
                <button
                    class="rounded-button border border-border bg-surface-light px-4 py-2 text-sm font-bold text-foreground transition hover:text-foreground disabled:opacity-50"
                    :disabled="loading"
                    @click="emit('cancel')"
                >
                    {{ t(cancelText ?? 'Cancel') }}
                </button>

                <button
                    class="rounded-button px-4 py-2 text-sm font-black text-primary-foreground transition disabled:opacity-50"
                    :class="danger
                        ? 'border border-status-danger bg-status-danger/80 hover:bg-status-danger'
                        : 'border border-hive bg-hive hover:bg-hive-light'"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    {{ t(loading ? 'Please wait...' : (confirmText ?? 'Confirm')) }}
                </button>
            </div>
        </div>
    </div>
</template>