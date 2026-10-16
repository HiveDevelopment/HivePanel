<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Lock, Server } from 'lucide-vue-next';

const props = defineProps<{
    cell: any;
}>();

function lockTitle() {
    return props.cell?.lock?.reason || 'Server Locked';
}

function lockMessage() {
    return props.cell?.lock?.message || 'This server is temporarily locked. Please try again later.';
}

function lockType() {
    return props.cell?.lock?.type || 'admin_lock';
}
</script>

<template>
    <AppLayout :context="'server'" :active-cell="cell" :active-cell-status="cell.status ?? 'offline'">
        <Head :title="`${cell.name} Locked`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-8 sm:px-6 lg:px-8">
                <div class="mx-auto">
                    <section
                        class="relative overflow-hidden rounded-panel border border-status-warning/40 bg-surface p-8 shadow-[0_30px_90px_rgba(0,0,0,0.45)]"
                    >
                        <div class="absolute inset-y-0 left-0 w-1.5 bg-status-warning" />

                        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-status-warning/10 blur-3xl" />
                        <div class="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-hive/10 blur-3xl" />

                        <div class="relative">
                            <div class="mb-6 inline-flex rounded-button border border-status-warning/30 bg-status-warning/10 p-4 text-status-warning">
                                <Lock class="size-8" />
                            </div>

                            <h1 class="text-3xl font-black tracking-tight text-foreground sm:text-4xl">
                                {{ lockTitle() }}
                            </h1>

                            <p class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                                {{ lockMessage() }}
                            </p>

                            <div class="mt-8 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-button border border-border bg-surface-light p-4">
                                    <div class="flex items-center gap-3 text-muted-foreground">
                                        <Server class="size-5 text-hive" />
                                        <span class="text-xs font-black uppercase tracking-wide">
                                            {{ t('ui.server_aef7de2') }}
                                        </span>
                                    </div>

                                    <div class="mt-3 truncate text-lg font-black text-foreground">
                                        {{ cell.name }}
                                    </div>
                                </div>

                                <div class="rounded-button border border-border bg-surface-light p-4">
                                    <div class="flex items-center gap-3 text-muted-foreground">
                                        <AlertTriangle class="size-5 text-status-warning" />
                                        <span class="text-xs font-black uppercase tracking-wide">
                                            {{ t('ui.lock_type') }}
                                        </span>
                                    </div>

                                    <div class="mt-3 truncate font-mono text-sm font-black text-status-warning">
                                        {{ lockType() }}
                                    </div>
                                </div>

                                <div class="rounded-button border border-border bg-surface-light p-4">
                                    <div class="flex items-center gap-3 text-muted-foreground">
                                        <Lock class="size-5 text-foreground" />
                                        <span class="text-xs font-black uppercase tracking-wide">
                                            {{ t('ui.access') }}
                                        </span>
                                    </div>

                                    <div class="mt-3 text-sm font-black text-foreground">
                                        {{ t('ui.temporarily_restricted') }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 rounded-button border border-border bg-surface-dark p-5">
                                <h2 class="text-sm font-black uppercase tracking-wide text-muted-foreground">
                                    {{ t('ui.what_can_i_do') }}
                                </h2>

                                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                    {{ t('ui.you_can_still_view_the_server_dashboard_and_status_pages_but_this') }}
                                </p>
                            </div>

                            <div class="mt-8 flex flex-wrap gap-3">
                                <Link
                                    :href="`/cells/${cell.id}`"
                                    class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-primary-foreground transition hover:bg-hive-light"
                                >
                                    <ArrowLeft class="size-4" />
                                    {{ t('ui.back_to_dashboard') }}
                                </Link>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
