<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import TextLink from '@/components/TextLink.vue'
import { Head, useForm } from '@inertiajs/vue3'
import {
    ArrowRight,
    Check,
    Eye,
    EyeOff,
    LoaderCircle,
    LockKeyhole,
    Mail,
    ShieldCheck,
    User,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

const props = withDefaults(defineProps<{
    passwordMinLength?: number
    passkeysEnabled?: boolean
}>(), {
    passwordMinLength: 8,
    passkeysEnabled: false,
})

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const passwordRequirements = computed(() => [
    {
        label: `At least ${props.passwordMinLength} characters`,
        valid: form.password.length >= props.passwordMinLength,
    },
    {
        label: 'Passwords match',
        valid: form.password.length > 0 && form.password === form.password_confirmation,
    },
])

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Create account" />

    <div class="relative min-h-screen overflow-hidden bg-[#090b0d] text-white">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_-10%,rgba(255,166,0,0.13),transparent_34%),radial-gradient(circle_at_15%_80%,rgba(255,140,0,0.035),transparent_25%),radial-gradient(circle_at_85%_70%,rgba(255,190,70,0.025),transparent_25%)]"
        />

        <div
            class="pointer-events-none absolute inset-0 opacity-[0.018] [background-image:linear-gradient(rgba(255,255,255,0.8)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.8)_1px,transparent_1px)] [background-size:48px_48px]"
        />

        <div
            class="pointer-events-none absolute left-1/2 top-0 h-px w-[520px] -translate-x-1/2 bg-gradient-to-r from-transparent via-hive/60 to-transparent"
        />

        <main class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-[460px]">
                <div class="mb-8 text-center">
                    <img
                        src="https://hivepanel.dev/assets/imgs/HivePanelLogo.png"
                        alt="HivePanel"
                        class="mx-auto h-12 w-auto drop-shadow-[0_0_28px_rgba(255,166,0,0.12)]"
                    />

                    <h1 class="mt-7 text-[26px] font-semibold tracking-[-0.025em] text-white">
                        Create your account
                    </h1>

                    <p class="mt-2 text-sm leading-6 text-zinc-500">
                        Get started with your HivePanel account.
                    </p>
                </div>

                <div
                    class="relative overflow-hidden rounded-2xl border border-white/[0.07] bg-[#15181c]/95 shadow-[0_32px_100px_rgba(0,0,0,0.45)] backdrop-blur-xl"
                >
                    <div
                        class="pointer-events-none absolute inset-x-12 top-0 h-px bg-gradient-to-r from-transparent via-white/[0.14] to-transparent"
                    />

                    <div class="p-6 sm:p-7">
                        <form @submit.prevent="submit">
                            <div>
                                <label
                                    for="name"
                                    class="mb-2 block text-sm font-medium text-zinc-300"
                                >
                                    Your name
                                </label>

                                <div class="group relative">
                                    <User
                                        class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-600 transition group-focus-within:text-hive"
                                    />

                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        autofocus
                                        tabindex="1"
                                        autocomplete="name"
                                        placeholder="Your name"
                                        class="block w-full rounded-xl border border-white/[0.07] bg-[#0d1013] py-3 pl-10 pr-3.5 text-sm text-white outline-none transition duration-200 placeholder:text-zinc-700 hover:border-white/[0.11] focus:border-hive/50 focus:bg-[#0f1215] focus:ring-4 focus:ring-hive/[0.06]"
                                    />
                                </div>

                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div class="mt-5">
                                <label
                                    for="email"
                                    class="mb-2 block text-sm font-medium text-zinc-300"
                                >
                                    Email address
                                </label>

                                <div class="group relative">
                                    <Mail
                                        class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-600 transition group-focus-within:text-hive"
                                    />

                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        tabindex="2"
                                        autocomplete="email"
                                        placeholder="you@example.com"
                                        class="block w-full rounded-xl border border-white/[0.07] bg-[#0d1013] py-3 pl-10 pr-3.5 text-sm text-white outline-none transition duration-200 placeholder:text-zinc-700 hover:border-white/[0.11] focus:border-hive/50 focus:bg-[#0f1215] focus:ring-4 focus:ring-hive/[0.06]"
                                    />
                                </div>

                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div class="mt-5">
                                <label
                                    for="password"
                                    class="mb-2 block text-sm font-medium text-zinc-300"
                                >
                                    Password
                                </label>

                                <div class="group relative">
                                    <LockKeyhole
                                        class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-600 transition group-focus-within:text-hive"
                                    />

                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        tabindex="3"
                                        autocomplete="new-password"
                                        placeholder="Create a password"
                                        class="block w-full rounded-xl border border-white/[0.07] bg-[#0d1013] py-3 pl-10 pr-11 text-sm text-white outline-none transition duration-200 placeholder:text-zinc-700 hover:border-white/[0.11] focus:border-hive/50 focus:bg-[#0f1215] focus:ring-4 focus:ring-hive/[0.06]"
                                    />

                                    <button
                                        type="button"
                                        tabindex="-1"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        class="absolute right-2 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-zinc-600 transition hover:bg-white/[0.04] hover:text-zinc-300"
                                        @click="showPassword = !showPassword"
                                    >
                                        <EyeOff v-if="showPassword" class="size-4" />
                                        <Eye v-else class="size-4" />
                                    </button>
                                </div>

                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>

                            <div class="mt-5">
                                <label
                                    for="password_confirmation"
                                    class="mb-2 block text-sm font-medium text-zinc-300"
                                >
                                    Confirm password
                                </label>

                                <div class="group relative">
                                    <LockKeyhole
                                        class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-600 transition group-focus-within:text-hive"
                                    />

                                    <input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        :type="showPasswordConfirmation ? 'text' : 'password'"
                                        required
                                        tabindex="4"
                                        autocomplete="new-password"
                                        placeholder="Confirm your password"
                                        class="block w-full rounded-xl border border-white/[0.07] bg-[#0d1013] py-3 pl-10 pr-11 text-sm text-white outline-none transition duration-200 placeholder:text-zinc-700 hover:border-white/[0.11] focus:border-hive/50 focus:bg-[#0f1215] focus:ring-4 focus:ring-hive/[0.06]"
                                    />

                                    <button
                                        type="button"
                                        tabindex="-1"
                                        :aria-label="showPasswordConfirmation ? 'Hide password' : 'Show password'"
                                        class="absolute right-2 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-zinc-600 transition hover:bg-white/[0.04] hover:text-zinc-300"
                                        @click="showPasswordConfirmation = !showPasswordConfirmation"
                                    >
                                        <EyeOff v-if="showPasswordConfirmation" class="size-4" />
                                        <Eye v-else class="size-4" />
                                    </button>
                                </div>

                                <InputError class="mt-2" :message="form.errors.password_confirmation" />
                            </div>

                            <div
                                v-if="form.password.length"
                                class="mt-4 flex flex-wrap gap-x-4 gap-y-2"
                            >
                                <div
                                    v-for="requirement in passwordRequirements"
                                    :key="requirement.label"
                                    class="flex items-center gap-1.5 text-xs"
                                    :class="requirement.valid ? 'text-status-success' : 'text-zinc-600'"
                                >
                                    <span
                                        class="flex size-4 items-center justify-center rounded-full border"
                                        :class="requirement.valid
                                            ? 'border-status-success/30 bg-status-success/10'
                                            : 'border-white/[0.08] bg-white/[0.02]'"
                                    >
                                        <Check
                                            v-if="requirement.valid"
                                            class="size-2.5"
                                        />
                                    </span>

                                    {{ requirement.label }}
                                </div>
                            </div>

                            <button
                                type="submit"
                                tabindex="5"
                                :disabled="form.processing"
                                class="group mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-hive px-4 py-3 text-sm font-semibold text-[#121212] shadow-[0_8px_30px_rgba(255,166,0,0.08)] transition duration-200 hover:bg-hive-light hover:shadow-[0_8px_35px_rgba(255,166,0,0.14)] focus:outline-none focus:ring-4 focus:ring-hive/15 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="size-4 animate-spin"
                                />

                                <span>
                                    {{ form.processing ? 'Creating account...' : 'Create account' }}
                                </span>

                                <ArrowRight
                                    v-if="!form.processing"
                                    class="size-4 transition group-hover:translate-x-0.5"
                                />
                            </button>
                        </form>

                        <div
                            v-if="props.passkeysEnabled"
                            class="mt-5 flex items-start gap-3 rounded-xl border border-hive/10 bg-hive/[0.035] px-4 py-3.5"
                        >
                            <ShieldCheck class="mt-0.5 size-4 shrink-0 text-hive" />

                            <div>
                                <div class="text-xs font-medium text-zinc-300">
                                    Passkey ready
                                </div>

                                <p class="mt-1 text-xs leading-5 text-zinc-600">
                                    After creating your account, you can secure it with Windows Hello, Touch ID, Face ID or a security key.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-white/[0.05] bg-black/[0.08] px-6 py-4">
                        <p class="text-center text-sm text-zinc-500">
                            Already have an account?

                            <TextLink
                                :href="route('login')"
                                class="ml-1 font-medium text-hive no-underline transition hover:text-hive-light"
                                tabindex="6"
                            >
                                Sign in
                            </TextLink>
                        </p>
                    </div>
                </div>

                <div class="mt-7 flex items-center justify-center gap-2 text-[11px] text-zinc-700">
                    <span>HivePanel</span>
                    <span class="size-0.5 rounded-full bg-zinc-700" />
                    <span>Game server management, rebuilt.</span>
                </div>
            </div>
        </main>
    </div>
</template>