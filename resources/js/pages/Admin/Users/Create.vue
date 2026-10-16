<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound, Mail, Save, Shield, ShieldCheck, UserPlus, UserRound } from 'lucide-vue-next';

interface Role {
    id: number | string;
    name: string;
    description?: string | null;
}

defineProps<{ roles: Role[] }>();

const form = useForm({
    name: '',
    email: '',
    method: 'invite' as 'invite' | 'password',
    password: '',
    password_confirmation: '',
    is_admin: false,
    roles: [] as (number | string)[],
});

function submit() {
    form.post('/admin/users', {
        preserveScroll: true,
        onFinish: () => {
            form.password = '';
            form.password_confirmation = '';
        },
    });
}
</script>

<template>
    <AppLayout context="admin">
        <Head :title="t('ui.create_user')" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="space-y-5">
                    <!-- Page header -->
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-button bg-hive/10">
                                    <UserPlus class="size-5 text-hive" />
                                </div>

                                <div>
                                    <h1 class="text-2xl font-black">{{ t('ui.create_user') }}</h1>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ t('ui.add_a_hivepanel_account_choose_how_it_is_set_up_and_assign_admini') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/users"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-light px-4 py-2.5 text-sm font-bold text-foreground transition hover:border-border hover:text-foreground"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back') }}
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <!-- Account -->
                        <section class="rounded-panel border border-border bg-surface">
                            <div class="border-b border-border px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-foreground/[0.03]">
                                        <UserRound class="size-4 text-muted-foreground" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-foreground">{{ t('ui.account') }}</h2>
                                        <p class="mt-0.5 text-xs text-muted-foreground">{{ t('ui.enter_the_new_user_s_account_information') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                                <div>
                                    <label for="name" class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                        t('ui.name')
                                    }}</label>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        autocomplete="name"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                    />
                                    <InputError :message="form.errors.name" class="mt-2" />
                                </div>

                                <div>
                                    <label for="email" class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                        t('ui.email_address')
                                    }}</label>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autocomplete="off"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                    />
                                    <InputError :message="form.errors.email" class="mt-2" />
                                </div>
                            </div>
                        </section>

                        <!-- Account setup -->
                        <section class="rounded-panel border border-border bg-surface">
                            <div class="border-b border-border px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <KeyRound class="size-4 text-hive" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-foreground">{{ t('ui.account_setup') }}</h2>
                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            {{ t('ui.choose_how_the_new_user_will_set_their_password') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-5 p-5 sm:p-6">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-button border p-4 transition"
                                        :class="
                                            form.method === 'invite'
                                                ? 'border-hive/30 bg-hive/[0.06]'
                                                : 'border-border bg-surface-dark hover:border-border'
                                        "
                                    >
                                        <input v-model="form.method" type="radio" value="invite" class="mt-0.5 size-4 shrink-0 accent-orange-500" />
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-2 text-sm font-black text-foreground">
                                                <Mail class="size-4 text-hive" />
                                                {{ t('ui.email_invitation') }}
                                            </span>
                                            <span class="mt-1 block text-xs leading-5 text-muted-foreground">
                                                {{ t('ui.send_a_secure_link_so_the_user_can_choose_their_password') }}
                                            </span>
                                        </span>
                                    </label>

                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-button border p-4 transition"
                                        :class="
                                            form.method === 'password'
                                                ? 'border-hive/30 bg-hive/[0.06]'
                                                : 'border-border bg-surface-dark hover:border-border'
                                        "
                                    >
                                        <input v-model="form.method" type="radio" value="password" class="mt-0.5 size-4 shrink-0 accent-orange-500" />
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-2 text-sm font-black text-foreground">
                                                <KeyRound class="size-4 text-hive" />
                                                {{ t('ui.set_password_manually') }}
                                            </span>
                                            <span class="mt-1 block text-xs leading-5 text-muted-foreground">
                                                {{ t('ui.create_the_account_using_an_administrator_defined_password') }}
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <p
                                    v-if="form.method === 'invite'"
                                    class="rounded-button border border-border bg-surface-dark px-4 py-3 text-xs leading-5 text-muted-foreground"
                                >
                                    {{ t('ui.hivepanel_will_send_an_invitation_using_the_configured_mail_servi') }}
                                </p>

                                <div v-else class="grid gap-5 lg:grid-cols-2">
                                    <div>
                                        <label for="password" class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.password')
                                        }}</label>
                                        <input
                                            id="password"
                                            v-model="form.password"
                                            type="password"
                                            autocomplete="new-password"
                                            required
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                        />
                                        <InputError :message="form.errors.password" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="password-confirmation" class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{
                                            t('ui.confirm_password')
                                        }}</label>
                                        <input
                                            id="password-confirmation"
                                            v-model="form.password_confirmation"
                                            type="password"
                                            autocomplete="new-password"
                                            required
                                            class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                        />
                                        <InputError :message="form.errors.password_confirmation" class="mt-2" />
                                    </div>
                                </div>
                                <InputError :message="form.errors.method" />
                            </div>
                        </section>

                        <!-- Super administrator -->
                        <section class="rounded-panel border border-border bg-surface">
                            <div class="border-b border-border px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <Shield class="size-4 text-hive" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-foreground">{{ t('ui.super_administrator') }}</h2>
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
                                <InputError :message="form.errors.is_admin" class="mt-2" />
                            </div>
                        </section>

                        <!-- Administrative roles -->
                        <section class="rounded-panel border border-border bg-surface">
                            <div class="border-b border-border px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <ShieldCheck class="size-4 text-hive" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-foreground">{{ t('ui.administrative_roles') }}</h2>
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
                                    {{ t('ui.this_user_will_be_a_super_administrator_assigned_roles_can_still_') }}
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
                                            <span class="block text-sm font-black text-foreground">{{ role.name }}</span>
                                            <span class="mt-1 block text-xs leading-5 text-muted-foreground">
                                                {{ role.description || 'No description provided.' }}
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <div v-else class="rounded-button border border-dashed border-border bg-surface-dark px-5 py-8 text-center">
                                    <ShieldCheck class="mx-auto size-6 text-muted-foreground" />
                                    <p class="mt-3 text-sm font-bold text-muted-foreground">{{ t('ui.no_administrative_roles') }}</p>
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

                                <InputError :message="form.errors.roles" class="mt-2" />
                            </div>
                        </section>

                        <!-- Form actions -->
                        <div class="flex flex-wrap justify-end gap-3">
                            <Link
                                href="/admin/users"
                                class="inline-flex items-center justify-center rounded-button border border-border bg-surface-light px-5 py-2.5 text-sm font-bold text-foreground transition hover:border-border hover:text-foreground"
                            >
                                {{ t('ui.cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-2.5 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save class="size-4" />
                                {{ form.processing ? 'Creating...' : 'Create user' }}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
