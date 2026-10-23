<script setup lang="ts">
import NectarBridgeFrame from '@/components/nectar/NectarBridgeFrame.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Download, Network, Puzzle, Settings, Terminal, Vote } from 'lucide-vue-next';

const props = defineProps<{
    extensionPage: {
        extensionId: string;
        extensionName: string;
        title: string;
        icon?: string;
    };
    cell?: any;
    frameUrl: string;
}>();
const icons = { puzzle: Puzzle, vote: Vote, settings: Settings, download: Download, network: Network, terminal: Terminal };
</script>

<template>
    <AppLayout :context="cell ? 'server' : 'dashboard'" :active-cell="cell" :active-cell-status="cell?.status === 'running' ? 'running' : 'offline'">
        <Head :title="`${props.extensionPage.title} - ${props.extensionPage.extensionName}`" />
        <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <header class="flex items-center gap-4 rounded-panel border border-foreground/[0.08] bg-surface px-5 py-5 sm:px-6">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-hive/20 bg-hive/[0.06]">
                    <component :is="icons[extensionPage.icon as keyof typeof icons] ?? Puzzle" class="size-5 text-hive" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-foreground">{{ extensionPage.title }}</h1>
                    <p class="mt-1 text-xs text-muted-foreground">{{ cell?.name || extensionPage.extensionName }}</p>
                </div>
            </header>
            <NectarBridgeFrame
                :key="frameUrl"
                :extension-id="extensionPage.extensionId"
                :title="extensionPage.title"
                :src="frameUrl"
                :cell-id="cell?.id"
                auto-height
            />
        </main>
    </AppLayout>
</template>
