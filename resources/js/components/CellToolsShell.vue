<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Settings2 } from 'lucide-vue-next';
import { computed } from 'vue';
defineProps<{ cell: any; title: string; description: string }>();
const page = usePage();
const errors = computed(() => Object.values(page.props.errors ?? {}));
</script>
<template>
    <AppLayout context="server" :active-cell="cell"
        ><Head :title="`${cell.name} · ${title}`" />
        <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-border bg-surface p-5 sm:px-6">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/5">
                        <Settings2 class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-foreground">{{ t(title ?? '') }}</h1>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ t(description ?? '') }}</p>
                    </div>
                </div>
                <slot name="actions" />
            </section>
            <div
                v-if="errors.length"
                role="alert"
                class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm text-status-danger"
            >
                <p v-for="(error, index) in errors" :key="index">{{ error }}</p>
            </div>
            <slot /></main
    ></AppLayout>
</template>
