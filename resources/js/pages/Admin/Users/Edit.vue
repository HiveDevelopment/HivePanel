<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Save, Shield, ShieldCheck, User } from 'lucide-vue-next'

const props = defineProps<{
    user: any
    roles: any[]
}>()

const form = useForm({
    name: props.user.name ?? '',
    email: props.user.email ?? '',
    is_admin: props.user.is_admin ?? false,
    roles: (props.user.roles ?? []).map((role: any) => role.id) as string[],
})

function submit() {
    form.patch(`/admin/users/${props.user.id}`)
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Edit ${user.name}`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto max-w-4xl space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <User class="size-6 text-hive" />
                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">Edit User</h1>
                                    <p class="mt-2 text-sm text-zinc-400">Update account details and administrative access.</p>
                                </div>
                            </div>

                            <Link :href="`/admin/users/${user.id}`" class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 hover:text-hive">
                                <ArrowLeft class="size-4" /> Back
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black">Account</h2>
                            <div class="mt-5 grid gap-5 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">Name</label>
                                    <input v-model="form.name" type="text" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50" />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">Email</label>
                                    <input v-model="form.email" type="email" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50" />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="flex items-start gap-3">
                                <Shield class="mt-0.5 size-5 text-hive" />
                                <div>
                                    <h2 class="font-black">Super Administrator</h2>
                                    <p class="mt-1 text-sm text-zinc-500">Super administrators bypass all administrative permission checks.</p>
                                </div>
                            </div>

                            <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-button border border-hive/20 bg-hive/5 p-4">
                                <input v-model="form.is_admin" type="checkbox" class="mt-0.5 size-4 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive" />
                                <span>
                                    <span class="block text-sm font-black text-white">Grant unrestricted administrative access</span>
                                    <span class="mt-1 block text-xs leading-5 text-zinc-500">This user can access every administration feature, including roles, security settings and panel updates.</span>
                                </span>
                            </label>
                            <InputError class="mt-2" :message="form.errors.is_admin" />
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="flex items-start gap-3">
                                <ShieldCheck class="mt-0.5 size-5 text-hive" />
                                <div>
                                    <h2 class="font-black">Administrative Roles</h2>
                                    <p class="mt-1 text-sm text-zinc-500">Assign one or more restricted roles. Permissions from assigned roles are combined.</p>
                                </div>
                            </div>

                            <div v-if="roles.length" class="mt-5 grid gap-3 md:grid-cols-2">
                                <label v-for="role in roles" :key="role.id" class="flex cursor-pointer items-start gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4 transition hover:border-zinc-700">
                                    <input v-model="form.roles" :value="role.id" type="checkbox" class="mt-0.5 size-4 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive" />
                                    <span>
                                        <span class="block text-sm font-black text-zinc-200">{{ role.name }}</span>
                                        <span class="mt-1 block text-xs leading-5 text-zinc-500">{{ role.description || 'No description provided.' }}</span>
                                    </span>
                                </label>
                            </div>
                            <div v-else class="mt-5 rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm text-zinc-500">No roles have been created yet.</div>
                            <InputError class="mt-2" :message="form.errors.roles" />
                        </section>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:opacity-50">
                                <Save class="size-4" />
                                {{ form.processing ? 'Saving...' : 'Save User' }}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
