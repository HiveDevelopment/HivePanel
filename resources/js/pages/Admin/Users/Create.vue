<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
    ArrowLeft,
    KeyRound,
    Mail,
    Save,
    Shield,
    ShieldCheck,
    UserPlus,
    UserRound,
} from 'lucide-vue-next'

interface Role {
    id: number | string
    name: string
    description?: string | null
}

defineProps<{ roles: Role[] }>()

const form = useForm({
    name: '',
    email: '',
    method: 'invite' as 'invite' | 'password',
    password: '',
    password_confirmation: '',
    is_admin: false,
    roles: [] as (number | string)[],
})

function submit() {
    form.post('/admin/users', {
        preserveScroll: true,
        onFinish: () => {
            form.password = ''
            form.password_confirmation = ''
        },
    })
}
</script>

<template>
    <AppLayout context="admin">
        <Head title="Create User" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="space-y-5">
                    <!-- Page header -->
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-button bg-hive/10">
                                    <UserPlus class="size-5 text-hive" />
                                </div>

                                <div>
                                    <h1 class="text-2xl font-black">Create User</h1>
                                    <p class="mt-1 text-sm text-zinc-500">
                                        Add a HivePanel account, choose how it is set up and assign administrative access.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/users"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2.5 text-sm font-bold text-zinc-300 transition hover:border-zinc-700 hover:text-white"
                            >
                                <ArrowLeft class="size-4" />
                                Back
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <!-- Account -->
                        <section class="rounded-panel border border-zinc-800 bg-surface">
                            <div class="border-b border-zinc-800 px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-white/[0.03]">
                                        <UserRound class="size-4 text-zinc-400" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-white">Account</h2>
                                        <p class="mt-0.5 text-xs text-zinc-500">Enter the new user's account information.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                                <div>
                                    <label for="name" class="text-xs font-black uppercase tracking-wide text-zinc-500">Name</label>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        autocomplete="name"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive/50"
                                    />
                                    <InputError :message="form.errors.name" class="mt-2" />
                                </div>

                                <div>
                                    <label for="email" class="text-xs font-black uppercase tracking-wide text-zinc-500">Email address</label>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autocomplete="off"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive/50"
                                    />
                                    <InputError :message="form.errors.email" class="mt-2" />
                                </div>
                            </div>
                        </section>

                        <!-- Account setup -->
                        <section class="rounded-panel border border-zinc-800 bg-surface">
                            <div class="border-b border-zinc-800 px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <KeyRound class="size-4 text-hive" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-white">Account Setup</h2>
                                        <p class="mt-0.5 text-xs text-zinc-500">Choose how the new user will set their password.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-5 p-5 sm:p-6">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-button border p-4 transition"
                                        :class="form.method === 'invite' ? 'border-hive/30 bg-hive/[0.06]' : 'border-zinc-800 bg-[#0d0f11] hover:border-zinc-700'"
                                    >
                                        <input v-model="form.method" type="radio" value="invite" class="mt-0.5 size-4 shrink-0 accent-orange-500" />
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-2 text-sm font-black text-white">
                                                <Mail class="size-4 text-hive" />
                                                Email invitation
                                            </span>
                                            <span class="mt-1 block text-xs leading-5 text-zinc-500">
                                                Send a secure link so the user can choose their password.
                                            </span>
                                        </span>
                                    </label>

                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-button border p-4 transition"
                                        :class="form.method === 'password' ? 'border-hive/30 bg-hive/[0.06]' : 'border-zinc-800 bg-[#0d0f11] hover:border-zinc-700'"
                                    >
                                        <input v-model="form.method" type="radio" value="password" class="mt-0.5 size-4 shrink-0 accent-orange-500" />
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-2 text-sm font-black text-white">
                                                <KeyRound class="size-4 text-hive" />
                                                Set password manually
                                            </span>
                                            <span class="mt-1 block text-xs leading-5 text-zinc-500">
                                                Create the account using an administrator-defined password.
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <p v-if="form.method === 'invite'" class="rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-xs leading-5 text-zinc-400">
                                    HivePanel will send an invitation using the configured mail service. The password setup link expires after 60 minutes.
                                </p>

                                <div v-else class="grid gap-5 lg:grid-cols-2">
                                    <div>
                                        <label for="password" class="text-xs font-black uppercase tracking-wide text-zinc-500">Password</label>
                                        <input
                                            id="password"
                                            v-model="form.password"
                                            type="password"
                                            autocomplete="new-password"
                                            required
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive/50"
                                        />
                                        <InputError :message="form.errors.password" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="password-confirmation" class="text-xs font-black uppercase tracking-wide text-zinc-500">Confirm password</label>
                                        <input
                                            id="password-confirmation"
                                            v-model="form.password_confirmation"
                                            type="password"
                                            autocomplete="new-password"
                                            required
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive/50"
                                        />
                                        <InputError :message="form.errors.password_confirmation" class="mt-2" />
                                    </div>
                                </div>
                                <InputError :message="form.errors.method" />
                            </div>
                        </section>

                        <!-- Super administrator -->
                        <section class="rounded-panel border border-zinc-800 bg-surface">
                            <div class="border-b border-zinc-800 px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <Shield class="size-4 text-hive" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-white">Super Administrator</h2>
                                        <p class="mt-0.5 text-xs text-zinc-500">Grant unrestricted access to the entire HivePanel administration area.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-5 sm:p-6">
                                <label
                                    class="flex cursor-pointer items-start gap-4 rounded-button border p-4 transition"
                                    :class="form.is_admin ? 'border-hive/30 bg-hive/[0.06]' : 'border-zinc-800 bg-[#0d0f11] hover:border-zinc-700'"
                                >
                                    <input
                                        v-model="form.is_admin"
                                        type="checkbox"
                                        class="mt-0.5 size-4 shrink-0 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive"
                                    />
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2 text-sm font-black text-white">
                                            Grant unrestricted administrative access
                                            <span
                                                v-if="form.is_admin"
                                                class="rounded-full border border-hive/20 bg-hive/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-hive"
                                            >
                                                Enabled
                                            </span>
                                        </span>
                                        <span class="mt-1 block max-w-3xl text-xs leading-5 text-zinc-500">
                                            Super administrators bypass all administrative permission checks and can access users, roles, infrastructure, security settings and panel updates.
                                        </span>
                                    </span>
                                </label>
                                <InputError :message="form.errors.is_admin" class="mt-2" />
                            </div>
                        </section>

                        <!-- Administrative roles -->
                        <section class="rounded-panel border border-zinc-800 bg-surface">
                            <div class="border-b border-zinc-800 px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <ShieldCheck class="size-4 text-hive" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-white">Administrative Roles</h2>
                                        <p class="mt-0.5 text-xs text-zinc-500">Assign restricted administrative roles to this user.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-5 sm:p-6">
                                <div
                                    v-if="form.is_admin"
                                    class="mb-5 rounded-button border border-hive/20 bg-hive/[0.05] px-4 py-3 text-sm text-zinc-400"
                                >
                                    This user will be a Super Administrator. Assigned roles can still be configured, but their permissions will be bypassed while unrestricted access is enabled.
                                </div>

                                <div v-if="roles.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    <label
                                        v-for="role in roles"
                                        :key="role.id"
                                        class="flex cursor-pointer items-start gap-3 rounded-button border p-4 transition"
                                        :class="form.roles.includes(role.id) ? 'border-hive/30 bg-hive/[0.05]' : 'border-zinc-800 bg-[#0d0f11] hover:border-zinc-700'"
                                    >
                                        <input
                                            v-model="form.roles"
                                            :value="role.id"
                                            type="checkbox"
                                            class="mt-0.5 size-4 shrink-0 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive"
                                        />
                                        <span class="min-w-0">
                                            <span class="block text-sm font-black text-zinc-200">{{ role.name }}</span>
                                            <span class="mt-1 block text-xs leading-5 text-zinc-500">
                                                {{ role.description || 'No description provided.' }}
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <div v-else class="rounded-button border border-dashed border-zinc-800 bg-[#0d0f11] px-5 py-8 text-center">
                                    <ShieldCheck class="mx-auto size-6 text-zinc-700" />
                                    <p class="mt-3 text-sm font-bold text-zinc-400">No administrative roles</p>
                                    <p class="mt-1 text-xs text-zinc-600">Create a role before assigning restricted administrative access.</p>
                                    <Link
                                        href="/admin/roles/create"
                                        class="mt-4 inline-flex rounded-button border border-zinc-800 bg-surface-light px-3.5 py-2 text-xs font-black text-zinc-300 transition hover:border-hive/30 hover:text-hive"
                                    >
                                        Create role
                                    </Link>
                                </div>

                                <InputError :message="form.errors.roles" class="mt-2" />
                            </div>
                        </section>

                        <!-- Form actions -->
                        <div class="flex flex-wrap justify-end gap-3">
                            <Link
                                href="/admin/users"
                                class="inline-flex items-center justify-center rounded-button border border-zinc-800 bg-surface-light px-5 py-2.5 text-sm font-bold text-zinc-300 transition hover:border-zinc-700 hover:text-white"
                            >
                                Cancel
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
