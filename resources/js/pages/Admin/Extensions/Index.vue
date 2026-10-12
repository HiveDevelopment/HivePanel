<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Blocks, Package, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Extension = {
    id: string;
    name: string;
    version: string;
    enabled: boolean;
    manifest: { frontend?: { slots?: string[] }; panelCompatibility?: string; description?: string; author?: string };
};
const props = defineProps<{ extensions: Extension[] }>();
const search = ref('');
const filtered = computed(() =>
    props.extensions.filter((e) => `${e.name} ${e.id} ${e.version}`.toLowerCase().includes(search.value.trim().toLowerCase())),
);
</script>
<template>
    <AppLayout context="admin">
        <Head title="Extensions" />
        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <Blocks class="size-6 text-hive" />
                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">Extensions</h1>
                                    <p class="mt-2 text-sm text-zinc-400">Manage Nectar extensions installed on this panel.</p>
                                </div>
                            </div>
                            <Link
                                href="/admin/extensions/install"
                                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                                ><Plus class="size-4" /> Install Extension</Link
                            >
                        </div>
                    </section>
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500" /><input
                                v-model="search"
                                placeholder="Search extensions..."
                                class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] py-3 pl-10 pr-4 text-sm font-bold text-white outline-none placeholder:text-zinc-600 focus:border-hive/50"
                            />
                        </div>
                    </section>
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-black">Installed Extensions</h2>
                                <p class="mt-1 text-sm text-zinc-500">Locally installed packages. No registry required.</p>
                            </div>
                            <span class="rounded-full border border-zinc-800 bg-[#0d0f11] px-3 py-1 text-xs font-black text-zinc-500">{{
                                extensions.length
                            }}</span>
                        </div>
                        <div v-if="!filtered.length" class="rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center">
                            <Package class="mx-auto size-10 text-zinc-700" />
                            <h3 class="mt-4 text-lg font-black text-zinc-300">
                                {{ search ? 'No matching extensions' : 'No extensions installed yet' }}
                            </h3>
                            <p class="mt-2 text-sm text-zinc-500">
                                {{ search ? 'Try a different search.' : 'Install a .nectar package to get started.' }}
                            </p>
                        </div>
                        <div v-else class="grid gap-3 xl:grid-cols-2">
                            <Link
                                v-for="extension in filtered"
                                :key="extension.id"
                                :href="`/admin/extensions/${extension.id}`"
                                class="block rounded-button border border-zinc-900 bg-[#0d0f11] p-4 transition hover:-translate-y-0.5 hover:border-hive/40 hover:bg-surface-hover"
                                ><div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-black">{{ extension.name }}</h3>
                                        <p class="mt-1 truncate font-mono text-xs text-zinc-600">{{ extension.id }} · v{{ extension.version }}</p>
                                        <p class="mt-3 text-xs text-zinc-500">{{ extension.manifest.frontend?.slots?.join(', ') || 'No slots' }}</p>
                                    </div>
                                    <span
                                        class="rounded-full border px-2 py-1 text-xs font-bold"
                                        :class="
                                            extension.enabled
                                                ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                : 'border-zinc-700 bg-zinc-800 text-zinc-400'
                                        "
                                        >{{ extension.enabled ? 'Enabled' : 'Disabled' }}</span
                                    >
                                </div></Link
                            >
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
