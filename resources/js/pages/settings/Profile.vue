<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Check, LoaderCircle, MailCheck, Trash2, UserRound } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const deletePassword = ref('');
const deleteError = ref<string | null>(null);
const deleting = ref(false);
const showDeleteConfirmation = ref(false);

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};

const deleteAccount = () => {
    if (!deletePassword.value || deleting.value) {
        return;
    }

    deleting.value = true;
    deleteError.value = null;

    router.delete(route('profile.destroy'), {
        data: {
            password: deletePassword.value,
        },
        preserveScroll: true,

        onError: (errors) => {
            deleteError.value = errors.password ?? 'Unable to delete your account.';
        },

        onFinish: () => {
            deleting.value = false;
        },
    });
};

const cancelDelete = () => {
    showDeleteConfirmation.value = false;
    deletePassword.value = '';
    deleteError.value = null;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('ui.profile_settings')" />

        <SettingsLayout>
            <div class="space-y-5">
                <section class="overflow-hidden rounded-2xl border border-foreground/[0.08] bg-surface">
                    <div class="border-b border-foreground/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-foreground/[0.06] bg-foreground/[0.025]"
                            >
                                <UserRound class="size-4 text-muted-foreground" />
                            </div>

                            <div>
                                <h2 class="text-sm font-semibold text-foreground">
                                    {{ t('ui.profile_information') }}
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.update_your_personal_information_and_email_address') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <form class="p-5 sm:p-6" @submit.prevent="submit">
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <label for="name" class="mb-2 block text-xs font-medium text-muted-foreground">
                                    {{ t('ui.name') }}
                                </label>

                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    autocomplete="name"
                                    :placeholder="t('ui.full_name')"
                                    class="block w-full rounded-xl border border-foreground/[0.07] bg-surface-dark px-3.5 py-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground hover:border-foreground/[0.12] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                />

                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <label for="email" class="mb-2 block text-xs font-medium text-muted-foreground">
                                    {{ t('ui.email_address') }}
                                </label>

                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autocomplete="username"
                                    :placeholder="t('ui.email_address')"
                                    class="block w-full rounded-xl border border-foreground/[0.07] bg-surface-dark px-3.5 py-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground hover:border-foreground/[0.12] focus:border-hive/50 focus:ring-4 focus:ring-hive/[0.06]"
                                />

                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>
                        </div>

                        <div
                            v-if="mustVerifyEmail && !user.email_verified_at"
                            class="mt-5 rounded-xl border border-status-warning/15 bg-status-warning/[0.05] p-4"
                        >
                            <div class="flex items-start gap-3">
                                <MailCheck class="mt-0.5 size-4 shrink-0 text-status-warning" />

                                <div>
                                    <p class="text-xs font-semibold text-status-warning">
                                        {{ t('ui.email_address_not_verified') }}
                                    </p>

                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                        {{ t('ui.verify_your_email_address_to_ensure_you_can_recover_and_secure_yo') }}
                                    </p>

                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="mt-2 text-xs font-semibold text-hive transition hover:text-hive-light"
                                    >
                                        {{ t('ui.send_verification_email') }}
                                    </Link>

                                    <p v-if="status === 'verification-link-sent'" class="mt-2 text-xs font-medium text-status-success">
                                        {{ t('ui.a_new_verification_link_has_been_sent') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-3 border-t border-foreground/[0.05] pt-5">
                            <TransitionRoot
                                :show="form.recentlySuccessful"
                                enter="transition ease-in-out"
                                enter-from="opacity-0"
                                leave="transition ease-in-out"
                                leave-to="opacity-0"
                                class="mr-auto"
                            >
                                <p class="flex items-center gap-1.5 text-xs font-medium text-status-success">
                                    <Check class="size-3.5" />
                                    {{ t('ui.changes_saved') }}
                                </p>
                            </TransitionRoot>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light disabled:cursor-wait disabled:opacity-60"
                            >
                                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />

                                {{ t('ui.save_changes_dd0ae7a') }}
                            </button>
                        </div>
                    </form>
                </section>

                <section class="overflow-hidden rounded-2xl border border-status-danger/15 bg-surface">
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-status-danger/15 bg-status-danger/[0.06]"
                                >
                                    <AlertTriangle class="size-4 text-status-danger" />
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-semibold text-foreground">
                                            {{ t('ui.delete_account') }}
                                        </h2>

                                        <span
                                            class="rounded-md border border-status-danger/15 bg-status-danger/[0.06] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-status-danger"
                                        >
                                            {{ t('ui.danger') }}
                                        </span>
                                    </div>

                                    <p class="mt-1 max-w-2xl text-xs leading-5 text-muted-foreground">
                                        {{ t('ui.permanently_delete_your_account_and_all_associated_resources_this') }}
                                    </p>
                                </div>
                            </div>

                            <button
                                v-if="!showDeleteConfirmation"
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-status-danger/20 bg-status-danger/[0.07] px-4 py-2.5 text-sm font-semibold text-status-danger transition hover:border-status-danger/30 hover:bg-status-danger/[0.12]"
                                @click="showDeleteConfirmation = true"
                            >
                                <Trash2 class="size-4" />
                                {{ t('ui.delete_account') }}
                            </button>
                        </div>

                        <div v-if="showDeleteConfirmation" class="mt-5 border-t border-foreground/[0.05] pt-5">
                            <div class="max-w-lg">
                                <p class="text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.enter_your_password_to_confirm_that_you_want_to_permanently_delet') }}
                                </p>

                                <input
                                    v-model="deletePassword"
                                    type="password"
                                    autocomplete="current-password"
                                    :placeholder="t('ui.current_password')"
                                    class="mt-3 block w-full rounded-xl border border-status-danger/15 bg-surface-dark px-3.5 py-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-status-danger/40 focus:ring-4 focus:ring-status-danger/[0.05]"
                                    @keyup.enter="deleteAccount"
                                />

                                <p v-if="deleteError" class="mt-2 text-xs font-medium text-status-danger">
                                    {{ deleteError }}
                                </p>

                                <div class="mt-4 flex items-center gap-2">
                                    <button
                                        type="button"
                                        :disabled="deleting"
                                        class="rounded-xl border border-foreground/[0.07] px-4 py-2.5 text-sm font-semibold text-muted-foreground transition hover:bg-foreground/[0.04] hover:text-foreground"
                                        @click="cancelDelete"
                                    >
                                        {{ t('ui.cancel') }}
                                    </button>

                                    <button
                                        type="button"
                                        :disabled="!deletePassword || deleting"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-status-danger px-4 py-2.5 text-sm font-semibold text-foreground transition hover:bg-status-danger disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="deleteAccount"
                                    >
                                        <LoaderCircle v-if="deleting" class="size-4 animate-spin" />

                                        <Trash2 v-else class="size-4" />

                                        {{ t('ui.permanently_delete') }}
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
