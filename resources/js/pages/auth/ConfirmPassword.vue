<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { t } from '@/composables/useLanguage';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Fingerprint, LoaderCircle, LockKeyhole, ShieldCheck } from 'lucide-vue-next';

defineProps<{
    returnTo?: string | null;
}>();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        preserveScroll: true,

        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <AuthLayout :title="t('ui.confirm_it_s_you')" :description="t('ui.this_is_a_security_sensitive_action_confirm_your_password_to_cont')">
        <Head :title="t('ui.confirm_password')" />

        <div class="space-y-5">
            <div class="rounded-xl border border-hive/15 bg-hive/[0.05] p-4">
                <div class="flex items-start gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-hive/15 bg-hive/[0.07]">
                        <ShieldCheck class="size-4 text-hive" />
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-foreground">
                            {{ t('ui.security_verification') }}
                        </p>

                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            {{ t('ui.hivepanel_requires_your_password_before_allowing_changes_to_sensi') }}
                        </p>
                    </div>
                </div>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="password" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ t('ui.current_password') }}
                    </label>

                    <div
                        class="flex items-center gap-3 rounded-xl border border-foreground/[0.07] bg-surface-dark px-4 py-3 transition focus-within:border-hive/50 focus-within:ring-4 focus-within:ring-hive/[0.05]"
                    >
                        <LockKeyhole class="size-4 shrink-0 text-muted-foreground" />

                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autofocus
                            autocomplete="current-password"
                            :placeholder="t('ui.enter_your_password')"
                            class="w-full bg-transparent text-sm font-medium text-foreground outline-none placeholder:text-muted-foreground"
                        />
                    </div>

                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-hive px-4 py-3 text-sm font-semibold text-black transition hover:bg-hive-light disabled:cursor-wait disabled:opacity-60"
                >
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />

                    <Fingerprint v-else class="size-4" />

                    {{ form.processing ? 'Confirming...' : 'Confirm and continue' }}
                </button>
            </form>

            <div class="flex items-center justify-center gap-2 text-[11px] text-muted-foreground">
                <ShieldCheck class="size-3.5" />
                {{ t('ui.protected_account_action') }}
            </div>
        </div>
    </AuthLayout>
</template>
