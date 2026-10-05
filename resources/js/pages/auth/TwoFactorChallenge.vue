<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import { Head, useForm } from '@inertiajs/vue3'
import {
    ArrowLeft,
    KeyRound,
    LoaderCircle,
    ShieldCheck,
} from 'lucide-vue-next'
import { computed, nextTick, ref } from 'vue'

const recoveryMode = ref(false)
const codeInputs = ref<HTMLInputElement[]>([])

const form = useForm({
    code: '',
})

const recoveryForm = useForm({
    recovery_code: '',
})

const digits = ref(['', '', '', '', '', ''])

const completeCode = computed(() => digits.value.join(''))

const submit = () => {
    if (completeCode.value.length !== 6 || form.processing) {
        return
    }

    form.code = completeCode.value

    form.post(route('two-factor.login.store'), {
        preserveScroll: true,

        onError: () => {
            digits.value = ['', '', '', '', '', '']
            form.code = ''

            nextTick(() => {
                codeInputs.value[0]?.focus()
            })
        },
    })
}

const submitRecovery = () => {
    if (! recoveryForm.recovery_code.trim() || recoveryForm.processing) {
        return
    }

    recoveryForm.post(route('two-factor.login.recovery'), {
        preserveScroll: true,
    })
}

const handleInput = (index: number, event: Event) => {
    const input = event.target as HTMLInputElement
    const value = input.value.replace(/\D/g, '').slice(-1)

    digits.value[index] = value
    input.value = value

    if (value && index < 5) {
        codeInputs.value[index + 1]?.focus()
    }

    if (completeCode.value.length === 6) {
        submit()
    }
}

const handleKeydown = (index: number, event: KeyboardEvent) => {
    if (event.key === 'Backspace' && ! digits.value[index] && index > 0) {
        digits.value[index - 1] = ''
        codeInputs.value[index - 1]?.focus()
    }

    if (event.key === 'ArrowLeft' && index > 0) {
        codeInputs.value[index - 1]?.focus()
    }

    if (event.key === 'ArrowRight' && index < 5) {
        codeInputs.value[index + 1]?.focus()
    }
}

const handlePaste = (event: ClipboardEvent) => {
    const pasted = event.clipboardData
        ?.getData('text')
        .replace(/\D/g, '')
        .slice(0, 6)

    if (! pasted) {
        return
    }

    event.preventDefault()

    digits.value = Array.from(
        { length: 6 },
        (_, index) => pasted[index] ?? '',
    )

    if (pasted.length === 6) {
        nextTick(() => submit())
    } else {
        nextTick(() => {
            codeInputs.value[pasted.length]?.focus()
        })
    }
}

const showRecovery = () => {
    recoveryMode.value = true
    form.clearErrors()

    nextTick(() => {
        document.getElementById('recovery_code')?.focus()
    })
}

const showAuthenticator = () => {
    recoveryMode.value = false
    recoveryForm.clearErrors()

    nextTick(() => {
        codeInputs.value[0]?.focus()
    })
}
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="relative min-h-screen overflow-hidden bg-surface-dark text-white">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,#ffc40014,transparent_32%),radial-gradient(circle_at_bottom_right,#ff8a000d,transparent_35%)]"
        />

        <main class="relative z-10 flex min-h-screen justify-center px-4 py-12 sm:items-center sm:py-16">
            <div class="w-full max-w-[500px]">
                <div class="mb-7 flex justify-center">
                    <img
                        src="https://hivepanel.dev/assets/imgs/HivePanelLogo.png"
                        alt="HivePanel"
                        class="h-14 w-auto"
                    />
                </div>

                <div
                    class="overflow-hidden rounded-panel border border-zinc-800/90 bg-surface/95 shadow-[0_30px_100px_rgba(0,0,0,0.5)] backdrop-blur"
                >
                    <div class="border-b border-zinc-800/80 px-7 pb-7 pt-8 text-center sm:px-9">
                        <div
                            class="mx-auto flex size-12 items-center justify-center rounded-xl border border-hive/20 bg-hive/[0.08] shadow-[0_0_30px_rgba(255,153,0,0.04)]"
                        >
                            <ShieldCheck class="size-5 text-hive" />
                        </div>

                        <h1 class="mt-5 text-2xl font-black tracking-tight text-white">
                            {{ recoveryMode ? 'Recovery code' : 'Two-factor authentication' }}
                        </h1>

                        <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-zinc-500">
                            {{
                                recoveryMode
                                    ? 'Enter one of the recovery codes you saved when you enabled two-factor authentication.'
                                    : 'Enter the 6-digit code from your authenticator app to finish signing in.'
                            }}
                        </p>
                    </div>

                    <div class="px-7 py-7 sm:px-9 sm:py-8">
                        <form
                            v-if="! recoveryMode"
                            class="space-y-6"
                            @submit.prevent="submit"
                        >
                            <div>
                                <label
                                    class="mb-3 block text-xs font-black uppercase tracking-[0.08em] text-zinc-500"
                                >
                                    Authentication code
                                </label>

                                <div
                                    class="grid grid-cols-6 gap-2 sm:gap-2.5"
                                    @paste="handlePaste"
                                >
                                    <input
                                        v-for="(_, index) in digits"
                                        :key="index"
                                        :ref="el => {
                                            if (el) {
                                                codeInputs[index] = el as HTMLInputElement
                                            }
                                        }"
                                        v-model="digits[index]"
                                        type="text"
                                        inputmode="numeric"
                                        :autocomplete="index === 0 ? 'one-time-code' : 'off'"
                                        maxlength="1"
                                        :autofocus="index === 0"
                                        :aria-label="`Authentication code digit ${index + 1}`"
                                        class="h-14 min-w-0 w-full rounded-xl border border-white/[0.08] bg-black/20 text-center text-xl font-black text-white outline-none transition duration-150 hover:border-white/[0.13] focus:border-hive/60 focus:bg-hive/[0.025] focus:ring-4 focus:ring-hive/[0.08]"
                                        @input="handleInput(index, $event)"
                                        @keydown="handleKeydown(index, $event)"
                                    />
                                </div>

                                <InputError
                                    class="mt-3 text-center"
                                    :message="form.errors.code"
                                />
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing || completeCode.length !== 6"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 text-sm font-black text-black shadow-[0_8px_25px_rgba(255,153,0,0.08)] transition hover:bg-hive-light disabled:cursor-not-allowed disabled:border-zinc-800 disabled:bg-zinc-900 disabled:text-zinc-600 disabled:shadow-none"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="size-4 animate-spin"
                                />

                                {{ form.processing ? 'Verifying...' : 'Verify and continue' }}
                            </button>

                            <div class="flex items-center gap-4">
                                <div class="h-px flex-1 bg-zinc-800/70" />
                                <span class="text-[10px] font-black uppercase tracking-wider text-zinc-700">
                                    Or
                                </span>
                                <div class="h-px flex-1 bg-zinc-800/70" />
                            </div>

                            <button
                                type="button"
                                class="group flex h-11 w-full items-center justify-center gap-2 rounded-button border border-zinc-800 bg-black/10 text-xs font-bold text-zinc-500 transition hover:border-hive/20 hover:bg-hive/[0.025] hover:text-zinc-300"
                                @click="showRecovery"
                            >
                                <KeyRound class="size-3.5 transition group-hover:text-hive" />
                                Use a recovery code instead
                            </button>
                        </form>

                        <form
                            v-else
                            class="space-y-6"
                            @submit.prevent="submitRecovery"
                        >
                            <div>
                                <label
                                    for="recovery_code"
                                    class="mb-2 block text-xs font-black uppercase tracking-[0.08em] text-zinc-500"
                                >
                                    Recovery code
                                </label>

                                <input
                                    id="recovery_code"
                                    v-model="recoveryForm.recovery_code"
                                    type="text"
                                    required
                                    autofocus
                                    autocomplete="off"
                                    spellcheck="false"
                                    placeholder="xxxxx-xxxxx"
                                    class="block h-12 w-full rounded-button border border-white/[0.08] bg-black/20 px-4 text-center font-mono text-sm font-bold tracking-[0.12em] text-white outline-none transition placeholder:text-zinc-700 hover:border-white/[0.13] focus:border-hive/60 focus:bg-hive/[0.025] focus:ring-4 focus:ring-hive/[0.08]"
                                />

                                <InputError
                                    class="mt-3 text-center"
                                    :message="recoveryForm.errors.recovery_code"
                                />
                            </div>

                            <button
                                type="submit"
                                :disabled="recoveryForm.processing || ! recoveryForm.recovery_code.trim()"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 text-sm font-black text-black shadow-[0_8px_25px_rgba(255,153,0,0.08)] transition hover:bg-hive-light disabled:cursor-not-allowed disabled:border-zinc-800 disabled:bg-zinc-900 disabled:text-zinc-600 disabled:shadow-none"
                            >
                                <LoaderCircle
                                    v-if="recoveryForm.processing"
                                    class="size-4 animate-spin"
                                />

                                {{ recoveryForm.processing ? 'Verifying...' : 'Use recovery code' }}
                            </button>

                            <button
                                type="button"
                                class="group flex h-11 w-full items-center justify-center gap-2 rounded-button border border-zinc-800 bg-black/10 text-xs font-bold text-zinc-500 transition hover:border-hive/20 hover:bg-hive/[0.025] hover:text-zinc-300"
                                @click="showAuthenticator"
                            >
                                <ArrowLeft class="size-3.5 transition group-hover:text-hive" />
                                Back to authenticator code
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-center gap-2 text-xs font-bold text-zinc-700">
                    <ShieldCheck class="size-3.5" />
                    <span>Secure account verification</span>
                </div>
            </div>
        </main>
    </div>
</template>