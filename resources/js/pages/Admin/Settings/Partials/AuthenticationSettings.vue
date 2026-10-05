<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import {
    ExternalLink,
    KeyRound,
    Pencil,
    Plus,
    Save,
    ShieldCheck,
    Trash2,
    X,
} from 'lucide-vue-next'
import { ref } from 'vue'
import type { OAuthProvider, OidcProvider } from '../types'

const props = defineProps<{
    oauthProviders: Record<string, OAuthProvider>
    oidcProviders: OidcProvider[]
}>()

const providerLabels: Record<string, string> = {
    discord: 'Discord',
    google: 'Google',
    github: 'GitHub',
}

const providerHelp: Record<string, string> = {
    discord: 'Allow users to authenticate using their Discord account.',
    google: 'Allow users to authenticate using their Google account.',
    github: 'Allow users to authenticate using their GitHub account.',
}

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
})

const showOidcForm = ref(false)
const editingProvider = ref<OidcProvider | null>(null)

const oidcForm = useForm({
    name: '',
    enabled: true,
    issuer: '',
    client_id: '',
    client_secret: '',
    scopes: 'openid profile email',
    allow_registration: false,
})

function submitOAuth() {
    oauthForm.patch('/admin/settings/oauth', {
        preserveScroll: true,
        onSuccess: () => {
            oauthForm.providers.discord.client_secret = ''
            oauthForm.providers.google.client_secret = ''
            oauthForm.providers.github.client_secret = ''
        },
    })
}

function openCreateOidc() {
    editingProvider.value = null

    oidcForm.reset()
    oidcForm.clearErrors()
    oidcForm.name = ''
    oidcForm.enabled = true
    oidcForm.issuer = ''
    oidcForm.client_id = ''
    oidcForm.client_secret = ''
    oidcForm.scopes = 'openid profile email'
    oidcForm.allow_registration = false

    showOidcForm.value = true
}

function openEditOidc(provider: OidcProvider) {
    editingProvider.value = provider

    oidcForm.clearErrors()
    oidcForm.name = provider.name
    oidcForm.enabled = provider.enabled
    oidcForm.issuer = provider.issuer
    oidcForm.client_id = provider.client_id
    oidcForm.client_secret = ''
    oidcForm.scopes = provider.scopes.join(' ')
    oidcForm.allow_registration = provider.allow_registration

    showOidcForm.value = true
}

function closeOidcForm() {
    showOidcForm.value = false
    editingProvider.value = null
    oidcForm.clearErrors()
}

function submitOidc() {
    const data = {
        ...oidcForm.data(),
        scopes: oidcForm.scopes.split(/\s+/).filter(Boolean),
    }

    if (editingProvider.value) {
        router.patch(`/admin/settings/oidc/${editingProvider.value.id}`, data, {
            preserveScroll: true,
            onSuccess: () => closeOidcForm(),
        })

        return
    }

    router.post('/admin/settings/oidc', data, {
        preserveScroll: true,
        onSuccess: () => closeOidcForm(),
    })
}

function deleteOidc(provider: OidcProvider) {
    if (!window.confirm(`Remove ${provider.name}? Users will no longer be able to sign in using this provider.`)) {
        return
    }

    router.delete(`/admin/settings/oidc/${provider.id}`, {
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="space-y-5">
        <form class="space-y-5" @submit.prevent="submitOAuth">
            <section class="rounded-panel border border-white/[0.06] bg-surface p-5 sm:p-6">
                <div class="mb-6">
                    <div class="flex items-center gap-3">
                        <KeyRound class="size-5 text-hive" />

                        <div>
                            <h3 class="text-base font-semibold text-white">
                                Social login
                            </h3>

                            <p class="mt-1 text-sm text-zinc-500">
                                Let users authenticate using an existing social account.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="provider in ['discord', 'google', 'github']"
                        :key="provider"
                        class="rounded-xl border border-white/[0.06] bg-[#111417] p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-semibold text-white">
                                        {{ providerLabels[provider] }}
                                    </h4>

                                    <span
                                        v-if="oauthForm.providers[provider].enabled"
                                        class="rounded-full border border-status-success/20 bg-status-success/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-status-success"
                                    >
                                        Enabled
                                    </span>
                                </div>

                                <p class="mt-1 text-sm text-zinc-500">
                                    {{ providerHelp[provider] }}
                                </p>
                            </div>

                            <input
                                v-model="oauthForm.providers[provider].enabled"
                                type="checkbox"
                                class="size-5 shrink-0 rounded border-zinc-700 bg-black text-hive focus:ring-hive"
                            />
                        </div>

                        <div
                            v-if="oauthForm.providers[provider].enabled"
                            class="mt-5 grid gap-4 border-t border-white/[0.05] pt-5 lg:grid-cols-2"
                        >
                            <div>
                                <label class="text-sm font-medium text-zinc-300">
                                    Client ID
                                </label>

                                <input
                                    v-model="oauthForm.providers[provider].client_id"
                                    type="text"
                                    class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#0b0d0f] px-3.5 py-2.5 text-sm text-white outline-none transition focus:border-hive/50"
                                />
                            </div>

                            <div>
                                <label class="text-sm font-medium text-zinc-300">
                                    Client secret
                                </label>

                                <input
                                    v-model="oauthForm.providers[provider].client_secret"
                                    type="password"
                                    class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#0b0d0f] px-3.5 py-2.5 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                                    placeholder="Leave blank to keep existing secret"
                                />
                            </div>

                            <div class="lg:col-span-2">
                                <label class="text-sm font-medium text-zinc-300">
                                    Callback URL
                                </label>

                                <input
                                    v-model="oauthForm.providers[provider].redirect_url"
                                    type="url"
                                    class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#0b0d0f] px-3.5 py-2.5 text-sm text-zinc-400 outline-none transition focus:border-hive/50"
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

        <section class="rounded-panel border border-white/[0.06] bg-surface p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex gap-3">
                    <ShieldCheck class="mt-0.5 size-5 shrink-0 text-hive" />

                    <div>
                        <h3 class="text-base font-semibold text-white">
                            OpenID Connect
                        </h3>

                        <p class="mt-1 max-w-2xl text-sm text-zinc-500">
                            Connect HivePanel to Microsoft Entra ID, Authentik, Keycloak, Okta or another standards-compliant OpenID Connect provider.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-hive/30 bg-hive/10 px-3.5 py-2 text-sm font-semibold text-hive transition hover:bg-hive/15"
                    @click="openCreateOidc"
                >
                    <Plus class="size-4" />
                    Add provider
                </button>
            </div>

            <div v-if="props.oidcProviders.length" class="mt-6 space-y-3">
                <div
                    v-for="provider in props.oidcProviders"
                    :key="provider.id"
                    class="flex flex-col gap-4 rounded-xl border border-white/[0.06] bg-[#111417] p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-white">
                                {{ provider.name }}
                            </span>

                            <span
                                :class="provider.enabled
                                    ? 'border-status-success/20 bg-status-success/10 text-status-success'
                                    : 'border-white/[0.08] bg-white/[0.03] text-zinc-500'"
                                class="rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                            >
                                {{ provider.enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>

                        <div class="mt-1 flex items-center gap-1.5 text-sm text-zinc-500">
                            <ExternalLink class="size-3.5 shrink-0" />
                            <span class="truncate">
                                {{ provider.issuer }}
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-white/[0.07] bg-white/[0.03] px-3 py-2 text-sm font-medium text-zinc-300 transition hover:bg-white/[0.06] hover:text-white"
                            @click="openEditOidc(provider)"
                        >
                            <Pencil class="size-3.5" />
                            Edit
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-status-danger/20 bg-status-danger/5 px-3 py-2 text-sm font-medium text-status-danger transition hover:bg-status-danger/10"
                            @click="deleteOidc(provider)"
                        >
                            <Trash2 class="size-3.5" />
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-6 rounded-xl border border-dashed border-white/[0.08] px-6 py-10 text-center"
            >
                <ShieldCheck class="mx-auto size-7 text-zinc-700" />

                <h4 class="mt-3 text-sm font-medium text-zinc-300">
                    No OpenID Connect providers
                </h4>

                <p class="mt-1 text-sm text-zinc-600">
                    Add a provider to offer organisation or enterprise SSO.
                </p>
            </div>
        </section>

        <div
            v-if="showOidcForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            @click.self="closeOidcForm"
        >
            <div class="w-full max-w-xl rounded-2xl border border-white/[0.08] bg-[#171a1e] shadow-2xl">
                <div class="flex items-start justify-between border-b border-white/[0.06] p-5">
                    <div>
                        <h3 class="text-lg font-semibold text-white">
                            {{ editingProvider ? 'Edit OpenID Connect provider' : 'Add OpenID Connect provider' }}
                        </h3>

                        <p class="mt-1 text-sm text-zinc-500">
                            HivePanel will use the issuer's discovery configuration.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 transition hover:bg-white/[0.05] hover:text-white"
                        @click="closeOidcForm"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <form class="p-5" @submit.prevent="submitOidc">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-zinc-300">
                                Display name
                            </label>

                            <input
                                v-model="oidcForm.name"
                                type="text"
                                required
                                class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#0b0d0f] px-3.5 py-2.5 text-sm text-white outline-none transition focus:border-hive/50"
                                placeholder="Microsoft Entra ID"
                            />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-zinc-300">
                                Issuer URL
                            </label>

                            <input
                                v-model="oidcForm.issuer"
                                type="url"
                                required
                                class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#0b0d0f] px-3.5 py-2.5 text-sm text-white outline-none transition focus:border-hive/50"
                                placeholder="https://login.example.com/application/o/hivepanel/"
                            />

                            <p class="mt-2 text-xs text-zinc-600">
                                The issuer must expose a valid OpenID Connect discovery document.
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-zinc-300">
                                Client ID
                            </label>

                            <input
                                v-model="oidcForm.client_id"
                                type="text"
                                required
                                class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#0b0d0f] px-3.5 py-2.5 text-sm text-white outline-none transition focus:border-hive/50"
                            />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-zinc-300">
                                Client secret
                            </label>

                            <input
                                v-model="oidcForm.client_secret"
                                type="password"
                                :required="!editingProvider"
                                class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#0b0d0f] px-3.5 py-2.5 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                                :placeholder="editingProvider ? 'Leave blank to keep existing secret' : 'Client secret'"
                            />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-zinc-300">
                                Scopes
                            </label>

                            <input
                                v-model="oidcForm.scopes"
                                type="text"
                                class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#0b0d0f] px-3.5 py-2.5 text-sm text-white outline-none transition focus:border-hive/50"
                                placeholder="openid profile email"
                            />
                        </div>

                        <label class="flex cursor-pointer items-center justify-between gap-6 rounded-xl border border-white/[0.06] bg-[#111417] p-4">
                            <div>
                                <div class="text-sm font-medium text-white">
                                    Allow account creation
                                </div>

                                <div class="mt-1 text-sm text-zinc-500">
                                    Create a HivePanel account when an authenticated OIDC user does not already have one.
                                </div>
                            </div>

                            <input
                                v-model="oidcForm.allow_registration"
                                type="checkbox"
                                class="size-5 shrink-0 rounded border-zinc-700 bg-black text-hive focus:ring-hive"
                            />
                        </label>

                        <label
                            v-if="editingProvider"
                            class="flex cursor-pointer items-center justify-between gap-6 rounded-xl border border-white/[0.06] bg-[#111417] p-4"
                        >
                            <div>
                                <div class="text-sm font-medium text-white">
                                    Provider enabled
                                </div>

                                <div class="mt-1 text-sm text-zinc-500">
                                    Allow this provider to appear on the HivePanel login page.
                                </div>
                            </div>

                            <input
                                v-model="oidcForm.enabled"
                                type="checkbox"
                                class="size-5 shrink-0 rounded border-zinc-700 bg-black text-hive focus:ring-hive"
                            />
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-white/[0.07] px-4 py-2.5 text-sm font-medium text-zinc-300 transition hover:bg-white/[0.04]"
                            @click="closeOidcForm"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="rounded-lg bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light"
                        >
                            {{ editingProvider ? 'Save provider' : 'Add provider' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>