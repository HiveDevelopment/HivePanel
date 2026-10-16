<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Building2, KeyRound, Lock, Mail, Settings, ShieldCheck, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AISettings from './Partials/AISettings.vue';
import AuthenticationSettings from './Partials/AuthenticationSettings.vue';
import CaptchaSettings from './Partials/CaptchaSettings.vue';
import GeneralSettings from './Partials/GeneralSettings.vue';
import InvitationSettings from './Partials/InvitationSettings.vue';
import MailSettings from './Partials/MailSettings.vue';
import SecuritySettings from './Partials/SecuritySettings.vue';
import type { OAuthProvider, OidcProvider, SettingsPayload } from './types';

defineProps<{
    settings: SettingsPayload;
    oauthProviders: Record<string, OAuthProvider>;
    oidcProviders: OidcProvider[];
}>();

const tabs = [
    {
        key: 'general',
        label: 'General',
        description: 'Branding and localisation',
        icon: Building2,
    },
    {
        key: 'security',
        label: 'Security',
        description: 'Authentication policies',
        icon: Lock,
    },
    { key: 'invitations', label: 'Invitations', description: 'New user email templates', icon: Mail },
    { key: 'ai', label: 'Hive AI', description: 'AI providers and models', icon: Sparkles },
    {
        key: 'mail',
        label: 'Mail',
        description: 'Outgoing email',
        icon: Mail,
    },
    {
        key: 'captcha',
        label: 'Captcha',
        description: 'Bot protection',
        icon: ShieldCheck,
    },
    {
        key: 'authentication',
        label: 'Authentication',
        description: 'Social login and SSO',
        icon: KeyRound,
    },
] as const;

const activeTab = ref<(typeof tabs)[number]['key']>('general');

const activeTabDetails = computed(() => {
    return tabs.find((tab) => tab.key === activeTab.value) ?? tabs[0];
});
</script>

<template>
    <AppLayout context="admin">
        <Head :title="t('ui.admin_settings')" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-foreground/[0.06] bg-surface p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-hive/10">
                                <Settings class="size-5 text-hive" />
                            </div>

                            <div>
                                <h1 class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                                    {{ t('Settings') }}
                                </h1>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.configure_your_hivepanel_installation') }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-5 xl:grid-cols-[260px_1fr]">
                        <aside class="h-fit rounded-panel border border-foreground/[0.06] bg-surface p-2">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left transition"
                                :class="
                                    activeTab === tab.key
                                        ? 'bg-hive/10 text-foreground'
                                        : 'text-muted-foreground hover:bg-foreground/[0.03] hover:text-foreground'
                                "
                                @click="activeTab = tab.key"
                            >
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                                    :class="activeTab === tab.key ? 'bg-hive/15 text-hive' : 'bg-foreground/[0.03] text-muted-foreground'"
                                >
                                    <component :is="tab.icon" class="size-4" />
                                </div>

                                <div class="min-w-0">
                                    <div class="text-sm font-medium">
                                        {{ tab.label }}
                                    </div>

                                    <div
                                        class="mt-0.5 truncate text-xs"
                                        :class="activeTab === tab.key ? 'text-muted-foreground' : 'text-muted-foreground'"
                                    >
                                        {{ tab.description }}
                                    </div>
                                </div>
                            </button>
                        </aside>

                        <div class="min-w-0 space-y-5">
                            <section class="rounded-panel border border-foreground/[0.06] bg-surface px-5 py-4">
                                <h2 class="text-lg font-semibold text-foreground">
                                    {{ activeTabDetails.label }}
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ activeTabDetails.description }}
                                </p>
                            </section>

                            <GeneralSettings v-if="activeTab === 'general'" :settings="settings.general" />

                            <SecuritySettings
                                v-if="activeTab === 'security'"
                                :settings="settings.security"
                                :require-two-factor="settings.general.require_2fa"
                            />

                            <AISettings v-if="activeTab === 'ai'" :settings="settings.ai" />

                            <InvitationSettings v-if="activeTab === 'invitations'" :settings="settings.invitations" />

                            <AuthenticationSettings
                                v-if="activeTab === 'authentication'"
                                :oauth-providers="oauthProviders"
                                :oidc-providers="oidcProviders"
                            />

                            <MailSettings v-if="activeTab === 'mail'" :settings="settings.mail" />

                            <CaptchaSettings v-if="activeTab === 'captcha'" :settings="settings.captcha" />
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
