<script setup lang="ts">
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
        ><Head title="Install Extension" />
        <div class="min-h-screen bg-surface-dark px-4 py-5 text-white sm:px-6 sm:py-7 lg:px-8">
            <div class="mx-auto space-y-5">
                <Link href="/admin/extensions" class="inline-flex items-center gap-2 text-sm font-bold text-zinc-400 hover:text-hive"
                    ><ArrowLeft class="size-4" /> Back to Extensions</Link
                >
                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <Upload class="size-6 text-hive" />
                        <div>
                            <h1 class="text-2xl font-black">Install Extension</h1>
                            <p class="mt-2 text-sm text-zinc-400">Upload a locally obtained .nectar package. Registry access is not required.</p>
                        </div>
                    </div>
                </section>
                <form @submit.prevent="submit" class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                    <label for="nectar-package" class="mb-2 block text-sm font-black">Nectar package</label
                    ><input
                        id="nectar-package"
                        type="file"
                        accept=".nectar"
                        required
                        class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] p-3 text-sm text-zinc-300"
                        @change="form.package = ($event.target as HTMLInputElement).files?.[0] ?? null"
                    />
                    <p v-if="form.errors.package" class="mt-2 text-sm text-red-400">{{ form.errors.package }}</p>
                    <p class="mt-3 text-xs text-zinc-500">
                        Extensions are installed disabled. Review them before enabling. Only install packages you trust.
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
