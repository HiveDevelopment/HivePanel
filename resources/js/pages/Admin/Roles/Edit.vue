<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    role: any | null;
    permissionGroups: Record<string, Record<string, string>>;
}>();

const form = useForm({
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    permissions: [...(props.role?.permissions ?? [])] as string[],
});

const editing = computed(() => Boolean(props.role));

function groupPermissions(group: Record<string, string>) {
    return Object.keys(group);
}

function groupSelected(group: Record<string, string>) {
    return groupPermissions(group).every((permission) => form.permissions.includes(permission));
}

function toggleGroup(group: Record<string, string>) {
    const permissions = groupPermissions(group);
    if (groupSelected(group)) {
        form.permissions = form.permissions.filter((permission) => !permissions.includes(permission));
    } else {
        form.permissions = [...new Set([...form.permissions, ...permissions])];
    }
}

function submit() {
    if (props.role) {
        form.patch(`/admin/roles/${props.role.id}`);
        return;
    }

    form.post('/admin/roles');
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="editing ? `Edit ${role.name}` : 'Create Role'" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto max-w-5xl space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <ShieldCheck class="size-6 text-hive" />
                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">{{ editing ? 'Edit Role' : 'Create Role' }}</h1>
                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.choose_the_administrative_permissions_granted_by_this_role') }}
                                    </p>
                                </div>
                            </div>
                            <Link
                                href="/admin/roles"
                                class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground hover:text-hive"
                            >
                                <ArrowLeft class="size-4" /> {{ t('ui.back') }}
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.role_name') }}</label>
                                    <input
                                        v-model="form.name"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none focus:border-hive/50"
                                        :placeholder="t('ui.support')"
                                    />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.description') }}</label>
                                    <input
                                        v-model="form.description"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none focus:border-hive/50"
                                        :placeholder="t('ui.permissions_for_support_staff')"
                                    />
                                    <InputError class="mt-2" :message="form.errors.description" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface">
                            <div class="border-b border-border p-5 sm:p-6">
                                <h2 class="text-lg font-black">{{ t('ui.permissions') }}</h2>
                                <p class="mt-1 text-sm text-muted-foreground">{{ t('ui.permissions_from_multiple_assigned_roles_are_combined') }}</p>
                            </div>

                            <div class="grid gap-px bg-surface-light lg:grid-cols-2">
                                <div v-for="(permissions, group) in permissionGroups" :key="group" class="bg-surface p-5">
                                    <div class="mb-4 flex items-center justify-between">
                                        <h3 class="font-black text-foreground">{{ group }}</h3>
                                        <button type="button" class="text-xs font-black text-hive hover:opacity-80" @click="toggleGroup(permissions)">
                                            {{ groupSelected(permissions) ? 'Clear' : 'Select all' }}
                                        </button>
                                    </div>
                                    <div class="space-y-2">
                                        <label
                                            v-for="(label, permission) in permissions"
                                            :key="permission"
                                            class="flex cursor-pointer items-start gap-3 rounded-button border border-border bg-surface-dark p-3.5 transition hover:border-border"
                                        >
                                            <input
                                                v-model="form.permissions"
                                                :value="permission"
                                                type="checkbox"
                                                class="mt-0.5 size-4 rounded border-border bg-surface-dark text-hive focus:ring-hive"
                                            />
                                            <span>
                                                <span class="block text-sm font-bold text-foreground">{{ label }}</span>
                                                <span class="mt-0.5 block font-mono text-[10px] text-muted-foreground">{{ permission }}</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:opacity-50"
                            >
                                <Save class="size-4" />
                                {{ form.processing ? 'Saving...' : editing ? 'Save Role' : 'Create Role' }}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
