<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps<{
    cell: { id: string; name: string };
    owner: { name: string; email: string } | null;
    accounts: Array<{ id: string; name: string; email: string; permissions: string[] }>;
    permissionGroups: Record<string, Record<string, string>>;
}>();
const form = useForm({ email: '', permissions: [] as string[] });
const removing = ref<(typeof props.accounts)[number] | null>(null);
const deleting = ref(false);
function edit(account: (typeof props.accounts)[number]) {
    form.email = account.email;
    form.permissions = [...account.permissions];
    form.clearErrors();
}
function save() {
    form.post(`/admin/cells/${props.cell.id}/users`, { preserveScroll: true, onSuccess: () => form.reset() });
}
function remove() {
    if (!removing.value) return;
    deleting.value = true;
    router.delete(`/admin/cells/${props.cell.id}/users/${removing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (removing.value = null),
        onFinish: () => (deleting.value = false),
    });
}
</script>
<template>
    <AppLayout context="admin"
        ><Head :title="`${cell.name} · ${t('accounts.title')}`" />
        <main class="space-y-5 px-4 py-6 sm:px-6 lg:px-8">
            <Link :href="`/admin/cells/${cell.id}`" class="text-sm text-muted-foreground">← {{ t('accounts.back') }}</Link>
            <header class="rounded-panel border border-border bg-surface p-6">
                <h1 class="text-xl font-bold text-foreground">{{ t('accounts.title') }} · {{ cell.name }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ t('accounts.description') }}</p>
                <p v-if="owner" class="mt-3 text-xs text-muted-foreground">{{ t('accounts.owner') }}: {{ owner.name }} · {{ owner.email }}</p>
            </header>
            <section class="rounded-panel border border-border bg-surface p-5">
                <h2 class="font-semibold text-foreground">{{ t('accounts.assigned') }}</h2>
                <p v-if="!accounts.length" class="mt-3 text-sm text-muted-foreground">{{ t('accounts.empty') }}</p>
                <div
                    v-for="account in accounts"
                    :key="account.id"
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
                >
                    <div>
                        <p class="font-semibold text-foreground">{{ account.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ account.email }} · {{ account.permissions.length }} {{ t('accounts.permissions') }}
                        </p>
                    </div>
                    <div class="flex gap-4 text-sm">
                        <button class="text-hive" @click="edit(account)">{{ t('accounts.edit') }}</button
                        ><button class="text-status-danger" @click="removing = account">{{ t('accounts.remove') }}</button>
                    </div>
                </div>
            </section>
            <form class="space-y-4 rounded-panel border border-border bg-surface p-5" @submit.prevent="save">
                <h2 class="font-semibold text-foreground">{{ t('accounts.add') }}</h2>
                <label class="block text-sm text-muted-foreground"
                    >{{ t('accounts.email')
                    }}<input
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="off"
                        class="mt-2 w-full rounded-button border border-border bg-surface-dark p-3 text-foreground"
                /></label>
                <p v-if="form.errors.email" role="alert" class="text-sm text-status-danger">{{ form.errors.email }}</p>
                <p class="text-xs text-muted-foreground">{{ t('accounts.existing') }}</p>
                <div class="grid gap-4 lg:grid-cols-2">
                    <fieldset v-for="(permissions, group) in permissionGroups" :key="group" class="rounded-button border border-border p-4">
                        <legend class="px-2 text-sm font-semibold text-foreground">{{ t(group) }}</legend>
                        <label v-for="(label, key) in permissions" :key="key" class="mt-2 flex items-start gap-3 text-xs text-muted-foreground"
                            ><input v-model="form.permissions" type="checkbox" :value="key" />{{ t(label) }}</label
                        >
                    </fieldset>
                </div>
                <p v-if="form.errors.permissions" role="alert" class="text-status-danger">{{ form.errors.permissions }}</p>
                <button
                    :disabled="form.processing"
                    class="rounded-button bg-hive px-5 py-3 text-sm font-bold text-primary-foreground disabled:opacity-50"
                >
                    {{ t('accounts.save') }}
                </button>
            </form>
        </main>
        <ConfirmationModal
            :open="Boolean(removing)"
            :title="t('accounts.remove')"
            :description="t('accounts.remove_description')"
            :confirm-text="t('accounts.remove')"
            danger
            :loading="deleting"
            @cancel="removing = null"
            @confirm="remove"
    /></AppLayout>
</template>
