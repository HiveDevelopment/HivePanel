<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Mail, Save, Shield, UserPlus, KeyRound } from 'lucide-vue-next'

interface Role {
    id: string
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
    roles: [] as string[],
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

        <div class="min-h-screen bg-surface-dark px-4 py-6 text-white sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-5">
                <section class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-zinc-800 bg-surface p-6">
                    <div class="flex items-center gap-3">
                        <div class="rounded-button bg-hive/10 p-3"><UserPlus class="size-5 text-hive" /></div>
                        <div>
                            <h1 class="text-2xl font-black">Create User</h1>
                            <p class="mt-1 text-sm text-zinc-500">Add a HivePanel account and assign access.</p>
                        </div>
                    </div>
                    <Link href="/admin/users" class="inline-flex items-center gap-2 rounded-button border border-zinc-800 px-4 py-2 text-sm text-zinc-300 hover:text-white">
                        <ArrowLeft class="size-4" /> Back to users
                    </Link>
                </section>

                <form class="space-y-5" @submit.prevent="submit">
                    <section class="space-y-5 rounded-panel border border-zinc-800 bg-surface p-6">
                        <h2 class="font-bold">Account details</h2>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="user-name" class="text-sm text-zinc-300">Full name</label>
                                <input id="user-name" v-model="form.name" required autocomplete="name" class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm outline-none focus:border-hive" />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>
                            <div>
                                <label for="user-email" class="text-sm text-zinc-300">Email address</label>
                                <input id="user-email" v-model="form.email" type="email" required autocomplete="off" class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm outline-none focus:border-hive" />
                                <InputError :message="form.errors.email" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="space-y-5 rounded-panel border border-zinc-800 bg-surface p-6">
                        <h2 class="font-bold">Account setup</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-button border p-4" :class="form.method === 'invite' ? 'border-hive/50 bg-hive/5' : 'border-zinc-800'">
                                <input v-model="form.method" type="radio" value="invite" class="accent-orange-500" />
                                <span><span class="flex items-center gap-2 font-semibold"><Mail class="size-4" /> Email invitation</span><span class="mt-1 block text-xs text-zinc-500">Send a link so the user sets their password.</span></span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-button border p-4" :class="form.method === 'password' ? 'border-hive/50 bg-hive/5' : 'border-zinc-800'">
                                <input v-model="form.method" type="radio" value="password" class="accent-orange-500" />
                                <span><span class="flex items-center gap-2 font-semibold"><KeyRound class="size-4" /> Set password manually</span><span class="mt-1 block text-xs text-zinc-500">Choose the initial password yourself.</span></span>
                            </label>
                        </div>
                        <p v-if="form.method === 'invite'" class="text-sm text-zinc-400">An invitation will be sent using HivePanel's configured mail service. The link expires after 60 minutes.</p>
                        <div v-else class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="new-password" class="text-sm text-zinc-300">Password</label>
                                <input id="new-password" v-model="form.password" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm outline-none focus:border-hive" />
                                <InputError :message="form.errors.password" class="mt-2" />
                            </div>
                            <div>
                                <label for="confirm-password" class="text-sm text-zinc-300">Confirm password</label>
                                <input id="confirm-password" v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm outline-none focus:border-hive" />
                            </div>
                        </div>
                    </section>

                    <section class="space-y-5 rounded-panel border border-zinc-800 bg-surface p-6">
                        <div class="flex items-center gap-2"><Shield class="size-5 text-hive" /><h2 class="font-bold">Permissions</h2></div>
                        <label class="flex items-start gap-3 rounded-button border border-zinc-800 p-4">
                            <input v-model="form.is_admin" type="checkbox" class="mt-1 accent-orange-500" />
                            <span><span class="block font-semibold">Super administrator</span><span class="text-xs text-zinc-500">Unrestricted access to HivePanel administration. Only enable for trusted administrators.</span></span>
                        </label>
                        <div v-if="roles.length" class="space-y-3">
                            <p class="text-sm text-zinc-400">Administrative roles</p>
                            <label v-for="role in roles" :key="role.id" class="flex items-start gap-3 rounded-button border border-zinc-800 p-3">
                                <input v-model="form.roles" :value="role.id" type="checkbox" class="mt-1 accent-orange-500" />
                                <span><span class="block text-sm font-semibold">{{ role.name }}</span><span class="text-xs text-zinc-500">{{ role.description }}</span></span>
                            </label>
                        </div>
                        <InputError :message="form.errors.roles || form.errors.is_admin" />
                    </section>

                    <div class="flex justify-end gap-3">
                        <Link href="/admin/users" class="rounded-button border border-zinc-800 px-5 py-3 text-sm font-semibold text-zinc-300">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-button bg-hive px-5 py-3 text-sm font-bold text-black disabled:opacity-50">
                            <Save class="size-4" /> {{ form.processing ? 'Creating…' : 'Create user' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
