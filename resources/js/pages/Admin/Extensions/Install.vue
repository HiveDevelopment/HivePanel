<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Upload } from 'lucide-vue-next';

type Extension = {
    id: string;
    name: string;
    version: string;
    enabled: boolean;
    manifest: { frontend?: { slots?: string[] }; panelCompatibility?: string; description?: string; author?: string };
};
const form = useForm<{ package: File | null }>({ package: null });
const submit = () => form.post('/admin/extensions', { forceFormData: true });
</script>
<template>
    <AppLayout context="admin"
        ><Head :title="t('ui.install_extension')" />
        <div class="min-h-screen bg-surface-dark px-4 py-5 text-foreground sm:px-6 sm:py-7 lg:px-8">
            <div class="mx-auto space-y-5">
                <Link href="/admin/extensions" class="inline-flex items-center gap-2 text-sm font-bold text-muted-foreground hover:text-hive"
                    ><ArrowLeft class="size-4" /> {{ t('ui.back_to_extensions') }}</Link
                >
                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <Upload class="size-6 text-hive" />
                        <div>
                            <h1 class="text-2xl font-black">{{ t('ui.install_extension') }}</h1>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('ui.upload_a_locally_obtained_nectar_package_registry_access_is_not_r') }}
                            </p>
                        </div>
                    </div>
                </section>
                <form @submit.prevent="submit" class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                    <label for="nectar-package" class="mb-2 block text-sm font-black">{{ t('ui.nectar_package') }}</label
                    ><input
                        id="nectar-package"
                        type="file"
                        accept=".nectar"
                        required
                        class="w-full rounded-button border border-border bg-surface-dark p-3 text-sm text-foreground"
                        @change="form.package = ($event.target as HTMLInputElement).files?.[0] ?? null"
                    />
                    <p v-if="form.errors.package" class="mt-2 text-sm text-status-danger">{{ form.errors.package }}</p>
                    <p class="mt-3 text-xs text-muted-foreground">
                        {{ t('ui.extensions_are_installed_disabled_review_them_before_enabling_onl') }}
                    </p>
                    <button
                        type="submit"
                        :disabled="form.processing || !form.package"
                        class="mt-5 inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black disabled:opacity-50"
                    >
                        <Upload class="size-4" />{{ form.processing ? 'Installing...' : 'Install Extension' }}
                    </button>
                </form>
            </div>
        </div></AppLayout
    >
</template>
