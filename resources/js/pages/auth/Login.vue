<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import TextLink from '@/components/TextLink.vue'
import { Head, useForm } from '@inertiajs/vue3'
import {
    ArrowRight,
    Eye,
    EyeOff,
    Fingerprint,
    Github,
    KeyRound,
    LoaderCircle,
    LockKeyhole,
    Mail,
    ShieldCheck,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { Passkeys } from '@laravel/passkeys'

interface OAuthProvider {
    provider: string
    name?: string
}

interface OidcProvider {
    name: string
    slug: string
}

const props = withDefaults(defineProps<{
    status?: string
    canResetPassword: boolean
    oauthProviders: OAuthProvider[]
    oidcProviders?: OidcProvider[]
    passkeysEnabled?: boolean
}>(), {
    oidcProviders: () => [],
    passkeysEnabled: false,
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const showPassword = ref(false)

const hasAlternativeMethods = computed(() => {
    return props.passkeysEnabled
        || props.oauthProviders.length > 0
        || props.oidcProviders.length > 0
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}

const providerName = (provider: OAuthProvider) => {
    if (provider.name) {
        return provider.name
    }

    return provider.provider.charAt(0).toUpperCase() + provider.provider.slice(1)
}

const providerInitial = (provider: OAuthProvider) => {
    return providerName(provider).charAt(0).toUpperCase()
}

const passkeyProcessing = ref(false)
const passkeyError = ref<string | null>(null)

const signInWithPasskey = async () => {
    if (passkeyProcessing.value) {
        return
    }

    passkeyProcessing.value = true
    passkeyError.value = null

    try {
        await Passkeys.verify()

        window.location.href = route('dashboard')
    } catch (error) {
        if (error instanceof DOMException && error.name === 'NotAllowedError') {
            passkeyError.value = 'Passkey sign-in was cancelled.'
        } else if (error instanceof Error) {
            passkeyError.value = error.message
        } else {
            passkeyError.value = 'Unable to sign in with your passkey.'
        }
    } finally {
        passkeyProcessing.value = false
    }
}
</script>

<template>
    <Head title="Log in" />

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
            <div class="w-full max-w-[430px]">
                <div class="mb-8 text-center">
                    <img
                        src="https://hivepanel.dev/assets/imgs/HivePanelLogo.png"
                        alt="HivePanel"
                        class="mx-auto h-12 w-auto drop-shadow-[0_0_28px_rgba(255,166,0,0.12)]"
                    />

                    <h1 class="mt-7 text-[26px] font-semibold tracking-[-0.025em] text-white">
                        Welcome back
                    </h1>

                    <p class="mt-2 text-sm leading-6 text-zinc-500">
                        Sign in to manage your servers and infrastructure.
                    </p>
                </div>

                <div
                    class="relative overflow-hidden rounded-2xl border border-white/[0.07] bg-[#15181c]/95 shadow-[0_32px_100px_rgba(0,0,0,0.45)] backdrop-blur-xl"
                >
                    <div
                        class="pointer-events-none absolute inset-x-12 top-0 h-px bg-gradient-to-r from-transparent via-white/[0.14] to-transparent"
                    />

                    <div class="p-6 sm:p-7">
                        <div
                            v-if="status"
                            class="mb-6 flex items-start gap-3 rounded-xl border border-status-success/20 bg-status-success/[0.07] px-4 py-3.5 text-sm text-status-success"
                        >
                            <ShieldCheck class="mt-0.5 size-4 shrink-0" />
                            <span>{{ status }}</span>
                        </div>

                        <button
                            v-if="props.passkeysEnabled"
                            type="button"
                            :disabled="passkeyProcessing"
                            class="group mb-5 flex w-full items-center gap-3 rounded-xl border border-hive/20 bg-hive/[0.07] px-4 py-3.5 text-left transition duration-200 hover:border-hive/35 hover:bg-hive/[0.11] disabled:cursor-wait disabled:opacity-60"
                            @click="signInWithPasskey"
                        >
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-hive/15 bg-hive/10">
                                <LoaderCircle
                                    v-if="passkeyProcessing"
                                    class="size-[18px] animate-spin text-hive"
                                />

                                <Fingerprint
                                    v-else
                                    class="size-[18px] text-hive"
                                />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-white">
                                    {{ passkeyProcessing ? 'Waiting for your passkey...' : 'Sign in with a passkey' }}
                                </span>

                                <span class="mt-0.5 block text-xs text-zinc-500">
                                    Windows Hello, Touch ID or security key
                                </span>
                            </span>

                            <ArrowRight
                                v-if="!passkeyProcessing"
                                class="size-4 text-zinc-600 transition group-hover:translate-x-0.5 group-hover:text-hive"
                            />
                        </button>

                        <div
                            v-if="passkeyError"
                            class="-mt-2 mb-5 rounded-lg border border-status-danger/20 bg-status-danger/[0.06] px-3.5 py-2.5 text-xs font-medium text-status-danger"
                        >
                            {{ passkeyError }}
                        </div>

                        <div
                            v-if="props.passkeysEnabled"
                            class="mb-5 flex items-center gap-3"
                        >
                            <div class="h-px flex-1 bg-white/[0.06]" />

                            <span class="text-[11px] font-medium text-zinc-600">
                                or use your password
                            </span>

                            <div class="h-px flex-1 bg-white/[0.06]" />
                        </div>

                        <form @submit.prevent="submit">
                            <div>
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
                                        autofocus
                                        tabindex="1"
                                        autocomplete="email"
                                        placeholder="you@example.com"
                                        class="block w-full rounded-xl border border-white/[0.07] bg-[#0d1013] py-3 pl-10 pr-3.5 text-sm text-white outline-none transition duration-200 placeholder:text-zinc-700 hover:border-white/[0.11] focus:border-hive/50 focus:bg-[#0f1215] focus:ring-4 focus:ring-hive/[0.06]"
                                    />
                                </div>

                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div class="mt-5">
                                <div class="mb-2 flex items-center justify-between gap-4">
                                    <label
                                        for="password"
                                        class="text-sm font-medium text-zinc-300"
                                    >
                                        Password
                                    </label>

                                    <TextLink
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        tabindex="5"
                                        class="text-xs font-medium text-zinc-500 transition hover:text-hive"
                                    >
                                        Forgot password?
                                    </TextLink>
                                </div>

                                <div class="group relative">
                                    <LockKeyhole
                                        class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-600 transition group-focus-within:text-hive"
                                    />

                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        tabindex="2"
                                        autocomplete="current-password"
                                        placeholder="Enter your password"
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

                            <div class="mt-4 flex items-center">
                                <label
                                    for="remember"
                                    class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-500"
                                >
                                    <input
                                        id="remember"
                                        v-model="form.remember"
                                        type="checkbox"
                                        tabindex="3"
                                        class="size-4 rounded border-zinc-700 bg-[#0d1013] text-hive focus:ring-hive/40 focus:ring-offset-0"
                                    />

                                    <span>Keep me signed in</span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                tabindex="4"
                                :disabled="form.processing"
                                class="group mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-hive px-4 py-3 text-sm font-semibold text-[#121212] shadow-[0_8px_30px_rgba(255,166,0,0.08)] transition duration-200 hover:bg-hive-light hover:shadow-[0_8px_35px_rgba(255,166,0,0.14)] focus:outline-none focus:ring-4 focus:ring-hive/15 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="size-4 animate-spin"
                                />

                                <span>
                                    {{ form.processing ? 'Signing in...' : 'Sign in' }}
                                </span>

                                <ArrowRight
                                    v-if="!form.processing"
                                    class="size-4 transition group-hover:translate-x-0.5"
                                />
                            </button>
                        </form>

                        <template v-if="hasAlternativeMethods && (props.oauthProviders.length || props.oidcProviders.length)">
                            <div class="my-6 flex items-center gap-3">
                                <div class="h-px flex-1 bg-white/[0.06]" />

                                <span class="text-[11px] font-medium text-zinc-600">
                                    other sign-in options
                                </span>

                                <div class="h-px flex-1 bg-white/[0.06]" />
                            </div>

                            <div
                                v-if="props.oauthProviders.length"
                                class="grid gap-2.5"
                                :class="props.oauthProviders.length > 1 ? 'grid-cols-2' : 'grid-cols-1'"
                            >
                                <a
                                    v-for="provider in props.oauthProviders"
                                    :key="provider.provider"
                                    :href="route('social.redirect', provider.provider)"
                                    class="group flex min-w-0 items-center justify-center gap-2.5 rounded-xl border border-white/[0.07] bg-white/[0.025] px-3.5 py-3 text-sm font-medium text-zinc-300 transition duration-200 hover:border-white/[0.12] hover:bg-white/[0.045] hover:text-white"
                                >
                                    <Github
                                        v-if="provider.provider === 'github'"
                                        class="size-4 shrink-0 text-zinc-400 group-hover:text-white"
                                    />

                                    <span
                                        v-else
                                        class="flex size-5 shrink-0 items-center justify-center rounded-md bg-white/[0.06] text-[10px] font-semibold text-zinc-400"
                                    >
                                        {{ providerInitial(provider) }}
                                    </span>

                                    <span class="truncate">
                                        {{ providerName(provider) }}
                                    </span>
                                </a>
                            </div>

                            <div
                                v-if="props.oidcProviders.length"
                                class="mt-3 space-y-2.5"
                            >
                                <a
                                    v-for="provider in props.oidcProviders"
                                    :key="provider.slug"
                                    :href="route('oidc.redirect', provider.slug)"
                                    class="group flex w-full items-center gap-3 rounded-xl border border-white/[0.07] bg-white/[0.025] px-4 py-3 text-left transition duration-200 hover:border-hive/20 hover:bg-hive/[0.035]"
                                >
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.04]">
                                        <KeyRound class="size-4 text-zinc-500 transition group-hover:text-hive" />
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-zinc-300 transition group-hover:text-white">
                                            Continue with {{ provider.name }}
                                        </span>

                                        <span class="mt-0.5 block text-[11px] text-zinc-600">
                                            Organisation sign-in
                                        </span>
                                    </span>

                                    <ArrowRight class="size-4 shrink-0 text-zinc-700 transition group-hover:translate-x-0.5 group-hover:text-hive" />
                                </a>
                            </div>
                        </template>
                    </div>

                    <div class="border-t border-white/[0.05] bg-black/[0.08] px-6 py-3.5">
                        <div class="flex items-center justify-center gap-1.5 text-[11px] text-zinc-600">
                            <ShieldCheck class="size-3.5" />
                            <span>Secure authentication powered by HivePanel</span>
                        </div>
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