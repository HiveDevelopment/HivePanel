<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { Head, router } from '@inertiajs/vue3';
import { Passkeys } from '@laravel/passkeys';
import { ArrowRight, CheckCircle2, Fingerprint, KeyRound, LoaderCircle, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const passkeyName = ref('');
const processing = ref(false);
const error = ref<string | null>(null);
const complete = ref(false);

const suggestedName = computed(() => {
    const platform = navigator.platform?.toLowerCase() ?? '';

    if (platform.includes('win')) {
        return 'Windows Hello';
    }

    if (platform.includes('mac')) {
        return 'Mac';
    }

    if (platform.includes('iphone') || platform.includes('ipad')) {
        return 'iPhone or iPad';
    }

    if (platform.includes('android')) {
        return 'Android device';
    }

    return 'My passkey';
});

const createPasskey = async () => {
    if (processing.value) {
        return;
    }

    processing.value = true;
    error.value = null;

    try {
        await Passkeys.register({
            name: passkeyName.value.trim() || suggestedName.value,
        });

        complete.value = true;
    } catch (exception) {
        if (exception instanceof DOMException && exception.name === 'NotAllowedError') {
            error.value = 'Passkey setup was cancelled.';
        } else if (exception instanceof Error) {
            error.value = exception.message;
        } else {
            error.value = 'Unable to create your passkey.';
        }
    } finally {
        processing.value = false;
    }
};

const continueToDashboard = () => {
    router.visit(route('dashboard'));
};
</script>

<template>
    <Head :title="t('ui.secure_your_account')" />

    <div class="relative min-h-screen overflow-hidden bg-surface-dark text-foreground">
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
                        :alt="t('ui.hivepanel_6123007')"
                        class="mx-auto h-12 w-auto drop-shadow-[0_0_28px_rgba(255,166,0,0.12)]"
                    />

                    <template v-if="!complete">
                        <div class="mx-auto mt-7 flex size-12 items-center justify-center rounded-2xl border border-hive/20 bg-hive/[0.08]">
                            <Fingerprint class="size-6 text-hive" />
                        </div>

                        <h1 class="mt-5 text-[26px] font-semibold tracking-[-0.025em] text-foreground">
                            {{ t('ui.secure_your_account') }}
                        </h1>

                        <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                            {{ t('ui.add_a_passkey_for_a_faster_and_more_secure_way_to_sign_in_to_hive') }}
                        </p>
                    </template>

                    <template v-else>
                        <div
                            class="mx-auto mt-7 flex size-12 items-center justify-center rounded-2xl border border-status-success/20 bg-status-success/[0.08]"
                        >
                            <CheckCircle2 class="size-6 text-status-success" />
                        </div>

                        <h1 class="mt-5 text-[26px] font-semibold tracking-[-0.025em] text-foreground">
                            {{ t('ui.you_re_all_set') }}
                        </h1>

                        <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                            {{ t('ui.your_passkey_has_been_added_and_is_ready_to_use') }}
                        </p>
                    </template>
                </div>

                <div
                    class="relative overflow-hidden rounded-2xl border border-foreground/[0.07] bg-surface-light/95 shadow-[0_32px_100px_rgba(0,0,0,0.45)] backdrop-blur-xl"
                >
                    <div
                        class="pointer-events-none absolute inset-x-12 top-0 h-px bg-gradient-to-r from-transparent via-white/[0.14] to-transparent"
                    />

                    <div v-if="!complete" class="p-6 sm:p-7">
                        <div class="mb-6 grid grid-cols-3 gap-2">
                            <div class="rounded-xl border border-foreground/[0.06] bg-foreground/[0.02] px-2 py-3 text-center">
                                <Fingerprint class="mx-auto size-5 text-hive" />

                                <span class="mt-2 block text-[11px] text-muted-foreground">
                                    {{ t('ui.biometrics') }}
                                </span>
                            </div>

                            <div class="rounded-xl border border-foreground/[0.06] bg-foreground/[0.02] px-2 py-3 text-center">
                                <KeyRound class="mx-auto size-5 text-hive" />

                                <span class="mt-2 block text-[11px] text-muted-foreground">
                                    {{ t('ui.security_keys') }}
                                </span>
                            </div>

                            <div class="rounded-xl border border-foreground/[0.06] bg-foreground/[0.02] px-2 py-3 text-center">
                                <ShieldCheck class="mx-auto size-5 text-hive" />

                                <span class="mt-2 block text-[11px] text-muted-foreground">
                                    {{ t('ui.phishing_resistant') }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label for="passkey-name" class="mb-2 block text-sm font-medium text-foreground">
                                {{ t('ui.passkey_name') }}
                            </label>

                            <input
                                id="passkey-name"
                                v-model="passkeyName"
                                type="text"
                                maxlength="100"
                                :placeholder="suggestedName"
                                class="block w-full rounded-xl border border-foreground/[0.07] bg-surface-dark px-3.5 py-3 text-sm text-foreground outline-none transition duration-200 placeholder:text-muted-foreground hover:border-foreground/[0.11] focus:border-hive/50 focus:bg-surface focus:ring-4 focus:ring-hive/[0.06]"
                                @keyup.enter="createPasskey"
                            />

                            <p class="mt-2 text-xs leading-5 text-muted-foreground">
                                {{ t('ui.give_this_passkey_a_name_so_you_can_recognise_it_later') }}
                            </p>
                        </div>

                        <div
                            v-if="error"
                            class="mt-4 rounded-xl border border-status-danger/20 bg-status-danger/[0.06] px-4 py-3 text-xs font-medium text-status-danger"
                        >
                            {{ error }}
                        </div>

                        <button
                            type="button"
                            :disabled="processing"
                            class="group mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-hive px-4 py-3 text-sm font-semibold text-[#121212] shadow-[0_8px_30px_rgba(255,166,0,0.08)] transition duration-200 hover:bg-hive-light hover:shadow-[0_8px_35px_rgba(255,166,0,0.14)] focus:outline-none focus:ring-4 focus:ring-hive/15 disabled:cursor-wait disabled:opacity-60"
                            @click="createPasskey"
                        >
                            <LoaderCircle v-if="processing" class="size-4 animate-spin" />

                            <Fingerprint v-else class="size-4" />

                            <span>
                                {{ processing ? 'Waiting for your device...' : 'Create passkey' }}
                            </span>

                            <ArrowRight v-if="!processing" class="size-4 transition group-hover:translate-x-0.5" />
                        </button>

                        <button
                            type="button"
                            :disabled="processing"
                            class="mt-3 w-full rounded-xl px-4 py-2.5 text-xs font-medium text-muted-foreground transition hover:bg-foreground/[0.025] hover:text-muted-foreground disabled:pointer-events-none"
                            @click="continueToDashboard"
                        >
                            {{ t('ui.i_ll_do_this_later') }}
                        </button>
                    </div>

                    <div v-else class="p-6 text-center sm:p-7">
                        <div class="rounded-xl border border-status-success/15 bg-status-success/[0.05] px-4 py-4">
                            <div class="flex items-center justify-center gap-2 text-sm font-medium text-foreground">
                                <CheckCircle2 class="size-4 text-status-success" />
                                {{ t('ui.passkey_successfully_created') }}
                            </div>

                            <p class="mt-2 text-xs leading-5 text-muted-foreground">
                                {{ t('ui.you_can_now_use_your_passkey_when_signing_in_to_hivepanel') }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="group mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-hive px-4 py-3 text-sm font-semibold text-[#121212] transition duration-200 hover:bg-hive-light focus:outline-none focus:ring-4 focus:ring-hive/15"
                            @click="continueToDashboard"
                        >
                            {{ t('ui.continue_to_hivepanel') }}

                            <ArrowRight class="size-4 transition group-hover:translate-x-0.5" />
                        </button>
                    </div>

                    <div class="border-t border-foreground/[0.05] bg-black/[0.08] px-6 py-3.5">
                        <div class="flex items-center justify-center gap-1.5 text-[11px] text-muted-foreground">
                            <ShieldCheck class="size-3.5" />
                            <span>{{ t('ui.your_passkey_never_leaves_your_device') }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-7 flex items-center justify-center gap-2 text-[11px] text-muted-foreground">
                    <span>{{ t('ui.hivepanel_6123007') }}</span>
                    <span class="size-0.5 rounded-full bg-surface-elevated" />
                    <span>{{ t('ui.game_server_management_rebuilt') }}</span>
                </div>
            </div>
        </main>
    </div>
</template>
