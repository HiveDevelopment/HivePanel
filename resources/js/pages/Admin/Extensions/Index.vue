<script setup lang="ts">
import { t } from '@/composables/useLanguage';
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
        <Head :title="t('Extensions')" />
        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <Blocks class="size-6 text-hive" />
                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">{{ t('Extensions') }}</h1>
                                    <p class="mt-2 text-sm text-muted-foreground">{{ t('ui.manage_nectar_extensions_installed_on_this_panel') }}</p>
                                </div>
                            </div>
                            <Link
                                href="/admin/extensions/install"
                                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                                ><Plus class="size-4" /> {{ t('ui.install_extension') }}</Link
                            >
                        </div>
                    </section>
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><input
                                v-model="search"
                                :placeholder="t('ui.search_extensions')"
                                class="w-full rounded-button border border-border bg-surface-dark py-3 pl-10 pr-4 text-sm font-bold text-foreground outline-none placeholder:text-muted-foreground focus:border-hive/50"
                            />
                        </div>
                    </section>
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-black">{{ t('ui.installed_extensions') }}</h2>
                                <p class="mt-1 text-sm text-muted-foreground">{{ t('ui.locally_installed_packages_no_registry_required') }}</p>
                            </div>
                            <span class="rounded-full border border-border bg-surface-dark px-3 py-1 text-xs font-black text-muted-foreground">{{
                                extensions.length
                            }}</span>
                        </div>
                        <div v-if="!filtered.length" class="rounded-button border border-border bg-surface-dark p-10 text-center">
                            <Package class="mx-auto size-10 text-muted-foreground" />
                            <h3 class="mt-4 text-lg font-black text-foreground">
                                {{ search ? 'No matching extensions' : 'No extensions installed yet' }}
                            </h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ search ? 'Try a different search.' : 'Install a .nectar package to get started.' }}
                            </p>
                        </div>
                        <div v-else class="grid gap-3 xl:grid-cols-2">
                            <Link
                                v-for="extension in filtered"
                                :key="extension.id"
                                :href="`/admin/extensions/${extension.id}`"
                                class="block rounded-button border border-border bg-surface-dark p-4 transition hover:-translate-y-0.5 hover:border-hive/40 hover:bg-surface-hover"
                                ><div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-black">{{ extension.name }}</h3>
                                        <p class="mt-1 truncate font-mono text-xs text-muted-foreground">
                                            {{ extension.id }} · v{{ extension.version }}
                                        </p>
                                        <p class="mt-3 text-xs text-muted-foreground">
                                            {{ extension.manifest.frontend?.slots?.join(', ') || 'No slots' }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full border px-2 py-1 text-xs font-bold"
                                        :class="
                                            extension.enabled
                                                ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                : 'border-border bg-surface-light text-muted-foreground'
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
