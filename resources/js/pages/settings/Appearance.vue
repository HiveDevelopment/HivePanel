<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import {
    MonitorCog,
    Palette,
} from 'lucide-vue-next'

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
]
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-5">
                <section class="overflow-hidden rounded-2xl border border-white/[0.08] bg-[#111315]">
                    <div class="border-b border-white/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-hive/15 bg-hive/[0.06]">
                                <Palette class="size-4 text-hive" />
                            </div>

                            <div>
                                <h2 class="text-sm font-semibold text-white">
                                    Appearance
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-zinc-500">
                                    Choose how HivePanel looks on this device.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6">
                        <div class="flex items-start gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-white/[0.06] bg-white/[0.025]">
                                <MonitorCog class="size-4 text-zinc-500" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-medium text-zinc-200">
                                    Interface theme
                                </h3>

                                <p class="mt-1 text-xs leading-5 text-zinc-500">
                                    Select a light or dark interface, or automatically follow your operating system.
                                </p>

                                <div class="mt-4">
                                    <AppearanceTabs />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>