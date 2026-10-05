<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import { type BreadcrumbItem, type SharedData, type User } from '@/types'
import { TransitionRoot } from '@headlessui/vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import {
    AlertTriangle,
    AtSign,
    Check,
    LoaderCircle,
    MailCheck,
    Trash2,
    UserRound,
} from 'lucide-vue-next'
import { ref } from 'vue'

interface Props {
    mustVerifyEmail: boolean
    status?: string
    className?: string
}

defineProps<Props>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
]

const page = usePage<SharedData>()
const user = page.props.auth.user as User

const form = useForm({
    name: user.name,
    email: user.email,
})

const deletePassword = ref('')
const deleteError = ref<string | null>(null)
const deleting = ref(false)
const showDeleteConfirmation = ref(false)

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    })
}

const deleteAccount = () => {
    if (! deletePassword.value || deleting.value) {
        return
    }

    deleting.value = true
    deleteError.value = null

    router.delete(route('profile.destroy'), {
        data: {
            password: deletePassword.value,
        },
        preserveScroll: true,

        onError: (errors) => {
            deleteError.value = errors.password ?? 'Unable to delete your account.'
        },

        onFinish: () => {
            deleting.value = false
        },
    })
}

const cancelDelete = () => {
    showDeleteConfirmation.value = false
    deletePassword.value = ''
    deleteError.value = null
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="space-y-5">
                <section class="overflow-hidden rounded-2xl border border-white/[0.08] bg-[#111315]">
                    <div class="border-b border-white/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-white/[0.06] bg-white/[0.025]">
                                <UserRound class="size-4 text-zinc-400" />
                            </div>

                            <div>
                                <h2 class="text-sm font-semibold text-white">
                                    Profile information
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-zinc-500">
                                    Update your personal information and email address.
                                </p>
                            </div>
                        </div>
                    </div>

                    <form
                        class="p-5 sm:p-6"
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <label
                                    for="name"
                                    class="mb-2 block text-xs font-medium text-zinc-400"
                                >
                                    Name
                                </label>

                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    autocomplete="name"
                                    placeholder="Full name"
                                    class="block w-full rounded-xl border border-white/[0.07] bg-[#0d0f11] px-3.5 py-3 text-sm text-white outline-none transition placeholder:text-zinc-700 hover:border-white/[0.12] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                />

                                <InputError
                                    class="mt-2"
                                    :message="form.errors.name"
                                />
                            </div>

                            <div>
                                <label
                                    for="email"
                                    class="mb-2 block text-xs font-medium text-zinc-400"
                                >
                                    Email address
                                </label>

                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autocomplete="username"
                                    placeholder="Email address"
                                    class="block w-full rounded-xl border border-white/[0.07] bg-[#0d0f11] px-3.5 py-3 text-sm text-white outline-none transition placeholder:text-zinc-700 hover:border-white/[0.12] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                />

                                <InputError
                                    class="mt-2"
                                    :message="form.errors.email"
                                />
                            </div>
                        </div>

                        <div
                            v-if="mustVerifyEmail && !user.email_verified_at"
                            class="mt-5 rounded-xl border border-amber-500/15 bg-amber-500/[0.05] p-4"
                        >
                            <div class="flex items-start gap-3">
                                <MailCheck class="mt-0.5 size-4 shrink-0 text-amber-400" />

                                <div>
                                    <p class="text-xs font-semibold text-amber-300">
                                        Email address not verified
                                    </p>

                                    <p class="mt-1 text-xs leading-5 text-zinc-500">
                                        Verify your email address to ensure you can recover and secure your account.
                                    </p>

                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="mt-2 text-xs font-semibold text-hive transition hover:text-hive-light"
                                    >
                                        Send verification email
                                    </Link>

                                    <p
                                        v-if="status === 'verification-link-sent'"
                                        class="mt-2 text-xs font-medium text-emerald-400"
                                    >
                                        A new verification link has been sent.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-3 border-t border-white/[0.05] pt-5">
                            <TransitionRoot
                                :show="form.recentlySuccessful"
                                enter="transition ease-in-out"
                                enter-from="opacity-0"
                                leave="transition ease-in-out"
                                leave-to="opacity-0"
                                class="mr-auto"
                            >
                                <p class="flex items-center gap-1.5 text-xs font-medium text-emerald-400">
                                    <Check class="size-3.5" />
                                    Changes saved
                                </p>
                            </TransitionRoot>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light disabled:cursor-wait disabled:opacity-60"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="size-4 animate-spin"
                                />

                                Save changes
                            </button>
                        </div>
                    </form>
                </section>

                <section class="overflow-hidden rounded-2xl border border-red-500/15 bg-[#111315]">
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-red-500/15 bg-red-500/[0.06]">
                                    <AlertTriangle class="size-4 text-red-400" />
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-semibold text-white">
                                            Delete account
                                        </h2>

                                        <span class="rounded-md border border-red-500/15 bg-red-500/[0.06] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-red-400">
                                            Danger
                                        </span>
                                    </div>

                                    <p class="mt-1 max-w-2xl text-xs leading-5 text-zinc-500">
                                        Permanently delete your account and all associated resources. This action cannot be undone.
                                    </p>
                                </div>
                            </div>

                            <button
                                v-if="!showDeleteConfirmation"
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-red-500/20 bg-red-500/[0.07] px-4 py-2.5 text-sm font-semibold text-red-400 transition hover:border-red-500/30 hover:bg-red-500/[0.12]"
                                @click="showDeleteConfirmation = true"
                            >
                                <Trash2 class="size-4" />
                                Delete account
                            </button>
                        </div>

                        <div
                            v-if="showDeleteConfirmation"
                            class="mt-5 border-t border-white/[0.05] pt-5"
                        >
                            <div class="max-w-lg">
                                <p class="text-xs leading-5 text-zinc-400">
                                    Enter your password to confirm that you want to permanently delete your account.
                                </p>

                                <input
                                    v-model="deletePassword"
                                    type="password"
                                    autocomplete="current-password"
                                    placeholder="Current password"
                                    class="mt-3 block w-full rounded-xl border border-red-500/15 bg-[#0d0f11] px-3.5 py-3 text-sm text-white outline-none transition placeholder:text-zinc-700 focus:border-red-500/40 focus:ring-4 focus:ring-red-500/[0.05]"
                                    @keyup.enter="deleteAccount"
                                />

                                <p
                                    v-if="deleteError"
                                    class="mt-2 text-xs font-medium text-red-400"
                                >
                                    {{ deleteError }}
                                </p>

                                <div class="mt-4 flex items-center gap-2">
                                    <button
                                        type="button"
                                        :disabled="deleting"
                                        class="rounded-xl border border-white/[0.07] px-4 py-2.5 text-sm font-semibold text-zinc-400 transition hover:bg-white/[0.04] hover:text-white"
                                        @click="cancelDelete"
                                    >
                                        Cancel
                                    </button>

                                    <button
                                        type="button"
                                        :disabled="!deletePassword || deleting"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-400 disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="deleteAccount"
                                    >
                                        <LoaderCircle
                                            v-if="deleting"
                                            class="size-4 animate-spin"
                                        />

                                        <Trash2
                                            v-else
                                            class="size-4"
                                        />

                                        Permanently delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>