<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { t } from '@/composables/useLanguage';
import { useForm } from '@inertiajs/vue3';
import { Save, ShieldCheck } from 'lucide-vue-next';
import type { SettingsPayload } from '../types';

const props = defineProps<{ settings: SettingsPayload['captcha'] }>();

const form = useForm({
    enabled: props.settings.enabled ?? false,
    provider: props.settings.provider ?? 'turnstile',
    site_key: props.settings.site_key ?? '',
    secret_key: '',
});

function save() {
    form.patch('/admin/settings/captcha', {
        preserveScroll: true,
        onSuccess: () => {
            form.secret_key = '';
            form.defaults();
        },
    });
}
</script>

<template>
    <form class="rounded-panel border border-foreground/[0.06] bg-surface p-5 sm:p-6" @submit.prevent="save">
        <div class="mb-5 flex items-center gap-3">
            <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                <ShieldCheck class="size-4 text-hive" />
            </div>
            <div>
                <h3 class="text-base font-semibold text-foreground">{{ t('ui.captcha_protection') }}</h3>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('ui.configure_the_bot_protection_provider_and_credentials') }}</p>
            </div>
        </div>

        <label class="mb-5 flex cursor-pointer items-center justify-between gap-4 rounded-button border border-border bg-surface-dark p-4">
            <span>
                <span class="block text-sm font-semibold text-foreground">{{ t('ui.enable_captcha') }}</span>
                <span class="mt-1 block text-xs text-muted-foreground">{{ t('ui.use_the_selected_provider_for_supported_forms') }}</span>
            </span>
            <input v-model="form.enabled" type="checkbox" class="size-5 rounded border-border bg-black text-hive focus:ring-hive" />
        </label>

        <div class="space-y-5">
            <div>
                <label for="captcha-provider" class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ t('ui.provider') }}</label>
                <select
                    id="captcha-provider"
                    v-model="form.provider"
                    class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm text-foreground outline-none focus:border-hive/50"
                >
                    <option value="turnstile">{{ t('ui.cloudflare_turnstile') }}</option>
                    <option value="recaptcha">{{ t('ui.google_recaptcha') }}</option>
                    <option value="hcaptcha">{{ t('ui.hcaptcha') }}</option>
                </select>
                <InputError :message="form.errors.provider" class="mt-2" />
            </div>
            <div>
                <label for="captcha-site-key" class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ t('ui.site_key') }}</label>
                <input
                    id="captcha-site-key"
                    v-model="form.site_key"
                    type="text"
                    autocomplete="off"
                    class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm text-foreground outline-none focus:border-hive/50"
                />
                <InputError :message="form.errors.site_key" class="mt-2" />
            </div>
            <div>
                <label for="captcha-secret-key" class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{
                    t('ui.secret_key')
                }}</label>
                <input
                    id="captcha-secret-key"
                    v-model="form.secret_key"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="t('ui.leave_blank_to_keep_the_saved_secret')"
                    class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm text-foreground outline-none focus:border-hive/50"
                />
                <InputError :message="form.errors.secret_key" class="mt-2" />
            </div>
        </div>

        <p class="mt-4 text-xs text-muted-foreground">{{ t('ui.saving_these_settings_does_not_by_itself_add_captcha_widgets_or_s') }}</p>

        <div class="mt-6 flex justify-end">
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center gap-2 rounded-button bg-hive px-5 py-2.5 text-sm font-bold text-black disabled:opacity-50"
            >
                <Save class="size-4" /> {{ form.processing ? 'Saving…' : 'Save captcha settings' }}
            </button>
        </div>
    </form>
</template>
