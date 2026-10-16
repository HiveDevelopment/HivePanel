<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import DesignTokenSettings from '@/components/DesignTokenSettings.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { MonitorCog, Palette } from 'lucide-vue-next';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('appearance.title')" />

        <SettingsLayout>
            <div class="space-y-5">
                <section class="overflow-hidden rounded-panel border border-foreground/[0.08] bg-surface">
                    <div class="border-b border-foreground/[0.06] px-5 py-5 sm:px-6">
                        <div class="flex items-start gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-hive/15 bg-hive/[0.06]">
                                <Palette class="size-4 text-hive" />
                            </div>

                            <div>
                                <h2 class="text-sm font-semibold text-foreground">
                                    {{ t('appearance.heading') }}
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                    {{ t('appearance.description') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-foreground/[0.06] bg-foreground/[0.025]"
                            >
                                <MonitorCog class="size-4 text-muted-foreground" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-medium text-foreground">
                                    {{ t('ui.interface_theme') }}
                                </h3>

                                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.select_a_light_or_dark_interface_or_automatically_follow_your_ope') }}
                                </p>

                                <div class="mt-4">
                                    <AppearanceTabs />
                                </div>
                            </div>
                        </div>
                    </div>
                    <DesignTokenSettings />
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
