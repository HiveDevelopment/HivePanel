<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Boxes, CircleCheck, Network, Server, User } from 'lucide-vue-next';

defineProps<{
    migration: any;
    servers: any[];
    summary: {
        selected: number;
        preserve_allocations: number;
        allocate_new: number;
        source_nodes: number;
        owners: number;
        users_to_create: number;
        combs_to_create: number;
        users_to_transfer: number;
        databases_to_transfer: number;
    };
}>();

function primaryAllocation(server: any) {
    const allocations = server.source_allocations ?? [];

    return allocations.find((allocation: any) => allocation.is_default) ?? allocations[0] ?? null;
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${migration.name} - Review`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="rounded-full border border-status-success/30 bg-status-success/10 px-3 py-1 text-xs font-black text-status-success"
                                    >
                                        {{ t('ui.step_3_of_3') }}
                                    </span>

                                    <span class="text-xs font-bold text-muted-foreground">
                                        {{ t('ui.discovery_mapping_review') }}
                                    </span>
                                </div>

                                <h1 class="mt-3 text-2xl font-black sm:text-3xl">
                                    {{ t('ui.review_migration') }}
                                </h1>

                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{ t('ui.confirm_the_destination_plan_before_we_build_the_execution_and_tr') }}
                                </p>
                            </div>

                            <Link
                                :href="`/admin/migrations/${migration.id}/mapping`"
                                class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.edit_mapping') }}
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-9">
                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('Servers') }}</div>
                            <div class="mt-1 text-2xl font-black">{{ summary.selected }}</div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.owners') }}</div>
                            <div class="mt-1 text-2xl font-black">{{ summary.owners }}</div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.source_nodes') }}</div>
                            <div class="mt-1 text-2xl font-black">{{ summary.source_nodes }}</div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.preserve') }}</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ summary.preserve_allocations }}</div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.allocate_new') }}</div>
                            <div class="mt-1 text-2xl font-black text-status-warning">{{ summary.allocate_new }}</div>
                        </div>

                        <div class="rounded-panel border border-status-warning/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.users_to_create') }}</div>
                            <div class="mt-1 text-2xl font-black text-status-warning">{{ summary.users_to_create }}</div>
                        </div>

                        <div class="rounded-panel border border-status-warning/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.combs_to_create') }}</div>
                            <div class="mt-1 text-2xl font-black text-status-warning">{{ summary.combs_to_create }}</div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.users_to_transfer') }}</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ summary.users_to_transfer }}</div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('Databases') }}</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ summary.databases_to_transfer }}</div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div class="border-b border-border p-5 sm:p-6">
                            <h2 class="text-lg font-black">{{ t('ui.migration_plan') }}</h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.these_mappings_are_persisted_no_destination_cell_has_been_created') }}
                            </p>
                        </div>

                        <div class="divide-y divide-border">
                            <div v-for="server in servers" :key="server.id" class="p-5 sm:p-6">
                                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_40px_minmax(0,1fr)] xl:items-center">
                                    <div>
                                        <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.pterodactyl') }}
                                        </div>

                                        <div class="mt-2 text-lg font-black text-foreground">
                                            {{ server.name }}
                                        </div>

                                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                            <div class="rounded-button border border-border bg-surface-dark p-3">
                                                <div
                                                    class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-muted-foreground"
                                                >
                                                    <User class="size-3" />
                                                    {{ t('ui.owner') }}
                                                </div>
                                                <div class="mt-1 break-all text-xs font-black text-foreground">
                                                    {{ server.owner_email }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-border bg-surface-dark p-3">
                                                <div
                                                    class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-muted-foreground"
                                                >
                                                    <Server class="size-3" />
                                                    {{ t('ui.node') }}
                                                </div>
                                                <div class="mt-1 text-xs font-black text-foreground">
                                                    {{ server.source_node_name }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-border bg-surface-dark p-3">
                                                <div
                                                    class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-muted-foreground"
                                                >
                                                    <Boxes class="size-3" />
                                                    {{ t('ui.egg') }}
                                                </div>
                                                <div class="mt-1 text-xs font-black text-foreground">
                                                    {{ server.source_egg_name }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-border bg-surface-dark p-3">
                                                <div
                                                    class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-muted-foreground"
                                                >
                                                    <Network class="size-3" />
                                                    {{ t('ui.primary') }}
                                                </div>
                                                <div class="mt-1 font-mono text-xs font-black text-foreground">
                                                    <template v-if="primaryAllocation(server)">
                                                        {{ primaryAllocation(server).ip }}:{{ primaryAllocation(server).port }}
                                                    </template>
                                                    <template v-else>
                                                        {{ t('ui.none') }}
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="hidden items-center justify-center text-2xl font-black text-hive xl:flex">→</div>

                                    <div>
                                        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-status-success">
                                            <CircleCheck class="size-4" />
                                            {{ t('ui.hivepanel_destination') }}
                                        </div>

                                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                            <div class="rounded-button border border-border bg-surface-dark p-3">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-muted-foreground">
                                                    {{ t('ui.owner') }}
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                                    <div class="text-xs font-black text-foreground">
                                                        {{ server.destination_owner?.name }}
                                                    </div>

                                                    <span
                                                        v-if="server.destination_owner?.will_create"
                                                        class="rounded-full border border-status-warning/30 bg-status-warning/10 px-2 py-0.5 text-[10px] font-black text-status-warning"
                                                    >
                                                        {{ t('ui.will_create') }}
                                                    </span>
                                                </div>

                                                <div class="mt-1 break-all text-[11px] text-muted-foreground">
                                                    {{ server.destination_owner?.email }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-border bg-surface-dark p-3">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-muted-foreground">
                                                    {{ t('ui.node') }}
                                                </div>
                                                <div class="mt-1 text-xs font-black text-foreground">
                                                    {{ server.destination_node?.name }}
                                                </div>
                                                <div class="mt-1 text-[11px] text-muted-foreground">
                                                    {{ server.destination_node?.location || 'No location' }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-border bg-surface-dark p-3">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-muted-foreground">
                                                    {{ t('ui.comb') }}
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                                    <div class="text-xs font-black text-foreground">
                                                        {{ server.destination_comb_record?.name }}
                                                    </div>

                                                    <span
                                                        v-if="server.destination_comb_record?.will_create"
                                                        class="rounded-full border border-status-warning/30 bg-status-warning/10 px-2 py-0.5 text-[10px] font-black text-status-warning"
                                                    >
                                                        {{ t('ui.will_create') }}
                                                    </span>
                                                </div>

                                                <div class="mt-1 text-[11px] text-muted-foreground">
                                                    {{ server.destination_comb_record?.external_id }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-border bg-surface-dark p-3">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-muted-foreground">
                                                    {{ t('ui.networking') }}
                                                </div>
                                                <div class="mt-1 text-xs font-black text-foreground">
                                                    {{
                                                        server.allocation_strategy === 'preserve'
                                                            ? 'Preserve source allocations'
                                                            : 'Allocate new ports'
                                                    }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-border bg-surface-dark p-3 sm:col-span-2">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-muted-foreground">
                                                    {{ t('Databases') }}
                                                </div>

                                                <div class="mt-1 text-xs font-black text-foreground">
                                                    {{ (server.database_plan ?? []).filter((database) => database.selected).length }}
                                                    {{ t('ui.selected_for_transfer') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-hive/30 bg-hive/5 p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <CircleCheck class="mt-0.5 size-5 shrink-0 text-hive" />

                                <div>
                                    <h2 class="font-black text-foreground">
                                        {{ t('ui.mapping_is_ready') }}
                                    </h2>

                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                        {{ t('ui.run_preflight_to_validate_allocations_and_configure_source_node_t') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="`/admin/migrations/${migration.id}/preflight`"
                                class="inline-flex items-center justify-center rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:bg-hive-light"
                            >
                                {{ t('ui.run_preflight') }}
                            </Link>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
