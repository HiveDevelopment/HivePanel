<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Passkeys } from '@laravel/passkeys';
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
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';

interface Passkey {
    id: number | string;
    name: string;
    created_at: string | null;
    last_used_at: string | null;
}

interface TwoFactor {
    enabled: boolean;
    confirmed_at: string | null;
    required: boolean;
}

const props = defineProps<{
    passkeysEnabled: boolean;
    passwordMinLength: number;
    passkeys: Passkey[];
    twoFactor: TwoFactor;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security settings',
        href: '/settings/security',
    },
];

const passwordInput = ref<HTMLInputElement>();
const currentPasswordInput = ref<HTMLInputElement>();

const passkeyName = ref('');
const passkeyProcessing = ref(false);
const passkeyError = ref<string | null>(null);
const passkeyCreated = ref(false);
const removingPasskey = ref<number | string | null>(null);
const passkeyPendingRemoval = ref<Passkey | null>(null);

const twoFactorEnabled = ref(props.twoFactor.enabled);
const twoFactorSetupOpen = ref(false);
const twoFactorSetupStep = ref<'setup' | 'recovery'>('setup');
const twoFactorProcessing = ref(false);
const twoFactorSecret = ref('');
const twoFactorQrCode = ref('');
const twoFactorCode = ref('');
const twoFactorError = ref<string | null>(null);
const recoveryCodes = ref<string[]>([]);
const disableTwoFactorOpen = ref(false);
const regenerateRecoveryOpen = ref(false);
const copiedRecoveryCodes = ref(false);
const copiedSecret = ref(false);

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const suggestedPasskeyName = computed(() => {
    const userAgent = navigator.userAgent.toLowerCase();

    if (userAgent.includes('windows')) {
        return 'Windows Hello';
    }

    if (userAgent.includes('iphone')) {
        return 'iPhone';
    }

    if (userAgent.includes('ipad')) {
        return 'iPad';
    }

    if (userAgent.includes('mac')) {
        return 'Mac';
    }

    if (userAgent.includes('android')) {
        return 'Android device';
    }

    return 'My passkey';
});

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,

        onSuccess: () => {
            passwordForm.reset();
        },

        onError: (errors) => {
            if (errors.password) {
                passwordForm.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }

            if (errors.current_password) {
                passwordForm.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};

const requestPasswordConfirmation = (action: 'add-passkey' | 'enable-2fa' | 'disable-2fa' | 'recovery-codes') => {
    const params = new URLSearchParams({
        action,
    });

    const returnTo = `/settings/security?${params.toString()}`;

    window.location.href = `${route('password.confirm')}?return=${encodeURIComponent(returnTo)}`;
};

const requiresPasswordConfirmation = (error: unknown): boolean => {
    if (!(error instanceof Error)) {
        return false;
    }

    const message = error.message.toLowerCase();

    return (
        message.includes('password confirmation') ||
        message.includes('password.confirm') ||
        message.includes('confirm your password') ||
        message.includes('419')
    );
};

const addPasskey = async () => {
    if (passkeyProcessing.value) {
        return;
    }

    passkeyProcessing.value = true;
    passkeyError.value = null;
    passkeyCreated.value = false;

    const name = passkeyName.value.trim() || suggestedPasskeyName.value;

    try {
        await Passkeys.register({
            name,
        });

        passkeyName.value = '';
        passkeyCreated.value = true;

        sessionStorage.removeItem('hivepanel.pendingPasskeyName');

        router.reload({
            only: ['passkeys'],
            preserveScroll: true,
        });
    } catch (error) {
        if (requiresPasswordConfirmation(error)) {
            sessionStorage.setItem('hivepanel.pendingPasskeyName', name);
            requestPasswordConfirmation('add-passkey');

            return;
        }

        if (error instanceof DOMException && error.name === 'NotAllowedError') {
            passkeyError.value = 'Passkey setup was cancelled.';
        } else if (error instanceof Error) {
            passkeyError.value = error.message;
        } else {
            passkeyError.value = 'Unable to create your passkey.';
        }
    } finally {
        passkeyProcessing.value = false;
    }
};

const requestPasskeyRemoval = (passkey: Passkey) => {
    if (removingPasskey.value !== null) {
        return;
    }

    passkeyError.value = null;
    passkeyPendingRemoval.value = passkey;
};

const cancelPasskeyRemoval = () => {
    if (removingPasskey.value !== null) {
        return;
    }

    passkeyPendingRemoval.value = null;
};

const confirmPasskeyRemoval = () => {
    const passkey = passkeyPendingRemoval.value;

    if (!passkey || removingPasskey.value !== null) {
        return;
    }

    removingPasskey.value = passkey.id;
    passkeyError.value = null;

    router.delete(route('passkey.destroy', passkey.id), {
        preserveScroll: true,

        onSuccess: () => {
            passkeyPendingRemoval.value = null;
        },

        onError: () => {
            passkeyError.value = 'Password confirmation may be required before removing a passkey.';
        },

        onFinish: () => {
            removingPasskey.value = null;
        },
    });
};

const beginTwoFactorSetup = async () => {
    if (twoFactorProcessing.value) {
        return;
    }

    twoFactorProcessing.value = true;
    twoFactorError.value = null;
    twoFactorCode.value = '';

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
        });

        if (response.status === 423 || response.redirected) {
            requestPasswordConfirmation('enable-2fa');
            return;
        }

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 409 || response.status === 403) {
                requestPasswordConfirmation('enable-2fa');
                return;
            }

            throw new Error(data.message || 'Unable to start two-factor authentication setup.');
        }

        twoFactorSecret.value = data.secret;
        twoFactorQrCode.value = data.qr_code;
        twoFactorSetupStep.value = 'setup';
        twoFactorSetupOpen.value = true;
    } catch (error) {
        twoFactorError.value = error instanceof Error ? error.message : 'Unable to start two-factor authentication setup.';
    } finally {
        twoFactorProcessing.value = false;
    }
};

const confirmTwoFactorSetup = async () => {
    if (twoFactorProcessing.value || !/^\d{6}$/.test(twoFactorCode.value)) {
        return;
    }

    twoFactorProcessing.value = true;
    twoFactorError.value = null;

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
        });

        const data = await response.json();

        if (!response.ok) {
            twoFactorError.value = data.errors?.code?.[0] || data.message || 'The authentication code is invalid.';

            return;
        }

        recoveryCodes.value = data.recovery_codes ?? [];
        twoFactorEnabled.value = true;
        twoFactorSetupStep.value = 'recovery';
        twoFactorCode.value = '';

        router.reload({
            only: ['twoFactor'],
            preserveScroll: true,
        });
    } catch {
        twoFactorError.value = 'Unable to confirm two-factor authentication.';
    } finally {
        twoFactorProcessing.value = false;
    }
};

const closeTwoFactorSetup = () => {
    if (twoFactorProcessing.value) {
        return;
    }

    if (twoFactorSetupStep.value === 'recovery' && recoveryCodes.value.length) {
        recoveryCodes.value = [];
    }

    twoFactorSetupOpen.value = false;
    twoFactorSetupStep.value = 'setup';
    twoFactorSecret.value = '';
    twoFactorQrCode.value = '';
    twoFactorCode.value = '';
    twoFactorError.value = null;
    copiedRecoveryCodes.value = false;
    copiedSecret.value = false;
};

const requestDisableTwoFactor = () => {
    disableTwoFactorOpen.value = true;
};

const cancelDisableTwoFactor = () => {
    if (twoFactorProcessing.value) {
        return;
    }

    disableTwoFactorOpen.value = false;
};

const disableTwoFactor = () => {
    if (twoFactorProcessing.value) {
        return;
    }

    twoFactorProcessing.value = true;

    router.delete(route('two-factor.disable'), {
        preserveScroll: true,

        onSuccess: () => {
            twoFactorEnabled.value = false;
            disableTwoFactorOpen.value = false;
        },

        onError: () => {
            disableTwoFactorOpen.value = false;
            requestPasswordConfirmation('disable-2fa');
        },

        onFinish: () => {
            twoFactorProcessing.value = false;
        },
    });
};

const requestRegenerateRecoveryCodes = () => {
    regenerateRecoveryOpen.value = true;
};

const cancelRegenerateRecoveryCodes = () => {
    if (twoFactorProcessing.value) {
        return;
    }

    regenerateRecoveryOpen.value = false;
};

const regenerateRecoveryCodes = async () => {
    if (twoFactorProcessing.value) {
        return;
    }

    twoFactorProcessing.value = true;
    twoFactorError.value = null;

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
        });

        if (response.status === 423 || response.redirected) {
            requestPasswordConfirmation('recovery-codes');
            return;
        }

        const data = await response.json();

        if (!response.ok) {
            requestPasswordConfirmation('recovery-codes');
            return;
        }

        recoveryCodes.value = data.recovery_codes ?? [];
        regenerateRecoveryOpen.value = false;
        twoFactorSetupStep.value = 'recovery';
        twoFactorSetupOpen.value = true;
    } catch {
        twoFactorError.value = 'Unable to generate new recovery codes.';
    } finally {
        twoFactorProcessing.value = false;
    }
};

const copyRecoveryCodes = async () => {
    if (!recoveryCodes.value.length) {
        return;
    }

    await navigator.clipboard.writeText(recoveryCodes.value.join('\n'));

    copiedRecoveryCodes.value = true;

    window.setTimeout(() => {
        copiedRecoveryCodes.value = false;
    }, 2000);
};

const copySecret = async () => {
    if (!twoFactorSecret.value) {
        return;
    }

    await navigator.clipboard.writeText(twoFactorSecret.value);

    copiedSecret.value = true;

    window.setTimeout(() => {
        copiedSecret.value = false;
    }, 2000);
};

const downloadRecoveryCodes = () => {
    if (!recoveryCodes.value.length) {
        return;
    }

    const contents = [
        'HivePanel recovery codes',
        '',
        'Each recovery code can only be used once.',
        'Store these codes somewhere safe.',
        '',
        ...recoveryCodes.value,
    ].join('\n');

    const blob = new Blob([contents], {
        type: 'text/plain;charset=utf-8',
    });

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = 'hivepanel-recovery-codes.txt';

    document.body.appendChild(link);
    link.click();
    link.remove();

    URL.revokeObjectURL(url);
};

const formatDate = (value: string | null) => {
    if (!value) {
        return 'Never';
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

const formatLastUsed = (value: string | null) => {
    if (!value) {
        return 'Never used';
    }

    const date = new Date(value);
    const now = new Date();

    if (date.toDateString() === now.toDateString()) {
        return 'Used today';
    }

    return `Last used ${formatDate(value)}`;
};

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const action = params.get('action');

    if (!action) {
        return;
    }

    window.history.replaceState({}, '', '/settings/security');

    await nextTick();

    if (action === 'add-passkey') {
        const pendingName = sessionStorage.getItem('hivepanel.pendingPasskeyName');

        if (pendingName) {
            passkeyName.value = pendingName;
        }

        sessionStorage.removeItem('hivepanel.pendingPasskeyName');

        await addPasskey();
        return;
    }

    if (action === 'enable-2fa') {
        await beginTwoFactorSetup();
        return;
    }

    if (action === 'disable-2fa') {
        disableTwoFactorOpen.value = true;
        return;
    }

    if (action === 'recovery-codes') {
        await regenerateRecoveryCodes();
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('ui.security_settings')" />

        <SettingsLayout>
            <div class="space-y-5">
                <section class="overflow-hidden rounded-2xl border border-foreground/[0.07] bg-surface-light">
                    <div class="border-b border-foreground/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-foreground/[0.035]">
                                <LockKeyhole class="size-4 text-muted-foreground" />
                            </div>

                            <div>
                                <h2 class="text-sm font-semibold text-foreground">
                                    {{ t('ui.password') }}
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.change_the_password_used_to_access_your_hivepanel_account') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-5 p-5 sm:p-6" @submit.prevent="updatePassword">
                        <div>
                            <label for="current_password" class="mb-2 block text-xs font-medium text-muted-foreground">
                                {{ t('ui.current_password') }}
                            </label>

                            <input
                                id="current_password"
                                ref="currentPasswordInput"
                                v-model="passwordForm.current_password"
                                type="password"
                                autocomplete="current-password"
                                :placeholder="t('ui.enter_your_current_password')"
                                class="block w-full rounded-xl border border-foreground/[0.07] bg-surface-dark px-3.5 py-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground hover:border-foreground/[0.11] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                            />

                            <InputError class="mt-2" :message="passwordForm.errors.current_password" />
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="password" class="mb-2 block text-xs font-medium text-muted-foreground">
                                    {{ t('ui.new_password') }}
                                </label>

                                <input
                                    id="password"
                                    ref="passwordInput"
                                    v-model="passwordForm.password"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="`At least ${passwordMinLength} characters`"
                                    class="block w-full rounded-xl border border-foreground/[0.07] bg-surface-dark px-3.5 py-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground hover:border-foreground/[0.11] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                />

                                <InputError class="mt-2" :message="passwordForm.errors.password" />
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-2 block text-xs font-medium text-muted-foreground">
                                    {{ t('ui.confirm_new_password') }}
                                </label>

                                <input
                                    id="password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="t('ui.repeat_your_new_password')"
                                    class="block w-full rounded-xl border border-foreground/[0.07] bg-surface-dark px-3.5 py-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground hover:border-foreground/[0.11] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                />

                                <InputError class="mt-2" :message="passwordForm.errors.password_confirmation" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-foreground/[0.05] pt-5">
                            <div
                                v-if="passwordForm.recentlySuccessful"
                                class="mr-auto flex items-center gap-1.5 text-xs font-medium text-status-success"
                            >
                                <Check class="size-3.5" />
                                {{ t('ui.password_updated') }}
                            </div>

                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light disabled:cursor-wait disabled:opacity-60"
                            >
                                <LoaderCircle v-if="passwordForm.processing" class="size-4 animate-spin" />

                                {{ t('ui.update_password') }}
                            </button>
                        </div>
                    </form>
                </section>

                <section class="overflow-hidden rounded-2xl border border-foreground/[0.07] bg-surface-light">
                    <div class="border-b border-foreground/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-hive/[0.07]">
                                    <ShieldCheck class="size-4 text-hive" />
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-semibold text-foreground">
                                            {{ t('ui.two_factor_authentication') }}
                                        </h2>

                                        <span
                                            v-if="twoFactor.required"
                                            class="rounded-md border border-status-warning/20 bg-status-warning/[0.07] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-status-warning"
                                        >
                                            {{ t('ui.required') }}
                                        </span>

                                        <span
                                            v-else
                                            class="rounded-md border border-hive/15 bg-hive/[0.06] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-hive"
                                        >
                                            {{ t('ui.recommended') }}
                                        </span>
                                    </div>

                                    <p class="mt-1 max-w-xl text-xs leading-5 text-muted-foreground">
                                        {{ t('ui.protect_your_account_with_a_time_based_code_from_an_authenticator') }}
                                    </p>
                                </div>
                            </div>

                            <span
                                :class="[
                                    'hidden rounded-lg border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider sm:inline-flex',
                                    twoFactorEnabled
                                        ? 'border-status-success/15 bg-status-success/[0.06] text-status-success'
                                        : 'border-foreground/[0.06] bg-foreground/[0.025] text-muted-foreground',
                                ]"
                            >
                                {{ twoFactorEnabled ? 'Enabled' : 'Not configured' }}
                            </span>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6">
                        <div v-if="twoFactorEnabled" class="flex flex-col gap-5 sm:flex-row sm:items-center">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-status-success/10 bg-status-success/[0.06]"
                            >
                                <Check class="size-5 text-status-success" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-foreground">
                                    {{ t('ui.authenticator_app_configured') }}
                                </div>

                                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.your_account_will_require_an_authentication_code_after_signing_in') }}
                                </p>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-foreground/[0.07] bg-foreground/[0.025] px-3.5 py-2.5 text-xs font-semibold text-muted-foreground transition hover:border-foreground/[0.12] hover:text-foreground"
                                    @click="requestRegenerateRecoveryCodes"
                                >
                                    <RefreshCw class="size-3.5" />
                                    {{ t('ui.recovery_codes') }}
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-status-danger/15 bg-status-danger/[0.05] px-3.5 py-2.5 text-xs font-semibold text-status-danger transition hover:border-status-danger/25 hover:bg-status-danger/[0.08]"
                                    @click="requestDisableTwoFactor"
                                >
                                    {{ t('ui.disable') }}
                                </button>
                            </div>
                        </div>

                        <div v-else class="flex flex-col gap-5 sm:flex-row sm:items-center">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-foreground/[0.06] bg-foreground/[0.025]"
                            >
                                <ShieldCheck class="size-5 text-muted-foreground" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-foreground">
                                    {{ t('ui.authenticator_app') }}
                                </div>

                                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.use_an_app_such_as_microsoft_authenticator_google_authenticator_1') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                :disabled="twoFactorProcessing"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-hive/20 bg-hive/[0.08] px-4 py-2.5 text-xs font-semibold text-hive transition hover:border-hive/30 hover:bg-hive/[0.12] disabled:opacity-50"
                                @click="beginTwoFactorSetup"
                            >
                                <LoaderCircle v-if="twoFactorProcessing" class="size-3.5 animate-spin" />

                                <Plus v-else class="size-3.5" />

                                {{ t('ui.set_up_2fa') }}
                            </button>
                        </div>

                        <div
                            v-if="twoFactorError && !twoFactorSetupOpen"
                            class="mt-4 rounded-xl border border-status-danger/15 bg-status-danger/[0.06] px-4 py-3 text-xs font-medium text-status-danger"
                        >
                            {{ twoFactorError }}
                        </div>
                    </div>
                </section>

                <section v-if="passkeysEnabled" class="overflow-hidden rounded-2xl border border-foreground/[0.07] bg-surface-light">
                    <div class="border-b border-foreground/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-hive/[0.07]">
                                    <Fingerprint class="size-4 text-hive" />
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-semibold text-foreground">
                                            {{ t('ui.passkeys') }}
                                        </h2>

                                        <span
                                            class="rounded-md border border-hive/15 bg-hive/[0.06] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-hive"
                                        >
                                            {{ t('ui.recommended') }}
                                        </span>
                                    </div>

                                    <p class="mt-1 max-w-xl text-xs leading-5 text-muted-foreground">
                                        {{ t('ui.sign_in_using_windows_hello_touch_id_face_id_or_a_security_key_wi') }}
                                    </p>
                                </div>
                            </div>

                            <div class="hidden text-right sm:block">
                                <div class="text-lg font-semibold text-foreground">
                                    {{ passkeys.length }}
                                </div>

                                <div class="text-[10px] uppercase tracking-wider text-muted-foreground">
                                    {{ passkeys.length === 1 ? 'Passkey' : 'Passkeys' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6">
                        <div v-if="passkeys.length" class="mb-5 space-y-2">
                            <div
                                v-for="passkey in passkeys"
                                :key="passkey.id"
                                class="group flex items-center gap-4 rounded-xl border border-foreground/[0.06] bg-black/[0.12] px-4 py-3.5 transition hover:border-foreground/[0.1]"
                            >
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-foreground/[0.06] bg-foreground/[0.025]"
                                >
                                    <KeyRound class="size-4 text-muted-foreground" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-foreground">
                                        {{ passkey.name }}
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-muted-foreground">
                                        <span> {{ t('ui.added') }} {{ formatDate(passkey.created_at) }} </span>

                                        <span class="size-0.5 rounded-full bg-surface-elevated" />

                                        <span>
                                            {{ formatLastUsed(passkey.last_used_at) }}
                                        </span>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    :disabled="removingPasskey !== null"
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-status-danger/10 hover:text-status-danger disabled:opacity-40"
                                    :title="t('ui.remove_passkey')"
                                    @click="requestPasskeyRemoval(passkey)"
                                >
                                    <LoaderCircle v-if="removingPasskey === passkey.id" class="size-3.5 animate-spin" />

                                    <Trash2 v-else class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <div v-else class="mb-5 rounded-xl border border-dashed border-foreground/[0.08] bg-black/[0.08] px-5 py-7 text-center">
                            <Fingerprint class="mx-auto size-6 text-muted-foreground" />

                            <p class="mt-3 text-sm font-medium text-muted-foreground">
                                {{ t('ui.no_passkeys_added') }}
                            </p>

                            <p class="mx-auto mt-1 max-w-sm text-xs leading-5 text-muted-foreground">
                                {{ t('ui.add_a_passkey_to_sign_in_quickly_and_securely_using_your_device') }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-foreground/[0.06] bg-black/[0.1] p-4">
                            <label for="passkey-name" class="mb-2 block text-xs font-medium text-muted-foreground">
                                {{ t('ui.add_a_passkey') }}
                            </label>

                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input
                                    id="passkey-name"
                                    v-model="passkeyName"
                                    type="text"
                                    maxlength="100"
                                    :placeholder="suggestedPasskeyName"
                                    class="min-w-0 flex-1 rounded-xl border border-foreground/[0.07] bg-surface-dark px-3.5 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground hover:border-foreground/[0.11] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                    @keyup.enter="addPasskey"
                                />

                                <button
                                    type="button"
                                    :disabled="passkeyProcessing"
                                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-hive/20 bg-hive/[0.08] px-4 py-2.5 text-sm font-semibold text-hive transition hover:border-hive/30 hover:bg-hive/[0.12] disabled:cursor-wait disabled:opacity-50"
                                    @click="addPasskey"
                                >
                                    <LoaderCircle v-if="passkeyProcessing" class="size-4 animate-spin" />

                                    <Plus v-else class="size-4" />

                                    {{ passkeyProcessing ? 'Waiting...' : 'Add passkey' }}
                                </button>
                            </div>

                            <p class="mt-2 text-[11px] text-muted-foreground">
                                {{ t('ui.give_it_a_name_that_identifies_the_device_such_as_windows_hello_o') }}
                            </p>
                        </div>

                        <div
                            v-if="passkeyError"
                            class="mt-4 rounded-xl border border-status-danger/15 bg-status-danger/[0.06] px-4 py-3 text-xs font-medium text-status-danger"
                        >
                            {{ passkeyError }}
                        </div>

                        <div
                            v-if="passkeyCreated"
                            class="mt-4 flex items-center gap-2 rounded-xl border border-status-success/15 bg-status-success/[0.06] px-4 py-3 text-xs font-medium text-status-success"
                        >
                            <Check class="size-3.5" />
                            {{ t('ui.passkey_added_successfully') }}
                        </div>
                    </div>
                </section>
            </div>
        </SettingsLayout>

        <div v-if="twoFactorSetupOpen" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg overflow-hidden rounded-panel border border-border bg-surface shadow-[0_25px_80px_rgba(0,0,0,0.55)]">
                <div class="flex items-center justify-between border-b border-border px-6 py-5">
                    <div>
                        <h2 class="text-lg font-black text-foreground">
                            {{ twoFactorSetupStep === 'setup' ? 'Set up two-factor authentication' : 'Save your recovery codes' }}
                        </h2>

                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                twoFactorSetupStep === 'setup'
                                    ? 'Secure your account with an authenticator app.'
                                    : 'Keep these somewhere safe. Each code can only be used once.'
                            }}
                        </p>
                    </div>

                    <button
                        type="button"
                        :disabled="twoFactorProcessing"
                        class="text-muted-foreground transition hover:text-foreground disabled:opacity-50"
                        @click="closeTwoFactorSetup"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div v-if="twoFactorSetupStep === 'setup'" class="px-6 py-6">
                    <div class="flex justify-center">
                        <div class="rounded-2xl bg-white p-4" v-html="twoFactorQrCode" />
                    </div>

                    <p class="mx-auto mt-5 max-w-sm text-center text-sm leading-6 text-muted-foreground">
                        {{ t('ui.scan_the_qr_code_with_your_authenticator_app_then_enter_the_6_dig') }}
                    </p>

                    <div class="mt-5 rounded-xl border border-border bg-surface-dark p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">
                                    {{ t('ui.manual_setup_key') }}
                                </div>

                                <div class="mt-1 truncate font-mono text-xs font-bold tracking-wider text-foreground">
                                    {{ twoFactorSecret }}
                                </div>
                            </div>

                            <button
                                type="button"
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-foreground/[0.04] hover:text-hive"
                                :title="t('ui.copy_setup_key')"
                                @click="copySecret"
                            >
                                <Check v-if="copiedSecret" class="size-3.5 text-status-success" />

                                <Clipboard v-else class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label for="two-factor-code" class="mb-2 block text-xs font-black uppercase tracking-wide text-muted-foreground">
                            {{ t('ui.authentication_code') }}
                        </label>

                        <input
                            id="two-factor-code"
                            v-model="twoFactorCode"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            placeholder="000000"
                            class="block w-full rounded-xl border border-border bg-surface-dark px-4 py-3 text-center font-mono text-lg font-black tracking-[0.35em] text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/60 focus:ring-4 focus:ring-hive/[0.06]"
                            @input="twoFactorCode = twoFactorCode.replace(/\D/g, '').slice(0, 6)"
                            @keyup.enter="confirmTwoFactorSetup"
                        />

                        <div
                            v-if="twoFactorError"
                            class="mt-3 rounded-xl border border-status-danger/15 bg-status-danger/[0.06] px-4 py-3 text-xs font-medium text-status-danger"
                        >
                            {{ twoFactorError }}
                        </div>
                    </div>
                </div>

                <div v-else class="px-6 py-6">
                    <div class="mb-5 flex items-start gap-3 rounded-xl border border-status-warning/15 bg-status-warning/[0.05] px-4 py-3">
                        <KeyRound class="mt-0.5 size-4 shrink-0 text-status-warning" />

                        <p class="text-xs leading-5 text-status-warning/70">
                            {{ t('ui.these_recovery_codes_are_shown_once_save_them_somewhere_secure_be') }}
                        </p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="code in recoveryCodes"
                            :key="code"
                            class="rounded-lg border border-border bg-surface-dark px-3 py-2.5 text-center font-mono text-xs font-bold tracking-wider text-foreground"
                        >
                            {{ code }}
                        </div>
                    </div>

                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-surface-light px-4 py-2.5 text-xs font-bold text-foreground transition hover:text-foreground"
                            @click="copyRecoveryCodes"
                        >
                            <Check v-if="copiedRecoveryCodes" class="size-3.5 text-status-success" />

                            <Clipboard v-else class="size-3.5" />

                            {{ copiedRecoveryCodes ? 'Copied' : 'Copy all' }}
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-surface-light px-4 py-2.5 text-xs font-bold text-foreground transition hover:text-foreground"
                            @click="downloadRecoveryCodes"
                        >
                            <Download class="size-3.5" />
                            {{ t('ui.download') }}
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-border px-6 py-4">
                    <button
                        v-if="twoFactorSetupStep === 'setup'"
                        type="button"
                        :disabled="twoFactorProcessing"
                        class="rounded-button border border-border bg-surface-light px-4 py-2 text-sm font-bold text-foreground transition hover:text-foreground disabled:opacity-50"
                        @click="closeTwoFactorSetup"
                    >
                        {{ t('ui.cancel') }}
                    </button>

                    <button
                        v-if="twoFactorSetupStep === 'setup'"
                        type="button"
                        :disabled="twoFactorProcessing || twoFactorCode.length !== 6"
                        class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light disabled:opacity-50"
                        @click="confirmTwoFactorSetup"
                    >
                        <LoaderCircle v-if="twoFactorProcessing" class="size-4 animate-spin" />

                        {{ twoFactorProcessing ? 'Verifying...' : 'Enable 2FA' }}
                    </button>

                    <button
                        v-else
                        type="button"
                        class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                        @click="closeTwoFactorSetup"
                    >
                        {{ t('ui.i_ve_saved_them') }}
                    </button>
                </div>
            </div>
        </div>

        <ConfirmationModal
            :open="passkeyPendingRemoval !== null"
            :title="t('ui.remove_passkey_a733f0e')"
            :description="
                passkeyPendingRemoval
                    ? `Are you sure you want to remove “${passkeyPendingRemoval.name}”? You will no longer be able to use this passkey to sign in to your account.`
                    : undefined
            "
            :confirm-text="t('ui.remove_passkey')"
            :cancel-text="t('ui.keep_passkey')"
            danger
            :loading="removingPasskey !== null"
            @cancel="cancelPasskeyRemoval"
            @confirm="confirmPasskeyRemoval"
        />

        <ConfirmationModal
            :open="disableTwoFactorOpen"
            :title="t('ui.disable_two_factor_authentication')"
            :description="t('ui.your_account_will_no_longer_require_an_authenticator_code_when_si')"
            :confirm-text="t('ui.disable_2fa')"
            :cancel-text="t('ui.keep_enabled')"
            danger
            :loading="twoFactorProcessing"
            @cancel="cancelDisableTwoFactor"
            @confirm="disableTwoFactor"
        />

        <ConfirmationModal
            :open="regenerateRecoveryOpen"
            :title="t('ui.generate_new_recovery_codes')"
            :description="t('ui.your_existing_recovery_codes_will_immediately_stop_working_and_wi')"
            :confirm-text="t('ui.generate_new_codes')"
            :cancel-text="t('Cancel')"
            :loading="twoFactorProcessing"
            @cancel="cancelRegenerateRecoveryCodes"
            @confirm="regenerateRecoveryCodes"
        />
    </AppLayout>
</template>
