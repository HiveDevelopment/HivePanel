<script setup lang="ts">
import NectarBridgeFrame from '@/components/nectar/NectarBridgeFrame.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Puzzle } from 'lucide-vue-next'

const props = defineProps<{
    extensionPage: {
        extensionId: string
        extensionName: string
        title: string
    }
    cell?: any
    frameUrl: string
}>()
</script>

<template>
    <AppLayout :context="cell ? 'server' : 'dashboard'" :active-cell="cell" :active-cell-status="cell?.status === 'running' ? 'running' : 'offline'">
        <Head :title="`${props.extensionPage.title} - ${props.extensionPage.extensionName}`" />
        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <Puzzle class="size-6 shrink-0 text-hive" />
                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">{{ extensionPage.title }}</h1>
                                    <p class="mt-2 text-sm text-zinc-400">{{ cell?.name || extensionPage.extensionName }}</p>
                                </div>
                            </div>

                        </div>
                    </section>
                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <NectarBridgeFrame
                            :key="frameUrl"
                            :extension-id="extensionPage.extensionId"
                            :title="extensionPage.title"
                            :src="frameUrl"
                            :cell-id="cell?.id"
                        />
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
