<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import {
    AlertTriangle,
    CheckCircle,
    X,
} from 'lucide-vue-next'
import {
    onBeforeUnmount,
    onMounted,
    ref,
} from 'vue'

type FlashMessages = {
    success?: string | null
    error?: string | null
    warning?: string | null
    info?: string | null
}

const page = usePage()

const visible = ref(false)
const message = ref('')
const type = ref<'success' | 'error'>('success')

let hideTimer: number | null = null
let removeSuccessListener: (() => void) | null = null

function displayToast(
    flash?: FlashMessages,
    errors?: Record<string, string>,
) {
    const firstError = errors
        ? Object.values(errors)[0]
        : null

    if (flash?.success) {
        message.value = flash.success
        type.value = 'success'
    } else if (flash?.error) {
        message.value = flash.error
        type.value = 'error'
    } else if (firstError) {
        message.value = firstError
        type.value = 'error'
    } else {
        return
    }

    visible.value = true

    if (hideTimer !== null) {
        window.clearTimeout(hideTimer)
    }

    hideTimer = window.setTimeout(() => {
        visible.value = false
        hideTimer = null
    }, 4500)
}

function closeToast() {
    visible.value = false

    if (hideTimer !== null) {
        window.clearTimeout(hideTimer)
        hideTimer = null
    }
}

onMounted(() => {
    displayToast(
        page.props.flash as FlashMessages | undefined,
        page.props.errors as Record<string, string> | undefined,
    )

    removeSuccessListener = router.on('success', (event) => {
        displayToast(
            event.detail.page.props.flash as FlashMessages | undefined,
            event.detail.page.props.errors as Record<string, string> | undefined,
        )
    })
})

onBeforeUnmount(() => {
    removeSuccessListener?.()

    if (hideTimer !== null) {
        window.clearTimeout(hideTimer)
    }
})
</script>

<template>
    <div
        v-if="visible && message"
        class="fixed bottom-5 right-5 z-[100] w-[calc(100%-2rem)] max-w-md rounded-panel border px-5 py-4 shadow-[0_25px_80px_rgba(0,0,0,0.55)]"
        :class="type === 'success'
            ? 'border-status-success/40 bg-status-success/15 text-status-success'
            : 'border-status-danger/40 bg-status-danger/15 text-status-danger'"
    >
        <div class="flex items-start gap-3">
            <CheckCircle
                v-if="type === 'success'"
                class="mt-0.5 size-5 shrink-0"
            />

            <AlertTriangle
                v-else
                class="mt-0.5 size-5 shrink-0"
            />

            <div class="min-w-0 flex-1 text-sm font-bold leading-6">
                {{ message }}
            </div>

            <button
                type="button"
                class="shrink-0 opacity-70 transition hover:opacity-100"
                @click="closeToast"
            >
                <X class="size-4" />
            </button>
        </div>
    </div>
</template>