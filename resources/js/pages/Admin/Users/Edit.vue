<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Save, Shield, ShieldCheck, User } from 'lucide-vue-next'

interface Role {
    id: number | string
    name: string
    description?: string | null
}

interface UserData {
    id: number | string
    name: string
    email: string
    is_admin: boolean
    roles?: Role[]
}

const props = defineProps<{
    user: UserData
    roles: Role[]
}>()

const form = useForm({
    name: props.user.name ?? '',
    email: props.user.email ?? '',
    is_admin: props.user.is_admin ?? false,
    roles: (props.user.roles ?? []).map(role => role.id),
})

function submit() {
    form.patch(`/admin/users/${props.user.id}`, {
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout context="admin">
        <Head :title="`Edit ${user.name}`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-button bg-hive/10">
                                    <User class="size-5 text-hive" />
                                </div>

                                <div>
                                    <h1 class="text-2xl font-black">
                                        Edit User
                                    </h1>

                                    <p class="mt-1 text-sm text-zinc-500">
                                        Manage {{ user.name }}'s account details, roles and administrative access.
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="`/admin/users/${user.id}`"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2.5 text-sm font-bold text-zinc-300 transition hover:border-zinc-700 hover:text-white"
                            >
                                <ArrowLeft class="size-4" />
                                Back
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <section class="rounded-panel border border-zinc-800 bg-surface">
                            <div class="border-b border-zinc-800 px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-white/[0.03]">
                                        <User class="size-4 text-zinc-400" />
                                    </div>

                                    <div>
                                        <h2 class="text-base font-black text-white">
                                            Account
                                        </h2>

                                        <p class="mt-0.5 text-xs text-zinc-500">
                                            Update the user's personal account information.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                                <div>
                                    <label
                                        for="name"
                                        class="text-xs font-black uppercase tracking-wide text-zinc-500"
                                    >
                                        Name
                                    </label>

                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        autocomplete="name"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive/50"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.name"
                                    />
                                </div>

                                <div>
                                    <label
                                        for="email"
                                        class="text-xs font-black uppercase tracking-wide text-zinc-500"
                                    >
                                        Email address
                                    </label>

                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="email"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive/50"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.email"
                                    />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface">
                            <div class="border-b border-zinc-800 px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <Shield class="size-4 text-hive" />
                                    </div>

                                    <div>
                                        <h2 class="text-base font-black text-white">
                                            Super Administrator
                                        </h2>

                                        <p class="mt-0.5 text-xs text-zinc-500">
                                            Grant unrestricted access to the entire HivePanel administration area.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-5 sm:p-6">
                                <label
                                    class="flex cursor-pointer items-start gap-4 rounded-button border p-4 transition"
                                    :class="form.is_admin
                                        ? 'border-hive/30 bg-hive/[0.06]'
                                        : 'border-zinc-800 bg-[#0d0f11] hover:border-zinc-700'"
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

                                <InputError
                                    class="mt-2"
                                    :message="form.errors.is_admin"
                                />
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface">
                            <div class="border-b border-zinc-800 px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                                        <ShieldCheck class="size-4 text-hive" />
                                    </div>

                                    <div>
                                        <h2 class="text-base font-black text-white">
                                            Administrative Roles
                                        </h2>

                                        <p class="mt-0.5 text-xs text-zinc-500">
                                            Assign restricted administrative roles to this user.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-5 sm:p-6">
                                <div
                                    v-if="form.is_admin"
                                    class="mb-5 rounded-button border border-hive/20 bg-hive/[0.05] px-4 py-3 text-sm text-zinc-400"
                                >
                                    This user is currently a Super Administrator. Assigned roles can still be configured, but their permissions are bypassed while unrestricted access is enabled.
                                </div>

                                <div
                                    v-if="roles.length"
                                    class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                                >
                                    <label
                                        v-for="role in roles"
                                        :key="role.id"
                                        class="flex cursor-pointer items-start gap-3 rounded-button border p-4 transition"
                                        :class="form.roles.includes(role.id)
                                            ? 'border-hive/30 bg-hive/[0.05]'
                                            : 'border-zinc-800 bg-[#0d0f11] hover:border-zinc-700'"
                                    >
                                        <input
                                            v-model="form.roles"
                                            :value="role.id"
                                            type="checkbox"
                                            class="mt-0.5 size-4 shrink-0 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive"
                                        />

                                        <span class="min-w-0">
                                            <span class="block text-sm font-black text-zinc-200">
                                                {{ role.name }}
                                            </span>

                                            <span class="mt-1 block text-xs leading-5 text-zinc-500">
                                                {{ role.description || 'No description provided.' }}
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <div
                                    v-else
                                    class="rounded-button border border-dashed border-zinc-800 bg-[#0d0f11] px-5 py-8 text-center"
                                >
                                    <ShieldCheck class="mx-auto size-6 text-zinc-700" />

                                    <p class="mt-3 text-sm font-bold text-zinc-400">
                                        No administrative roles
                                    </p>

                                    <p class="mt-1 text-xs text-zinc-600">
                                        Create a role before assigning restricted administrative access.
                                    </p>

                                    <Link
                                        href="/admin/roles/create"
                                        class="mt-4 inline-flex rounded-button border border-zinc-800 bg-surface-light px-3.5 py-2 text-xs font-black text-zinc-300 transition hover:border-hive/30 hover:text-hive"
                                    >
                                        Create role
                                    </Link>
                                </div>

                                <InputError
                                    class="mt-2"
                                    :message="form.errors.roles"
                                />
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