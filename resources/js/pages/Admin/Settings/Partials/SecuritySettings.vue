<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import { useForm } from '@inertiajs/vue3';
import { Fingerprint, KeyRound, Save, ShieldCheck, UserPlus } from 'lucide-vue-next';
import type { SettingsPayload, TwoFactorRequirement } from '../types';

const props = defineProps<{
    settings: SettingsPayload['security'];
    requireTwoFactor: TwoFactorRequirement;
}>();

const form = useForm({
    allow_registration: props.settings.allow_registration ?? false,
    require_email_verification: props.settings.require_email_verification ?? false,
    allow_passkeys: props.settings.allow_passkeys ?? true,
    session_lifetime: props.settings.session_lifetime ?? 120,
    password_min_length: props.settings.password_min_length ?? 8,
    require_2fa: props.requireTwoFactor ?? 'not_required',
});

function submit() {
    form.patch('/admin/settings/security', {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <section class="rounded-panel border border-foreground/[0.06] bg-surface p-5 sm:p-6">
            <div class="mb-5">
                <h3 class="text-base font-semibold text-foreground">
                    {{ t('ui.account_security') }}
                </h3>

                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('ui.control_account_creation_and_authentication_requirements') }}
                </p>
            </div>

            <div class="divide-y divide-foreground/[0.05] rounded-xl border border-foreground/[0.06] bg-surface">
                <label class="flex cursor-pointer items-center justify-between gap-6 p-4">
                    <div class="flex min-w-0 gap-3">
                        <UserPlus class="mt-0.5 size-5 shrink-0 text-muted-foreground" />

                        <div>
                            <div class="text-sm font-medium text-foreground">
                                {{ t('ui.allow_public_registration') }}
                            </div>

                            <div class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.allow_users_to_create_accounts_without_an_administrator') }}
                            </div>
                        </div>
                    </div>

                    <input
                        v-model="form.allow_registration"
                        type="checkbox"
                        class="size-5 shrink-0 rounded border-border bg-black text-hive focus:ring-hive"
                    />
                </label>

                <label class="flex cursor-pointer items-center justify-between gap-6 p-4">
                    <div class="flex min-w-0 gap-3">
                        <ShieldCheck class="mt-0.5 size-5 shrink-0 text-muted-foreground" />

                        <div>
                            <div class="text-sm font-medium text-foreground">
                                {{ t('ui.require_email_verification') }}
                            </div>

                            <div class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.require_users_to_verify_their_email_address_before_accessing_the_') }}
                            </div>
                        </div>
                    </div>

                    <input
                        v-model="form.require_email_verification"
                        type="checkbox"
                        class="size-5 shrink-0 rounded border-border bg-black text-hive focus:ring-hive"
                    />
                </label>

                <label class="flex cursor-pointer items-center justify-between gap-6 p-4">
                    <div class="flex min-w-0 gap-3">
                        <Fingerprint class="mt-0.5 size-5 shrink-0 text-muted-foreground" />

                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-foreground">
                                    {{ t('ui.passkeys') }}
                                </span>

                                <span
                                    class="rounded-full border border-hive/20 bg-hive/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-hive"
                                >
                                    {{ t('ui.recommended') }}
                                </span>
                            </div>

                            <div class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.allow_passwordless_sign_in_using_windows_hello_touch_id_face_id_a') }}
                            </div>
                        </div>
                    </div>

                    <input
                        v-model="form.allow_passkeys"
                        type="checkbox"
                        class="size-5 shrink-0 rounded border-border bg-black text-hive focus:ring-hive"
                    />
                </label>
            </div>
        </section>

        <section class="rounded-panel border border-foreground/[0.06] bg-surface p-5 sm:p-6">
            <div class="mb-5 flex items-center gap-3">
                <KeyRound class="size-5 text-hive" />

                <div>
                    <h3 class="text-base font-semibold text-foreground">
                        {{ t('ui.authentication_policy') }}
                    </h3>

                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('ui.configure_additional_authentication_requirements') }}
                    </p>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-foreground">
                    {{ t('ui.two_factor_authentication') }}
                </label>

                <select
                    v-model="form.require_2fa"
                    class="mt-2 w-full rounded-lg border border-foreground/[0.07] bg-surface px-3.5 py-2.5 text-sm text-foreground outline-none transition focus:border-hive/50"
                >
                    <option value="not_required">{{ t('ui.optional') }}</option>
                    <option value="admin_only">{{ t('ui.required_for_administrators') }}</option>
                    <option value="all_users">{{ t('ui.required_for_everyone') }}</option>
                </select>

                <p class="mt-2 text-xs text-muted-foreground">
                    {{ t('ui.determines_which_users_must_configure_two_factor_authentication') }}
                </p>
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-foreground">
                        {{ t('ui.session_lifetime') }}
                    </label>

                    <div class="relative mt-2">
                        <input
                            v-model="form.session_lifetime"
                            type="number"
                            min="5"
                            max="10080"
                            class="w-full rounded-lg border border-foreground/[0.07] bg-surface px-3.5 py-2.5 pr-20 text-sm text-foreground outline-none transition focus:border-hive/50"
                        />

                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">
                            {{ t('ui.minutes') }}
                        </span>
                    </div>

                    <p v-if="form.errors.session_lifetime" class="mt-2 text-xs font-medium text-status-danger">
                        {{ form.errors.session_lifetime }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium text-foreground">
                        {{ t('ui.minimum_password_length') }}
                    </label>

                    <div class="relative mt-2">
                        <input
                            v-model="form.password_min_length"
                            type="number"
                            min="8"
                            max="128"
                            class="w-full rounded-lg border border-foreground/[0.07] bg-surface px-3.5 py-2.5 pr-24 text-sm text-foreground outline-none transition focus:border-hive/50"
                        />

                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">
                            {{ t('ui.characters') }}
                        </span>
                    </div>

                    <p v-if="form.errors.password_min_length" class="mt-2 text-xs font-medium text-status-danger">
                        {{ form.errors.password_min_length }}
                    </p>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center gap-2 rounded-lg bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light disabled:opacity-50"
            >
                <Save class="size-4" />
                {{ form.processing ? 'Saving...' : 'Save security settings' }}
            </button>
        </div>
    </form>
</template>
