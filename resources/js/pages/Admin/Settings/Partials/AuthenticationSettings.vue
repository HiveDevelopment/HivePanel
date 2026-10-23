<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import { t } from '@/composables/useLanguage';
import { router, useForm } from '@inertiajs/vue3';
import { ExternalLink, KeyRound, Pencil, Plus, Save, ShieldCheck, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import type { OAuthProvider, OidcProvider } from '../types';

const { confirm } = useConfirm();

const props = defineProps<{
    oauthProviders: Record<string, OAuthProvider>;
    oidcProviders: OidcProvider[];
}>();

const providerLabels: Record<string, string> = {
    discord: 'Discord',
    google: 'Google',
    github: 'GitHub',
};

const providerHelp: Record<string, string> = {
    discord: 'Allow users to authenticate using their Discord account.',
    google: 'Allow users to authenticate using their Google account.',
    github: 'Allow users to authenticate using their GitHub account.',
};

const oauthForm = useForm({
    providers: {
        discord: {
            enabled: props.oauthProviders.discord?.enabled ?? false,
            client_id: props.oauthProviders.discord?.client_id ?? '',
            client_secret: '',
            redirect_url: props.oauthProviders.discord?.redirect_url ?? '',
        },
        google: {
            enabled: props.oauthProviders.google?.enabled ?? false,
            client_id: props.oauthProviders.google?.client_id ?? '',
            client_secret: '',
            redirect_url: props.oauthProviders.google?.redirect_url ?? '',
        },
        github: {
            enabled: props.oauthProviders.github?.enabled ?? false,
            client_id: props.oauthProviders.github?.client_id ?? '',
            client_secret: '',
            redirect_url: props.oauthProviders.github?.redirect_url ?? '',
        },
    },
});

const showOidcForm = ref(false);
const editingProvider = ref<OidcProvider | null>(null);

const oidcForm = useForm({
    name: '',
    enabled: true,
    issuer: '',
    client_id: '',
    client_secret: '',
    scopes: 'openid profile email',
    allow_registration: false,
});

function submitOAuth() {
    oauthForm.patch('/admin/settings/oauth', {
        preserveScroll: true,
        onSuccess: () => {
            oauthForm.providers.discord.client_secret = '';
            oauthForm.providers.google.client_secret = '';
            oauthForm.providers.github.client_secret = '';
        },
    });
}

function openCreateOidc() {
    editingProvider.value = null;

    oidcForm.reset();
    oidcForm.clearErrors();
    oidcForm.name = '';
    oidcForm.enabled = true;
    oidcForm.issuer = '';
    oidcForm.client_id = '';
    oidcForm.client_secret = '';
    oidcForm.scopes = 'openid profile email';
    oidcForm.allow_registration = false;

    showOidcForm.value = true;
}

function openEditOidc(provider: OidcProvider) {
    editingProvider.value = provider;

    oidcForm.clearErrors();
    oidcForm.name = provider.name;
    oidcForm.enabled = provider.enabled;
    oidcForm.issuer = provider.issuer;
    oidcForm.client_id = provider.client_id;
    oidcForm.client_secret = '';
    oidcForm.scopes = provider.scopes.join(' ');
    oidcForm.allow_registration = provider.allow_registration;

    showOidcForm.value = true;
}

function closeOidcForm() {
    showOidcForm.value = false;
    editingProvider.value = null;
    oidcForm.clearErrors();
}

function submitOidc() {
    const data = {
        ...oidcForm.data(),
        scopes: oidcForm.scopes.split(/\s+/).filter(Boolean),
    };

    if (editingProvider.value) {
        router.patch(`/admin/settings/oidc/${editingProvider.value.id}`, data, {
            preserveScroll: true,
            onSuccess: () => closeOidcForm(),
        });

        return;
    }

    router.post('/admin/settings/oidc', data, {
        preserveScroll: true,
        onSuccess: () => closeOidcForm(),
    });
}

async function deleteOidc(provider: OidcProvider) {
    const accepted = await confirm({
        title: t('dialogs.remove_provider'),
        description: t('dialogs.remove_provider_description', { name: provider.name }),
        confirmText: t('ui.remove'),
        danger: true,
    });
    if (!accepted) return;

    router.delete(`/admin/settings/oidc/${provider.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-5">
        <form class="space-y-5" @submit.prevent="submitOAuth">
            <section class="rounded-panel border border-foreground/[0.06] bg-surface p-5 sm:p-6">
                <div class="mb-6">
                    <div class="flex items-center gap-3">
                        <KeyRound class="size-5 text-hive" />

                        <div>
                            <h3 class="text-base font-semibold text-foreground">
                                {{ t('ui.social_login') }}
                            </h3>

                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.let_users_authenticate_using_an_existing_social_account') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="provider in ['discord', 'google', 'github']"
                        :key="provider"
                        class="rounded-xl border border-foreground/[0.06] bg-surface p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-semibold text-foreground">
                                        {{ providerLabels[provider] }}
                                    </h4>

                                    <span
                                        v-if="oauthForm.providers[provider].enabled"
                                        class="rounded-full border border-status-success/20 bg-status-success/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-status-success"
                                    >
                                        {{ t('ui.enabled') }}
                                    </span>
                                </div>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ providerHelp[provider] }}
                                </p>
                            </div>

                            <input
                                v-model="oauthForm.providers[provider].enabled"
                                type="checkbox"
                                class="size-5 shrink-0 rounded border-border bg-black text-hive focus:ring-hive"
                            />
                        </div>

                        <div
                            v-if="oauthForm.providers[provider].enabled"
                            class="mt-5 grid gap-4 border-t border-foreground/[0.05] pt-5 lg:grid-cols-2"
                        >
                            <div>
                                <label class="text-sm font-medium text-foreground">
                                    {{ t('ui.client_id') }}
                                </label>

                                <input
                                    v-model="oauthForm.providers[provider].client_id"
                                    type="text"
                                    class="mt-2 w-full rounded-lg border border-foreground/[0.07] bg-surface-dark px-3.5 py-2.5 text-sm text-foreground outline-none transition focus:border-hive/50"
                                />
                            </div>

                            <div>
                                <label class="text-sm font-medium text-foreground">
                                    {{ t('ui.client_secret') }}
                                </label>

                                <input
                                    v-model="oauthForm.providers[provider].client_secret"
                                    type="password"
                                    class="mt-2 w-full rounded-lg border border-foreground/[0.07] bg-surface-dark px-3.5 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                    :placeholder="t('ui.leave_blank_to_keep_existing_secret')"
                                />
                            </div>

                            <div class="lg:col-span-2">
                                <label class="text-sm font-medium text-foreground">
                                    {{ t('ui.callback_url') }}
                                </label>

                                <input
                                    v-model="oauthForm.providers[provider].redirect_url"
                                    type="url"
                                    class="mt-2 w-full rounded-lg border border-foreground/[0.07] bg-surface-dark px-3.5 py-2.5 text-sm text-muted-foreground outline-none transition focus:border-hive/50"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="submit"
                        :disabled="oauthForm.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light disabled:opacity-50"
                    >
                        <Save class="size-4" />
                        {{ oauthForm.processing ? 'Saving...' : 'Save social providers' }}
                    </button>
                </div>
            </section>
        </form>

        <section class="rounded-panel border border-foreground/[0.06] bg-surface p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex gap-3">
                    <ShieldCheck class="mt-0.5 size-5 shrink-0 text-hive" />

                    <div>
                        <h3 class="text-base font-semibold text-foreground">
                            {{ t('ui.openid_connect') }}
                        </h3>

                        <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                            {{ t('ui.connect_hivepanel_to_microsoft_entra_id_authentik_keycloak_okta_o') }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-hive/30 bg-hive/10 px-3.5 py-2 text-sm font-semibold text-hive transition hover:bg-hive/15"
                    @click="openCreateOidc"
                >
                    <Plus class="size-4" />
                    {{ t('ui.add_provider') }}
                </button>
            </div>

            <div v-if="props.oidcProviders.length" class="mt-6 space-y-3">
                <div
                    v-for="provider in props.oidcProviders"
                    :key="provider.id"
                    class="flex flex-col gap-4 rounded-xl border border-foreground/[0.06] bg-surface p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-foreground">
                                {{ provider.name }}
                            </span>

                            <span
                                :class="
                                    provider.enabled
                                        ? 'border-status-success/20 bg-status-success/10 text-status-success'
                                        : 'border-foreground/[0.08] bg-foreground/[0.03] text-muted-foreground'
                                "
                                class="rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                            >
                                {{ provider.enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>

                        <div class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                            <ExternalLink class="size-3.5 shrink-0" />
                            <span class="truncate">
                                {{ provider.issuer }}
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-foreground/[0.07] bg-foreground/[0.03] px-3 py-2 text-sm font-medium text-foreground transition hover:bg-foreground/[0.06] hover:text-foreground"
                            @click="openEditOidc(provider)"
                        >
                            <Pencil class="size-3.5" />
                            {{ t('ui.edit') }}
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-status-danger/20 bg-status-danger/5 px-3 py-2 text-sm font-medium text-status-danger transition hover:bg-status-danger/10"
                            @click="deleteOidc(provider)"
                        >
                            <Trash2 class="size-3.5" />
                            {{ t('ui.remove') }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="mt-6 rounded-xl border border-dashed border-foreground/[0.08] px-6 py-10 text-center">
                <ShieldCheck class="mx-auto size-7 text-muted-foreground" />

                <h4 class="mt-3 text-sm font-medium text-foreground">
                    {{ t('ui.no_openid_connect_providers') }}
                </h4>

                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('ui.add_a_provider_to_offer_organisation_or_enterprise_sso') }}
                </p>
            </div>
        </section>

        <div
            v-if="showOidcForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            @click.self="closeOidcForm"
        >
            <div class="w-full max-w-xl rounded-2xl border border-foreground/[0.08] bg-[#171a1e] shadow-2xl">
                <div class="flex items-start justify-between border-b border-foreground/[0.06] p-5">
                    <div>
                        <h3 class="text-lg font-semibold text-foreground">
                            {{ editingProvider ? 'Edit OpenID Connect provider' : 'Add OpenID Connect provider' }}
                        </h3>

                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ t('ui.hivepanel_will_use_the_issuer_s_discovery_configuration') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg p-2 text-muted-foreground transition hover:bg-foreground/[0.05] hover:text-foreground"
                        @click="closeOidcForm"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <form class="p-5" @submit.prevent="submitOidc">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-foreground">
                                {{ t('ui.display_name') }}
                            </label>

                            <input
                                v-model="oidcForm.name"
                                type="text"
                                required
                                class="mt-2 w-full rounded-lg border border-foreground/[0.07] bg-surface-dark px-3.5 py-2.5 text-sm text-foreground outline-none transition focus:border-hive/50"
                                :placeholder="t('ui.microsoft_entra_id')"
                            />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-foreground">
                                {{ t('ui.issuer_url') }}
                            </label>

                            <input
                                v-model="oidcForm.issuer"
                                type="url"
                                required
                                class="mt-2 w-full rounded-lg border border-foreground/[0.07] bg-surface-dark px-3.5 py-2.5 text-sm text-foreground outline-none transition focus:border-hive/50"
                                placeholder="https://login.example.com/application/o/hivepanel/"
                            />

                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ t('ui.the_issuer_must_expose_a_valid_openid_connect_discovery_document') }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-foreground">
                                {{ t('ui.client_id') }}
                            </label>

                            <input
                                v-model="oidcForm.client_id"
                                type="text"
                                required
                                class="mt-2 w-full rounded-lg border border-foreground/[0.07] bg-surface-dark px-3.5 py-2.5 text-sm text-foreground outline-none transition focus:border-hive/50"
                            />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-foreground">
                                {{ t('ui.client_secret') }}
                            </label>

                            <input
                                v-model="oidcForm.client_secret"
                                type="password"
                                :required="!editingProvider"
                                class="mt-2 w-full rounded-lg border border-foreground/[0.07] bg-surface-dark px-3.5 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                :placeholder="editingProvider ? 'Leave blank to keep existing secret' : 'Client secret'"
                            />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-foreground">
                                {{ t('ui.scopes') }}
                            </label>

                            <input
                                v-model="oidcForm.scopes"
                                type="text"
                                class="mt-2 w-full rounded-lg border border-foreground/[0.07] bg-surface-dark px-3.5 py-2.5 text-sm text-foreground outline-none transition focus:border-hive/50"
                                :placeholder="t('ui.openid_profile_email')"
                            />
                        </div>

                        <label
                            class="flex cursor-pointer items-center justify-between gap-6 rounded-xl border border-foreground/[0.06] bg-surface p-4"
                        >
                            <div>
                                <div class="text-sm font-medium text-foreground">
                                    {{ t('ui.allow_account_creation') }}
                                </div>

                                <div class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.create_a_hivepanel_account_when_an_authenticated_oidc_user_does_n') }}
                                </div>
                            </div>

                            <input
                                v-model="oidcForm.allow_registration"
                                type="checkbox"
                                class="size-5 shrink-0 rounded border-border bg-black text-hive focus:ring-hive"
                            />
                        </label>

                        <label
                            v-if="editingProvider"
                            class="flex cursor-pointer items-center justify-between gap-6 rounded-xl border border-foreground/[0.06] bg-surface p-4"
                        >
                            <div>
                                <div class="text-sm font-medium text-foreground">
                                    {{ t('ui.provider_enabled') }}
                                </div>

                                <div class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.allow_this_provider_to_appear_on_the_hivepanel_login_page') }}
                                </div>
                            </div>

                            <input
                                v-model="oidcForm.enabled"
                                type="checkbox"
                                class="size-5 shrink-0 rounded border-border bg-black text-hive focus:ring-hive"
                            />
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-foreground/[0.07] px-4 py-2.5 text-sm font-medium text-foreground transition hover:bg-foreground/[0.04]"
                            @click="closeOidcForm"
                        >
                            {{ t('ui.cancel') }}
                        </button>

                        <button type="submit" class="rounded-lg bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light">
                            {{ editingProvider ? 'Save provider' : 'Add provider' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
