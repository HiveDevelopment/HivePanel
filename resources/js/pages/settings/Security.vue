<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import InputError from '@/components/InputError.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import { type BreadcrumbItem } from '@/types'
import { Passkeys } from '@laravel/passkeys'
import { Head, router, useForm } from '@inertiajs/vue3'
import {
    Check,
    Clipboard,
    Download,
    Fingerprint,
    KeyRound,
    LoaderCircle,
    LockKeyhole,
    Plus,
    RefreshCw,
    ShieldCheck,
    Trash2,
    X,
} from 'lucide-vue-next'
import { computed, nextTick, onMounted, ref } from 'vue'

interface Passkey {
    id: number | string
    name: string
    created_at: string | null
    last_used_at: string | null
}

interface TwoFactor {
    enabled: boolean
    confirmed_at: string | null
    required: boolean
}

const props = defineProps<{
    passkeysEnabled: boolean
    passwordMinLength: number
    passkeys: Passkey[]
    twoFactor: TwoFactor
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security settings',
        href: '/settings/security',
    },
]

const passwordInput = ref<HTMLInputElement>()
const currentPasswordInput = ref<HTMLInputElement>()

const passkeyName = ref('')
const passkeyProcessing = ref(false)
const passkeyError = ref<string | null>(null)
const passkeyCreated = ref(false)
const removingPasskey = ref<number | string | null>(null)
const passkeyPendingRemoval = ref<Passkey | null>(null)

const twoFactorEnabled = ref(props.twoFactor.enabled)
const twoFactorSetupOpen = ref(false)
const twoFactorSetupStep = ref<'setup' | 'recovery'>('setup')
const twoFactorProcessing = ref(false)
const twoFactorSecret = ref('')
const twoFactorQrCode = ref('')
const twoFactorCode = ref('')
const twoFactorError = ref<string | null>(null)
const recoveryCodes = ref<string[]>([])
const disableTwoFactorOpen = ref(false)
const regenerateRecoveryOpen = ref(false)
const copiedRecoveryCodes = ref(false)
const copiedSecret = ref(false)

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const suggestedPasskeyName = computed(() => {
    const userAgent = navigator.userAgent.toLowerCase()

    if (userAgent.includes('windows')) {
        return 'Windows Hello'
    }

    if (userAgent.includes('iphone')) {
        return 'iPhone'
    }

    if (userAgent.includes('ipad')) {
        return 'iPad'
    }

    if (userAgent.includes('mac')) {
        return 'Mac'
    }

    if (userAgent.includes('android')) {
        return 'Android device'
    }

    return 'My passkey'
})

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,

        onSuccess: () => {
            passwordForm.reset()
        },

        onError: (errors) => {
            if (errors.password) {
                passwordForm.reset('password', 'password_confirmation')
                passwordInput.value?.focus()
            }

            if (errors.current_password) {
                passwordForm.reset('current_password')
                currentPasswordInput.value?.focus()
            }
        },
    })
}

const requestPasswordConfirmation = (action: 'add-passkey' | 'enable-2fa' | 'disable-2fa' | 'recovery-codes') => {
    const params = new URLSearchParams({
        action,
    })

    const returnTo = `/settings/security?${params.toString()}`

    window.location.href = `${route('password.confirm')}?return=${encodeURIComponent(returnTo)}`
}

const requiresPasswordConfirmation = (error: unknown): boolean => {
    if (! (error instanceof Error)) {
        return false
    }

    const message = error.message.toLowerCase()

    return message.includes('password confirmation')
        || message.includes('password.confirm')
        || message.includes('confirm your password')
        || message.includes('419')
}

const addPasskey = async () => {
    if (passkeyProcessing.value) {
        return
    }

    passkeyProcessing.value = true
    passkeyError.value = null
    passkeyCreated.value = false

    const name = passkeyName.value.trim() || suggestedPasskeyName.value

    try {
        await Passkeys.register({
            name,
        })

        passkeyName.value = ''
        passkeyCreated.value = true

        sessionStorage.removeItem('hivepanel.pendingPasskeyName')

        router.reload({
            only: ['passkeys'],
            preserveScroll: true,
        })
    } catch (error) {
        if (requiresPasswordConfirmation(error)) {
            sessionStorage.setItem('hivepanel.pendingPasskeyName', name)
            requestPasswordConfirmation('add-passkey')

            return
        }

        if (
            error instanceof DOMException
            && error.name === 'NotAllowedError'
        ) {
            passkeyError.value = 'Passkey setup was cancelled.'
        } else if (error instanceof Error) {
            passkeyError.value = error.message
        } else {
            passkeyError.value = 'Unable to create your passkey.'
        }
    } finally {
        passkeyProcessing.value = false
    }
}

const requestPasskeyRemoval = (passkey: Passkey) => {
    if (removingPasskey.value !== null) {
        return
    }

    passkeyError.value = null
    passkeyPendingRemoval.value = passkey
}

const cancelPasskeyRemoval = () => {
    if (removingPasskey.value !== null) {
        return
    }

    passkeyPendingRemoval.value = null
}

const confirmPasskeyRemoval = () => {
    const passkey = passkeyPendingRemoval.value

    if (! passkey || removingPasskey.value !== null) {
        return
    }

    removingPasskey.value = passkey.id
    passkeyError.value = null

    router.delete(route('passkey.destroy', passkey.id), {
        preserveScroll: true,

        onSuccess: () => {
            passkeyPendingRemoval.value = null
        },

        onError: () => {
            passkeyError.value = 'Password confirmation may be required before removing a passkey.'
        },

        onFinish: () => {
            removingPasskey.value = null
        },
    })
}

const beginTwoFactorSetup = async () => {
    if (twoFactorProcessing.value) {
        return
    }

    twoFactorProcessing.value = true
    twoFactorError.value = null
    twoFactorCode.value = ''

    try {
        const response = await fetch(route('two-factor.enable'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })

        if (response.status === 423 || response.redirected) {
            requestPasswordConfirmation('enable-2fa')
            return
        }

        const data = await response.json()

        if (! response.ok) {
            if (
                response.status === 409
                || response.status === 403
            ) {
                requestPasswordConfirmation('enable-2fa')
                return
            }

            throw new Error(data.message || 'Unable to start two-factor authentication setup.')
        }

        twoFactorSecret.value = data.secret
        twoFactorQrCode.value = data.qr_code
        twoFactorSetupStep.value = 'setup'
        twoFactorSetupOpen.value = true
    } catch (error) {
        twoFactorError.value = error instanceof Error
            ? error.message
            : 'Unable to start two-factor authentication setup.'
    } finally {
        twoFactorProcessing.value = false
    }
}

const confirmTwoFactorSetup = async () => {
    if (
        twoFactorProcessing.value
        || ! /^\d{6}$/.test(twoFactorCode.value)
    ) {
        return
    }

    twoFactorProcessing.value = true
    twoFactorError.value = null

    try {
        const response = await fetch(route('two-factor.confirm'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                code: twoFactorCode.value,
            }),
        })

        const data = await response.json()

        if (! response.ok) {
            twoFactorError.value = data.errors?.code?.[0]
                || data.message
                || 'The authentication code is invalid.'

            return
        }

        recoveryCodes.value = data.recovery_codes ?? []
        twoFactorEnabled.value = true
        twoFactorSetupStep.value = 'recovery'
        twoFactorCode.value = ''

        router.reload({
            only: ['twoFactor'],
            preserveScroll: true,
        })
    } catch {
        twoFactorError.value = 'Unable to confirm two-factor authentication.'
    } finally {
        twoFactorProcessing.value = false
    }
}

const closeTwoFactorSetup = () => {
    if (twoFactorProcessing.value) {
        return
    }

    if (
        twoFactorSetupStep.value === 'recovery'
        && recoveryCodes.value.length
    ) {
        recoveryCodes.value = []
    }

    twoFactorSetupOpen.value = false
    twoFactorSetupStep.value = 'setup'
    twoFactorSecret.value = ''
    twoFactorQrCode.value = ''
    twoFactorCode.value = ''
    twoFactorError.value = null
    copiedRecoveryCodes.value = false
    copiedSecret.value = false
}

const requestDisableTwoFactor = () => {
    disableTwoFactorOpen.value = true
}

const cancelDisableTwoFactor = () => {
    if (twoFactorProcessing.value) {
        return
    }

    disableTwoFactorOpen.value = false
}

const disableTwoFactor = () => {
    if (twoFactorProcessing.value) {
        return
    }

    twoFactorProcessing.value = true

    router.delete(route('two-factor.disable'), {
        preserveScroll: true,

        onSuccess: () => {
            twoFactorEnabled.value = false
            disableTwoFactorOpen.value = false
        },

        onError: () => {
            disableTwoFactorOpen.value = false
            requestPasswordConfirmation('disable-2fa')
        },

        onFinish: () => {
            twoFactorProcessing.value = false
        },
    })
}

const requestRegenerateRecoveryCodes = () => {
    regenerateRecoveryOpen.value = true
}

const cancelRegenerateRecoveryCodes = () => {
    if (twoFactorProcessing.value) {
        return
    }

    regenerateRecoveryOpen.value = false
}

const regenerateRecoveryCodes = async () => {
    if (twoFactorProcessing.value) {
        return
    }

    twoFactorProcessing.value = true
    twoFactorError.value = null

    try {
        const response = await fetch(route('two-factor.recovery-codes'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })

        if (response.status === 423 || response.redirected) {
            requestPasswordConfirmation('recovery-codes')
            return
        }

        const data = await response.json()

        if (! response.ok) {
            requestPasswordConfirmation('recovery-codes')
            return
        }

        recoveryCodes.value = data.recovery_codes ?? []
        regenerateRecoveryOpen.value = false
        twoFactorSetupStep.value = 'recovery'
        twoFactorSetupOpen.value = true
    } catch {
        twoFactorError.value = 'Unable to generate new recovery codes.'
    } finally {
        twoFactorProcessing.value = false
    }
}

const copyRecoveryCodes = async () => {
    if (! recoveryCodes.value.length) {
        return
    }

    await navigator.clipboard.writeText(recoveryCodes.value.join('\n'))

    copiedRecoveryCodes.value = true

    window.setTimeout(() => {
        copiedRecoveryCodes.value = false
    }, 2000)
}

const copySecret = async () => {
    if (! twoFactorSecret.value) {
        return
    }

    await navigator.clipboard.writeText(twoFactorSecret.value)

    copiedSecret.value = true

    window.setTimeout(() => {
        copiedSecret.value = false
    }, 2000)
}

const downloadRecoveryCodes = () => {
    if (! recoveryCodes.value.length) {
        return
    }

    const contents = [
        'HivePanel recovery codes',
        '',
        'Each recovery code can only be used once.',
        'Store these codes somewhere safe.',
        '',
        ...recoveryCodes.value,
    ].join('\n')

    const blob = new Blob([contents], {
        type: 'text/plain;charset=utf-8',
    })

    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')

    link.href = url
    link.download = 'hivepanel-recovery-codes.txt'

    document.body.appendChild(link)
    link.click()
    link.remove()

    URL.revokeObjectURL(url)
}

const formatDate = (value: string | null) => {
    if (! value) {
        return 'Never'
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value))
}

const formatLastUsed = (value: string | null) => {
    if (! value) {
        return 'Never used'
    }

    const date = new Date(value)
    const now = new Date()

    if (date.toDateString() === now.toDateString()) {
        return 'Used today'
    }

    return `Last used ${formatDate(value)}`
}

onMounted(async () => {
    const params = new URLSearchParams(window.location.search)
    const action = params.get('action')

    if (! action) {
        return
    }

    window.history.replaceState(
        {},
        '',
        '/settings/security',
    )

    await nextTick()

    if (action === 'add-passkey') {
        const pendingName = sessionStorage.getItem('hivepanel.pendingPasskeyName')

        if (pendingName) {
            passkeyName.value = pendingName
        }

        sessionStorage.removeItem('hivepanel.pendingPasskeyName')

        await addPasskey()
        return
    }

    if (action === 'enable-2fa') {
        await beginTwoFactorSetup()
        return
    }

    if (action === 'disable-2fa') {
        disableTwoFactorOpen.value = true
        return
    }

    if (action === 'recovery-codes') {
        await regenerateRecoveryCodes()
    }
})
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Security settings" />

        <SettingsLayout>
            <div class="space-y-5">
                <section class="overflow-hidden rounded-2xl border border-white/[0.07] bg-[#15181c]">
                    <div class="border-b border-white/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/[0.035]">
                                <LockKeyhole class="size-4 text-zinc-400" />
                            </div>

                            <div>
                                <h2 class="text-sm font-semibold text-zinc-100">
                                    Password
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-zinc-500">
                                    Change the password used to access your HivePanel account.
                                </p>
                            </div>
                        </div>
                    </div>

                    <form
                        class="space-y-5 p-5 sm:p-6"
                        @submit.prevent="updatePassword"
                    >
                        <div>
                            <label
                                for="current_password"
                                class="mb-2 block text-xs font-medium text-zinc-400"
                            >
                                Current password
                            </label>

                            <input
                                id="current_password"
                                ref="currentPasswordInput"
                                v-model="passwordForm.current_password"
                                type="password"
                                autocomplete="current-password"
                                placeholder="Enter your current password"
                                class="block w-full rounded-xl border border-white/[0.07] bg-[#0d1013] px-3.5 py-3 text-sm text-white outline-none transition placeholder:text-zinc-700 hover:border-white/[0.11] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                            />

                            <InputError
                                class="mt-2"
                                :message="passwordForm.errors.current_password"
                            />
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label
                                    for="password"
                                    class="mb-2 block text-xs font-medium text-zinc-400"
                                >
                                    New password
                                </label>

                                <input
                                    id="password"
                                    ref="passwordInput"
                                    v-model="passwordForm.password"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="`At least ${passwordMinLength} characters`"
                                    class="block w-full rounded-xl border border-white/[0.07] bg-[#0d1013] px-3.5 py-3 text-sm text-white outline-none transition placeholder:text-zinc-700 hover:border-white/[0.11] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                />

                                <InputError
                                    class="mt-2"
                                    :message="passwordForm.errors.password"
                                />
                            </div>

                            <div>
                                <label
                                    for="password_confirmation"
                                    class="mb-2 block text-xs font-medium text-zinc-400"
                                >
                                    Confirm new password
                                </label>

                                <input
                                    id="password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="Repeat your new password"
                                    class="block w-full rounded-xl border border-white/[0.07] bg-[#0d1013] px-3.5 py-3 text-sm text-white outline-none transition placeholder:text-zinc-700 hover:border-white/[0.11] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                />

                                <InputError
                                    class="mt-2"
                                    :message="passwordForm.errors.password_confirmation"
                                />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-white/[0.05] pt-5">
                            <div
                                v-if="passwordForm.recentlySuccessful"
                                class="mr-auto flex items-center gap-1.5 text-xs font-medium text-emerald-400"
                            >
                                <Check class="size-3.5" />
                                Password updated
                            </div>

                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light disabled:cursor-wait disabled:opacity-60"
                            >
                                <LoaderCircle
                                    v-if="passwordForm.processing"
                                    class="size-4 animate-spin"
                                />

                                Update password
                            </button>
                        </div>
                    </form>
                </section>

                <section class="overflow-hidden rounded-2xl border border-white/[0.07] bg-[#15181c]">
                    <div class="border-b border-white/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-hive/[0.07]">
                                    <ShieldCheck class="size-4 text-hive" />
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-semibold text-zinc-100">
                                            Two-factor authentication
                                        </h2>

                                        <span
                                            v-if="twoFactor.required"
                                            class="rounded-md border border-amber-500/20 bg-amber-500/[0.07] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-400"
                                        >
                                            Required
                                        </span>

                                        <span
                                            v-else
                                            class="rounded-md border border-hive/15 bg-hive/[0.06] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-hive"
                                        >
                                            Recommended
                                        </span>
                                    </div>

                                    <p class="mt-1 max-w-xl text-xs leading-5 text-zinc-500">
                                        Protect your account with a time-based code from an authenticator app.
                                    </p>
                                </div>
                            </div>

                            <span
                                :class="[
                                    'hidden rounded-lg border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider sm:inline-flex',
                                    twoFactorEnabled
                                        ? 'border-emerald-500/15 bg-emerald-500/[0.06] text-emerald-400'
                                        : 'border-white/[0.06] bg-white/[0.025] text-zinc-600',
                                ]"
                            >
                                {{ twoFactorEnabled ? 'Enabled' : 'Not configured' }}
                            </span>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6">
                        <div
                            v-if="twoFactorEnabled"
                            class="flex flex-col gap-5 sm:flex-row sm:items-center"
                        >
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-emerald-500/10 bg-emerald-500/[0.06]">
                                <Check class="size-5 text-emerald-400" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-zinc-200">
                                    Authenticator app configured
                                </div>

                                <p class="mt-1 text-xs leading-5 text-zinc-600">
                                    Your account will require an authentication code after signing in with your password or an external identity provider.
                                </p>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/[0.07] bg-white/[0.025] px-3.5 py-2.5 text-xs font-semibold text-zinc-400 transition hover:border-white/[0.12] hover:text-white"
                                    @click="requestRegenerateRecoveryCodes"
                                >
                                    <RefreshCw class="size-3.5" />
                                    Recovery codes
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-500/15 bg-red-500/[0.05] px-3.5 py-2.5 text-xs font-semibold text-red-400 transition hover:border-red-500/25 hover:bg-red-500/[0.08]"
                                    @click="requestDisableTwoFactor"
                                >
                                    Disable
                                </button>
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex flex-col gap-5 sm:flex-row sm:items-center"
                        >
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-white/[0.06] bg-white/[0.025]">
                                <ShieldCheck class="size-5 text-zinc-600" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-zinc-300">
                                    Authenticator app
                                </div>

                                <p class="mt-1 text-xs leading-5 text-zinc-600">
                                    Use an app such as Microsoft Authenticator, Google Authenticator, 1Password or another TOTP-compatible app.
                                </p>
                            </div>

                            <button
                                type="button"
                                :disabled="twoFactorProcessing"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-hive/20 bg-hive/[0.08] px-4 py-2.5 text-xs font-semibold text-hive transition hover:border-hive/30 hover:bg-hive/[0.12] disabled:opacity-50"
                                @click="beginTwoFactorSetup"
                            >
                                <LoaderCircle
                                    v-if="twoFactorProcessing"
                                    class="size-3.5 animate-spin"
                                />

                                <Plus
                                    v-else
                                    class="size-3.5"
                                />

                                Set up 2FA
                            </button>
                        </div>

                        <div
                            v-if="twoFactorError && !twoFactorSetupOpen"
                            class="mt-4 rounded-xl border border-red-500/15 bg-red-500/[0.06] px-4 py-3 text-xs font-medium text-red-400"
                        >
                            {{ twoFactorError }}
                        </div>
                    </div>
                </section>

                <section
                    v-if="passkeysEnabled"
                    class="overflow-hidden rounded-2xl border border-white/[0.07] bg-[#15181c]"
                >
                    <div class="border-b border-white/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-hive/[0.07]">
                                    <Fingerprint class="size-4 text-hive" />
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-semibold text-zinc-100">
                                            Passkeys
                                        </h2>

                                        <span class="rounded-md border border-hive/15 bg-hive/[0.06] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-hive">
                                            Recommended
                                        </span>
                                    </div>

                                    <p class="mt-1 max-w-xl text-xs leading-5 text-zinc-500">
                                        Sign in using Windows Hello, Touch ID, Face ID or a security key without entering your password.
                                    </p>
                                </div>
                            </div>

                            <div class="hidden text-right sm:block">
                                <div class="text-lg font-semibold text-zinc-200">
                                    {{ passkeys.length }}
                                </div>

                                <div class="text-[10px] uppercase tracking-wider text-zinc-600">
                                    {{ passkeys.length === 1 ? 'Passkey' : 'Passkeys' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6">
                        <div
                            v-if="passkeys.length"
                            class="mb-5 space-y-2"
                        >
                            <div
                                v-for="passkey in passkeys"
                                :key="passkey.id"
                                class="group flex items-center gap-4 rounded-xl border border-white/[0.06] bg-black/[0.12] px-4 py-3.5 transition hover:border-white/[0.1]"
                            >
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-white/[0.06] bg-white/[0.025]">
                                    <KeyRound class="size-4 text-zinc-400" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-zinc-200">
                                        {{ passkey.name }}
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-zinc-600">
                                        <span>
                                            Added {{ formatDate(passkey.created_at) }}
                                        </span>

                                        <span class="size-0.5 rounded-full bg-zinc-700" />

                                        <span>
                                            {{ formatLastUsed(passkey.last_used_at) }}
                                        </span>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    :disabled="removingPasskey !== null"
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg text-zinc-600 transition hover:bg-red-500/10 hover:text-red-400 disabled:opacity-40"
                                    title="Remove passkey"
                                    @click="requestPasskeyRemoval(passkey)"
                                >
                                    <LoaderCircle
                                        v-if="removingPasskey === passkey.id"
                                        class="size-3.5 animate-spin"
                                    />

                                    <Trash2
                                        v-else
                                        class="size-3.5"
                                    />
                                </button>
                            </div>
                        </div>

                        <div
                            v-else
                            class="mb-5 rounded-xl border border-dashed border-white/[0.08] bg-black/[0.08] px-5 py-7 text-center"
                        >
                            <Fingerprint class="mx-auto size-6 text-zinc-700" />

                            <p class="mt-3 text-sm font-medium text-zinc-400">
                                No passkeys added
                            </p>

                            <p class="mx-auto mt-1 max-w-sm text-xs leading-5 text-zinc-600">
                                Add a passkey to sign in quickly and securely using your device.
                            </p>
                        </div>

                        <div class="rounded-xl border border-white/[0.06] bg-black/[0.1] p-4">
                            <label
                                for="passkey-name"
                                class="mb-2 block text-xs font-medium text-zinc-400"
                            >
                                Add a passkey
                            </label>

                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input
                                    id="passkey-name"
                                    v-model="passkeyName"
                                    type="text"
                                    maxlength="100"
                                    :placeholder="suggestedPasskeyName"
                                    class="min-w-0 flex-1 rounded-xl border border-white/[0.07] bg-[#0d1013] px-3.5 py-2.5 text-sm text-white outline-none transition placeholder:text-zinc-700 hover:border-white/[0.11] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                    @keyup.enter="addPasskey"
                                />

                                <button
                                    type="button"
                                    :disabled="passkeyProcessing"
                                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-hive/20 bg-hive/[0.08] px-4 py-2.5 text-sm font-semibold text-hive transition hover:border-hive/30 hover:bg-hive/[0.12] disabled:cursor-wait disabled:opacity-50"
                                    @click="addPasskey"
                                >
                                    <LoaderCircle
                                        v-if="passkeyProcessing"
                                        class="size-4 animate-spin"
                                    />

                                    <Plus
                                        v-else
                                        class="size-4"
                                    />

                                    {{ passkeyProcessing ? 'Waiting...' : 'Add passkey' }}
                                </button>
                            </div>

                            <p class="mt-2 text-[11px] text-zinc-600">
                                Give it a name that identifies the device, such as Windows Hello or MacBook.
                            </p>
                        </div>

                        <div
                            v-if="passkeyError"
                            class="mt-4 rounded-xl border border-red-500/15 bg-red-500/[0.06] px-4 py-3 text-xs font-medium text-red-400"
                        >
                            {{ passkeyError }}
                        </div>

                        <div
                            v-if="passkeyCreated"
                            class="mt-4 flex items-center gap-2 rounded-xl border border-emerald-500/15 bg-emerald-500/[0.06] px-4 py-3 text-xs font-medium text-emerald-400"
                        >
                            <Check class="size-3.5" />
                            Passkey added successfully.
                        </div>
                    </div>
                </section>
            </div>
        </SettingsLayout>

        <div
            v-if="twoFactorSetupOpen"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-panel border border-zinc-800 bg-surface shadow-[0_25px_80px_rgba(0,0,0,0.55)]">
                <div class="flex items-center justify-between border-b border-zinc-800 px-6 py-5">
                    <div>
                        <h2 class="text-lg font-black text-white">
                            {{ twoFactorSetupStep === 'setup'
                                ? 'Set up two-factor authentication'
                                : 'Save your recovery codes'
                            }}
                        </h2>

                        <p class="mt-1 text-xs text-zinc-500">
                            {{ twoFactorSetupStep === 'setup'
                                ? 'Secure your account with an authenticator app.'
                                : 'Keep these somewhere safe. Each code can only be used once.'
                            }}
                        </p>
                    </div>

                    <button
                        type="button"
                        :disabled="twoFactorProcessing"
                        class="text-zinc-500 transition hover:text-white disabled:opacity-50"
                        @click="closeTwoFactorSetup"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div
                    v-if="twoFactorSetupStep === 'setup'"
                    class="px-6 py-6"
                >
                    <div class="flex justify-center">
                        <div
                            class="rounded-2xl bg-white p-4"
                            v-html="twoFactorQrCode"
                        />
                    </div>

                    <p class="mx-auto mt-5 max-w-sm text-center text-sm leading-6 text-zinc-400">
                        Scan the QR code with your authenticator app, then enter the 6-digit code it generates.
                    </p>

                    <div class="mt-5 rounded-xl border border-zinc-800 bg-[#0d0f11] p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-[10px] font-black uppercase tracking-wider text-zinc-600">
                                    Manual setup key
                                </div>

                                <div class="mt-1 truncate font-mono text-xs font-bold tracking-wider text-zinc-300">
                                    {{ twoFactorSecret }}
                                </div>
                            </div>

                            <button
                                type="button"
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-white/[0.04] hover:text-hive"
                                title="Copy setup key"
                                @click="copySecret"
                            >
                                <Check
                                    v-if="copiedSecret"
                                    class="size-3.5 text-emerald-400"
                                />

                                <Clipboard
                                    v-else
                                    class="size-3.5"
                                />
                            </button>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label
                            for="two-factor-code"
                            class="mb-2 block text-xs font-black uppercase tracking-wide text-zinc-500"
                        >
                            Authentication code
                        </label>

                        <input
                            id="two-factor-code"
                            v-model="twoFactorCode"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            placeholder="000000"
                            class="block w-full rounded-xl border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-center font-mono text-lg font-black tracking-[0.35em] text-white outline-none transition placeholder:text-zinc-700 focus:border-hive/60 focus:ring-4 focus:ring-hive/[0.06]"
                            @input="twoFactorCode = twoFactorCode.replace(/\D/g, '').slice(0, 6)"
                            @keyup.enter="confirmTwoFactorSetup"
                        />

                        <div
                            v-if="twoFactorError"
                            class="mt-3 rounded-xl border border-red-500/15 bg-red-500/[0.06] px-4 py-3 text-xs font-medium text-red-400"
                        >
                            {{ twoFactorError }}
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="px-6 py-6"
                >
                    <div class="mb-5 flex items-start gap-3 rounded-xl border border-amber-500/15 bg-amber-500/[0.05] px-4 py-3">
                        <KeyRound class="mt-0.5 size-4 shrink-0 text-amber-400" />

                        <p class="text-xs leading-5 text-amber-200/70">
                            These recovery codes are shown once. Save them somewhere secure before closing this window.
                        </p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="code in recoveryCodes"
                            :key="code"
                            class="rounded-lg border border-zinc-800 bg-[#0d0f11] px-3 py-2.5 text-center font-mono text-xs font-bold tracking-wider text-zinc-300"
                        >
                            {{ code }}
                        </div>
                    </div>

                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-800 bg-surface-light px-4 py-2.5 text-xs font-bold text-zinc-300 transition hover:text-white"
                            @click="copyRecoveryCodes"
                        >
                            <Check
                                v-if="copiedRecoveryCodes"
                                class="size-3.5 text-emerald-400"
                            />

                            <Clipboard
                                v-else
                                class="size-3.5"
                            />

                            {{ copiedRecoveryCodes ? 'Copied' : 'Copy all' }}
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-800 bg-surface-light px-4 py-2.5 text-xs font-bold text-zinc-300 transition hover:text-white"
                            @click="downloadRecoveryCodes"
                        >
                            <Download class="size-3.5" />
                            Download
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-800 px-6 py-4">
                    <button
                        v-if="twoFactorSetupStep === 'setup'"
                        type="button"
                        :disabled="twoFactorProcessing"
                        class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:text-white disabled:opacity-50"
                        @click="closeTwoFactorSetup"
                    >
                        Cancel
                    </button>

                    <button
                        v-if="twoFactorSetupStep === 'setup'"
                        type="button"
                        :disabled="twoFactorProcessing || twoFactorCode.length !== 6"
                        class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light disabled:opacity-50"
                        @click="confirmTwoFactorSetup"
                    >
                        <LoaderCircle
                            v-if="twoFactorProcessing"
                            class="size-4 animate-spin"
                        />

                        {{ twoFactorProcessing ? 'Verifying...' : 'Enable 2FA' }}
                    </button>

                    <button
                        v-else
                        type="button"
                        class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                        @click="closeTwoFactorSetup"
                    >
                        I've saved them
                    </button>
                </div>
            </div>
        </div>

        <ConfirmationModal
            :open="passkeyPendingRemoval !== null"
            title="Remove passkey?"
            :description="passkeyPendingRemoval
                ? `Are you sure you want to remove “${passkeyPendingRemoval.name}”? You will no longer be able to use this passkey to sign in to your account.`
                : undefined"
            confirm-text="Remove passkey"
            cancel-text="Keep passkey"
            danger
            :loading="removingPasskey !== null"
            @cancel="cancelPasskeyRemoval"
            @confirm="confirmPasskeyRemoval"
        />

        <ConfirmationModal
            :open="disableTwoFactorOpen"
            title="Disable two-factor authentication?"
            description="Your account will no longer require an authenticator code when signing in. Your existing recovery codes will also be deleted."
            confirm-text="Disable 2FA"
            cancel-text="Keep enabled"
            danger
            :loading="twoFactorProcessing"
            @cancel="cancelDisableTwoFactor"
            @confirm="disableTwoFactor"
        />

        <ConfirmationModal
            :open="regenerateRecoveryOpen"
            title="Generate new recovery codes?"
            description="Your existing recovery codes will immediately stop working and will be replaced with a new set."
            confirm-text="Generate new codes"
            cancel-text="Cancel"
            :loading="twoFactorProcessing"
            @cancel="cancelRegenerateRecoveryCodes"
            @confirm="regenerateRecoveryCodes"
        />
    </AppLayout>
</template>