<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Shield, ShieldCheck, User } from 'lucide-vue-next';

interface Role {
    id: number | string;
    name: string;
    description?: string | null;
}

interface UserData {
    id: number | string;
    name: string;
    email: string;
    is_admin: boolean;
    roles?: Role[];
}

const props = defineProps<{
    user: UserData;
    roles: Role[];
}>();

const form = useForm({
    name: props.user.name ?? '',
    email: props.user.email ?? '',
    is_admin: props.user.is_admin ?? false,
    roles: (props.user.roles ?? []).map((role) => role.id),
});

function submit() {
    form.patch(`/admin/users/${props.user.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout context="admin">
        <Head :title="`Edit ${user.name}`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-button bg-hive/10">
                                    <User class="size-5 text-hive" />
                                </div>

                                <div>
                                    <h1 class="text-2xl font-black">
                                        {{ t('ui.edit_user') }}
                                    </h1>

                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ t('ui.manage') }} {{ user.name }}{{ t('ui.s_account_details_roles_and_administrative_access') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="`/admin/users/${user.id}`"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-light px-4 py-2.5 text-sm font-bold text-foreground transition hover:border-border hover:text-foreground"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back') }}
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <section class="rounded-panel border border-border bg-surface">
                            <div class="border-b border-border px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-foreground/[0.03]">
                                        <User class="size-4 text-muted-foreground" />
                                    </div>

                                    <div>
                                        <h2 class="text-base font-black text-foreground">
                                            {{ t('ui.account') }}
                                        </h2>

                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            {{ t('ui.update_the_user_s_personal_account_information') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                                <div>
                                    <label for="name" class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.name') }}
                                    </label>

                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        autocomplete="name"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>

                                <div>
                                    <label for="email" class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.email_address') }}
                                    </label>

                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="email"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface">
                            <div class="border-b border-border px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <Shield class="size-4 text-hive" />
                                    </div>

                                    <div>
                                        <h2 class="text-base font-black text-foreground">
                                            {{ t('ui.super_administrator') }}
                                        </h2>

                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            {{ t('ui.grant_unrestricted_access_to_the_entire_hivepanel_administration_') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-5 sm:p-6">
                                <label
                                    class="flex cursor-pointer items-start gap-4 rounded-button border p-4 transition"
                                    :class="form.is_admin ? 'border-hive/30 bg-hive/[0.06]' : 'border-border bg-surface-dark hover:border-border'"
                                >
                                    <input
                                        v-model="form.is_admin"
                                        type="checkbox"
                                        class="mt-0.5 size-4 shrink-0 rounded border-border bg-surface-dark text-hive focus:ring-hive"
                                    />

                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2 text-sm font-black text-foreground">
                                            {{ t('ui.grant_unrestricted_administrative_access') }}

                                            <span
                                                v-if="form.is_admin"
                                                class="rounded-full border border-hive/20 bg-hive/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-hive"
                                            >
                                                {{ t('ui.enabled') }}
                                            </span>
                                        </span>

                                        <span class="mt-1 block max-w-3xl text-xs leading-5 text-muted-foreground">
                                            {{ t('ui.super_administrators_bypass_all_administrative_permission_checks_') }}
                                        </span>
                                    </span>
                                </label>

                                <InputError class="mt-2" :message="form.errors.is_admin" />
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface">
                            <div class="border-b border-border px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <ShieldCheck class="size-4 text-hive" />
                                    </div>

                                    <div>
                                        <h2 class="text-base font-black text-foreground">
                                            {{ t('ui.administrative_roles') }}
                                        </h2>

                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            {{ t('ui.assign_restricted_administrative_roles_to_this_user') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-5 sm:p-6">
                                <div
                                    v-if="form.is_admin"
                                    class="mb-5 rounded-button border border-hive/20 bg-hive/[0.05] px-4 py-3 text-sm text-muted-foreground"
                                >
                                    {{ t('ui.this_user_is_currently_a_super_administrator_assigned_roles_can_s') }}
                                </div>

                                <div v-if="roles.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    <label
                                        v-for="role in roles"
                                        :key="role.id"
                                        class="flex cursor-pointer items-start gap-3 rounded-button border p-4 transition"
                                        :class="
                                            form.roles.includes(role.id)
                                                ? 'border-hive/30 bg-hive/[0.05]'
                                                : 'border-border bg-surface-dark hover:border-border'
                                        "
                                    >
                                        <input
                                            v-model="form.roles"
                                            :value="role.id"
                                            type="checkbox"
                                            class="mt-0.5 size-4 shrink-0 rounded border-border bg-surface-dark text-hive focus:ring-hive"
                                        />

                                        <span class="min-w-0">
                                            <span class="block text-sm font-black text-foreground">
                                                {{ role.name }}
                                            </span>

                                            <span class="mt-1 block text-xs leading-5 text-muted-foreground">
                                                {{ role.description || 'No description provided.' }}
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <div v-else class="rounded-button border border-dashed border-border bg-surface-dark px-5 py-8 text-center">
                                    <ShieldCheck class="mx-auto size-6 text-muted-foreground" />

                                    <p class="mt-3 text-sm font-bold text-muted-foreground">
                                        {{ t('ui.no_administrative_roles') }}
                                    </p>

                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ t('ui.create_a_role_before_assigning_restricted_administrative_access') }}
                                    </p>

                                    <Link
                                        href="/admin/roles/create"
                                        class="mt-4 inline-flex rounded-button border border-border bg-surface-light px-3.5 py-2 text-xs font-black text-foreground transition hover:border-hive/30 hover:text-hive"
                                    >
                                        {{ t('ui.create_role_221163d') }}
                                    </Link>
                                </div>

                                <InputError class="mt-2" :message="form.errors.roles" />
                            </div>
                        </section>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-2.5 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save class="size-4" />

                                {{ form.processing ? 'Saving...' : 'Save changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
