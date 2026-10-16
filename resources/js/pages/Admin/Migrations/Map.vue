<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Boxes, Check, CircleAlert, Network, Server, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    migration: any;
    servers: any[];
    nodes: any[];
    users: any[];
    combs: any[];
    sourceOwners: any[];
    sourceNodes: any[];
    sourceEggs: any[];
}>();

const form = useForm({
    selected_server_ids: props.servers
        .filter((server) => server.selected !== false && !server.migration_duplicate && !server.source_metadata?.migration_duplicate)
        .map((server) => server.id),

    owner_transfer: Object.fromEntries(props.sourceOwners.map((item) => [item.key, true])),

    owner_map: Object.fromEntries(props.sourceOwners.map((item) => [item.key, item.destination_id ?? (item.matched ? '' : '__create__')])),

    owner_create: Object.fromEntries(
        props.sourceOwners.map((item) => [
            item.key,
            {
                name: item.create_name ?? '',
                email: item.source,
                preserve_password: Boolean(item.database?.preserve_by_default && item.database?.password_compatible),
            },
        ]),
    ),

    node_map: Object.fromEntries(props.sourceNodes.map((item) => [item.key, item.destination_id ?? ''])),

    comb_map: Object.fromEntries(props.sourceEggs.map((item) => [item.key, item.destination_external_id ?? (item.matched ? '' : '__create__')])),

    comb_create: Object.fromEntries(
        props.sourceEggs.map((item) => [
            item.key,
            {
                ...item.create,
            },
        ]),
    ),

    database_selection: Object.fromEntries(
        props.servers.map((server) => [
            server.id,
            server.database_plan?.length
                ? server.database_plan
                      .filter((database: any) => database.selected)
                      .map((database: any) => String(database.source?.id ?? database.source?.database ?? ''))
                : (server.source_metadata?.databases ?? []).map((database: any) => String(database.id ?? database.database ?? '')),
        ]),
    ),

    allocation_strategy: props.servers.find((server) => server.selected)?.allocation_strategy ?? 'preserve',
});

function migrationDuplicate(server: any) {
    return server.migration_duplicate ?? server.source_metadata?.migration_duplicate ?? null;
}

function serverSelectable(server: any) {
    return !migrationDuplicate(server);
}

const alreadyMigratedCount = computed(() => props.servers.filter((server) => !serverSelectable(server)).length);

const selectedCount = computed(() => form.selected_server_ids.length);

const selectedServers = computed(() => props.servers.filter((server) => form.selected_server_ids.includes(server.id)));

const requiredOwnerEmails = computed(() => new Set(selectedServers.value.map((server) => server.owner_email).filter(Boolean)));

const requiredNodeNames = computed(() => new Set(selectedServers.value.map((server) => server.source_node_name).filter(Boolean)));

const requiredEggNames = computed(() => new Set(selectedServers.value.map((server) => server.source_egg_name).filter(Boolean)));

const requiredOwners = computed(() => props.sourceOwners.filter((item) => requiredOwnerEmails.value.has(item.source)));

const requiredNodes = computed(() => props.sourceNodes.filter((item) => requiredNodeNames.value.has(item.source)));

const requiredEggs = computed(() => props.sourceEggs.filter((item) => requiredEggNames.value.has(item.source)));

const ownerMappedCount = computed(() => requiredOwners.value.filter((item) => Boolean(form.owner_map[item.key])).length);

const nodeMappedCount = computed(() => requiredNodes.value.filter((item) => Boolean(form.node_map[item.key])).length);

const eggMappedCount = computed(() => requiredEggs.value.filter((item) => Boolean(form.comb_map[item.key])).length);

const canContinue = computed(
    () =>
        selectedCount.value > 0 &&
        ownerMappedCount.value === requiredOwners.value.length &&
        nodeMappedCount.value === requiredNodes.value.length &&
        eggMappedCount.value === requiredEggs.value.length,
);

function toggleServer(server: any) {
    if (!serverSelectable(server)) {
        return;
    }

    if (form.selected_server_ids.includes(server.id)) {
        form.selected_server_ids = form.selected_server_ids.filter((value) => value !== server.id);
        return;
    }

    form.selected_server_ids = [...form.selected_server_ids, server.id];
}

function selectAll() {
    form.selected_server_ids = props.servers.filter((server) => serverSelectable(server)).map((server) => server.id);
}

function selectNone() {
    form.selected_server_ids = [];
}

function toggleOwnerTransfer(owner: any) {
    const next = !form.owner_transfer[owner.key];
    form.owner_transfer[owner.key] = next;

    if (!next && form.owner_map[owner.key] === '__create__') {
        form.owner_map[owner.key] = '';
    }
}

function databaseId(database: any) {
    return String(database.id ?? database.database ?? '');
}

function databaseSelected(serverId: string, database: any) {
    return (form.database_selection[serverId] ?? []).includes(databaseId(database));
}

function toggleDatabase(serverId: string, database: any) {
    const id = databaseId(database);
    const current = form.database_selection[serverId] ?? [];

    if (current.includes(id)) {
        form.database_selection[serverId] = current.filter((value: string) => value !== id);
        return;
    }

    form.database_selection[serverId] = [...current, id];
}

function submit() {
    form.patch(`/admin/migrations/${props.migration.id}/mapping`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${migration.name} - Map`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full border border-hive/30 bg-hive/10 px-3 py-1 text-xs font-black text-hive">
                                        {{ t('ui.step_2_of_3') }}
                                    </span>

                                    <span class="text-xs font-bold text-muted-foreground">
                                        {{ t('ui.discovery_mapping_review') }}
                                    </span>
                                </div>

                                <h1 class="mt-3 text-2xl font-black sm:text-3xl">
                                    {{ t('ui.map_migration') }}
                                </h1>

                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{ t('ui.map_the_discovered_source_inventory_to_hivepanel_before_anything_') }}
                                </p>
                            </div>

                            <Link
                                :href="`/admin/migrations/${migration.id}`"
                                class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back_to_discovery') }}
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.selected_servers') }}
                            </div>

                            <div class="mt-1 text-2xl font-black">{{ selectedCount }}/{{ servers.length - alreadyMigratedCount }}</div>

                            <div v-if="alreadyMigratedCount > 0" class="mt-1 text-xs font-bold text-status-warning">
                                {{ alreadyMigratedCount }} {{ t('ui.already_migrated') }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('ui.owners') }}
                            </div>

                            <div
                                class="mt-1 text-2xl font-black"
                                :class="ownerMappedCount === requiredOwners.length ? 'text-status-success' : 'text-status-warning'"
                            >
                                {{ ownerMappedCount }}/{{ requiredOwners.length }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('Nodes') }}
                            </div>

                            <div
                                class="mt-1 text-2xl font-black"
                                :class="nodeMappedCount === requiredNodes.length ? 'text-status-success' : 'text-status-warning'"
                            >
                                {{ nodeMappedCount }}/{{ requiredNodes.length }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                {{ t('Combs') }}
                            </div>

                            <div
                                class="mt-1 text-2xl font-black"
                                :class="eggMappedCount === requiredEggs.length ? 'text-status-success' : 'text-status-warning'"
                            >
                                {{ eggMappedCount }}/{{ requiredEggs.length }}
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-5 xl:grid-cols-3">
                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-2">
                                <Users class="size-5 text-hive" />
                                <h2 class="text-lg font-black">{{ t('ui.owner_mapping') }}</h2>
                            </div>

                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.exact_email_matches_are_selected_automatically') }}
                            </p>

                            <div class="mt-5 space-y-3">
                                <div v-for="owner in sourceOwners" :key="owner.key" class="rounded-button border border-border bg-surface-dark p-4">
                                    <div class="mb-3 flex items-start justify-between gap-3">
                                        <div class="break-all text-xs font-black text-muted-foreground">
                                            {{ owner.source }}
                                        </div>

                                        <button
                                            type="button"
                                            class="shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide transition"
                                            :class="
                                                form.owner_transfer[owner.key]
                                                    ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                    : 'border-border bg-surface-light text-muted-foreground'
                                            "
                                            @click="toggleOwnerTransfer(owner)"
                                        >
                                            {{ form.owner_transfer[owner.key] ? 'Transfer User' : 'Do Not Transfer' }}
                                        </button>
                                    </div>

                                    <p v-if="!form.owner_transfer[owner.key]" class="mb-3 text-xs leading-5 text-status-warning">
                                        {{ t('ui.select_an_existing_hivepanel_user_below_as_the_fallback_owner_for') }}
                                    </p>

                                    <select
                                        v-model="form.owner_map[owner.key]"
                                        class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                    >
                                        <option value="">{{ t('ui.select_hivepanel_user') }}</option>

                                        <option v-if="form.owner_transfer[owner.key]" value="__create__">
                                            {{ t('ui.create_new_hivepanel_user') }}
                                        </option>

                                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} — {{ user.email }}</option>
                                    </select>

                                    <div v-if="form.owner_transfer[owner.key] && form.owner_map[owner.key] === '__create__'" class="mt-3 grid gap-2">
                                        <div class="rounded-button border border-status-warning/20 bg-status-warning/5 p-3">
                                            <div class="text-[10px] font-black uppercase tracking-wide text-status-warning">
                                                {{ t('ui.will_create_during_execution') }}
                                            </div>

                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                                {{ t('ui.no_account_is_created_while_mapping_or_reviewing') }}
                                            </p>
                                        </div>

                                        <input
                                            v-model="form.owner_create[owner.key].name"
                                            type="text"
                                            :placeholder="t('ui.user_name')"
                                            class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                        />

                                        <input
                                            v-model="form.owner_create[owner.key].email"
                                            type="email"
                                            :placeholder="t('ui.email')"
                                            class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                        />

                                        <label
                                            v-if="owner.database?.password_available"
                                            class="flex cursor-pointer items-start gap-2 rounded-button border border-border bg-black/20 p-3"
                                        >
                                            <input
                                                v-model="form.owner_create[owner.key].preserve_password"
                                                type="checkbox"
                                                class="mt-0.5 size-4 accent-hive"
                                                :disabled="!owner.database?.password_compatible"
                                            />

                                            <div>
                                                <div class="text-xs font-black text-foreground">
                                                    {{ t('ui.preserve_existing_password') }}
                                                </div>

                                                <p
                                                    class="mt-1 text-xs leading-5"
                                                    :class="owner.database?.password_compatible ? 'text-muted-foreground' : 'text-status-warning'"
                                                >
                                                    <template v-if="owner.database?.password_compatible">
                                                        {{ owner.database?.password_hash_type }} {{ t('ui.hash_is_compatible_with_hivepanel') }}
                                                    </template>

                                                    <template v-else>
                                                        {{ owner.database?.password_hash_type || 'Source' }}
                                                        {{ t('ui.hash_is_not_compatible_with_hivepanel_s_configured_hasher_this_us') }}
                                                    </template>
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-2">
                                <Server class="size-5 text-hive" />
                                <h2 class="text-lg font-black">{{ t('ui.node_mapping') }}</h2>
                            </div>

                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.source_and_destination_nodes_with_the_same_name_are_selected_auto') }}
                            </p>

                            <div class="mt-5 space-y-3">
                                <div
                                    v-for="sourceNode in sourceNodes"
                                    :key="sourceNode.key"
                                    class="rounded-button border border-border bg-surface-dark p-4"
                                >
                                    <div class="mb-2 text-xs font-black text-muted-foreground">
                                        {{ sourceNode.source }}
                                    </div>

                                    <select
                                        v-model="form.node_map[sourceNode.key]"
                                        class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                    >
                                        <option value="">{{ t('ui.select_hivepanel_node') }}</option>

                                        <option v-for="node in nodes" :key="node.id" :value="node.id">
                                            {{ node.name }}{{ node.location ? ` — ${node.location}` : '' }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-2">
                                <Boxes class="size-5 text-hive" />
                                <h2 class="text-lg font-black">{{ t('ui.egg_comb_mapping') }}</h2>
                            </div>

                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('ui.hivepanel_suggests_a_comb_from_the_egg_name_where_possible') }}
                            </p>

                            <div class="mt-5 space-y-3">
                                <div v-for="egg in sourceEggs" :key="egg.key" class="rounded-button border border-border bg-surface-dark p-4">
                                    <div class="mb-2 text-xs font-black text-muted-foreground">
                                        {{ egg.source }}
                                    </div>

                                    <select
                                        v-model="form.comb_map[egg.key]"
                                        class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                    >
                                        <option value="">{{ t('ui.select_hivepanel_comb') }}</option>

                                        <option value="__create__">
                                            {{ t('ui.create_draft_comb_from_pterodactyl_egg') }}
                                        </option>

                                        <option v-for="comb in combs" :key="comb.id" :value="comb.external_id">
                                            {{ comb.game }} — {{ comb.name }}
                                        </option>
                                    </select>

                                    <div v-if="form.comb_map[egg.key] === '__create__'" class="mt-3 space-y-2">
                                        <div class="rounded-button border border-status-warning/20 bg-status-warning/5 p-3">
                                            <div class="text-[10px] font-black uppercase tracking-wide text-status-warning">
                                                {{ t('ui.draft_comb_will_be_created') }}
                                            </div>

                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                                {{ t('ui.review_the_generated_definition_before_execution') }}
                                            </p>
                                        </div>

                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <input
                                                v-model="form.comb_create[egg.key].name"
                                                type="text"
                                                :placeholder="t('ui.comb_name')"
                                                class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                            />

                                            <input
                                                v-model="form.comb_create[egg.key].game"
                                                type="text"
                                                :placeholder="t('ui.game')"
                                                class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                            />
                                        </div>

                                        <input
                                            v-model="form.comb_create[egg.key].external_id"
                                            type="text"
                                            :placeholder="t('ui.external_id')"
                                            class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 font-mono text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                        />

                                        <input
                                            v-model="form.comb_create[egg.key].docker_image"
                                            type="text"
                                            :placeholder="t('ui.docker_image_74c0856')"
                                            class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 font-mono text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                        />

                                        <textarea
                                            v-model="form.comb_create[egg.key].startup"
                                            rows="3"
                                            :placeholder="t('ui.startup_command_2b0d42d')"
                                            class="w-full rounded-button border border-border bg-black/30 px-3 py-2.5 font-mono text-xs font-bold text-foreground outline-none transition focus:border-hive"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <Network class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">{{ t('ui.allocation_strategy') }}</h2>
                                </div>

                                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                                    {{ t('ui.preserve_keeps_the_source_ip_port_intent_for_the_execution_planne') }}
                                </p>
                            </div>

                            <div class="grid min-w-[320px] grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="rounded-button border px-4 py-3 text-sm font-black transition"
                                    :class="
                                        form.allocation_strategy === 'preserve'
                                            ? 'border-hive bg-hive/10 text-hive'
                                            : 'border-border bg-surface-dark text-muted-foreground'
                                    "
                                    @click="form.allocation_strategy = 'preserve'"
                                >
                                    {{ t('ui.preserve') }}
                                </button>

                                <button
                                    type="button"
                                    class="rounded-button border px-4 py-3 text-sm font-black transition"
                                    :class="
                                        form.allocation_strategy === 'allocate_new'
                                            ? 'border-hive bg-hive/10 text-hive'
                                            : 'border-border bg-surface-dark text-muted-foreground'
                                    "
                                    @click="form.allocation_strategy = 'allocate_new'"
                                >
                                    {{ t('ui.allocate_new') }}
                                </button>
                            </div>
                        </div>
                    </section>

                    <section v-if="alreadyMigratedCount > 0" class="rounded-panel border border-status-warning/30 bg-status-warning/10 p-4">
                        <div class="flex items-start gap-3">
                            <CircleAlert class="mt-0.5 size-5 shrink-0 text-status-warning" />

                            <div>
                                <div class="text-sm font-black text-status-warning">
                                    {{ alreadyMigratedCount }} {{ t('ui.server') }}{{ alreadyMigratedCount === 1 ? '' : 's' }}
                                    {{ t('ui.already_migrated') }}
                                </div>

                                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.hivepanel_found_an_existing_destination_cell_for_the_same_source_') }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-border bg-surface">
                        <div class="flex flex-col gap-3 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                            <div>
                                <h2 class="text-lg font-black">{{ t('ui.servers_to_migrate') }}</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.deselect_test_or_retired_servers_you_do_not_want_included_servers') }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-button border border-border bg-surface-dark px-3 py-2 text-xs font-black text-foreground"
                                    @click="selectAll"
                                >
                                    {{ t('ui.select_all_d1ec69e') }}
                                </button>

                                <button
                                    type="button"
                                    class="rounded-button border border-border bg-surface-dark px-3 py-2 text-xs font-black text-foreground"
                                    @click="selectNone"
                                >
                                    {{ t('ui.select_none') }}
                                </button>
                            </div>
                        </div>

                        <div class="divide-y divide-border">
                            <div
                                v-for="server in servers"
                                :key="server.id"
                                class="p-4 transition sm:px-6"
                                :class="serverSelectable(server) ? 'hover:bg-surface-light/40' : 'bg-status-warning/[0.03]'"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-4 text-left"
                                    :class="serverSelectable(server) ? '' : 'cursor-not-allowed opacity-75'"
                                    :disabled="!serverSelectable(server)"
                                    @click="toggleServer(server)"
                                >
                                    <div
                                        class="flex size-5 shrink-0 items-center justify-center rounded border"
                                        :class="
                                            !serverSelectable(server)
                                                ? 'border-status-warning/40 bg-status-warning/10 text-status-warning'
                                                : form.selected_server_ids.includes(server.id)
                                                  ? 'border-hive bg-hive text-black'
                                                  : 'border-border bg-surface-dark'
                                        "
                                    >
                                        <Check v-if="form.selected_server_ids.includes(server.id)" class="size-3.5" />

                                        <CircleAlert v-else-if="!serverSelectable(server)" class="size-3.5" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="font-black text-foreground">
                                                {{ server.name }}
                                            </div>

                                            <a
                                                v-if="migrationDuplicate(server)"
                                                :href="`/admin/cells/${migrationDuplicate(server).cell_id}`"
                                                class="inline-flex items-center gap-1 rounded-full border border-status-warning/30 bg-status-warning/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-status-warning transition hover:bg-status-warning/20"
                                                @click.stop
                                            >
                                                {{ t('ui.already_migrated_fb2f185') }} {{ migrationDuplicate(server).cell_name }}
                                            </a>
                                        </div>

                                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            <span>{{ server.owner_email }}</span>
                                            <span>{{ server.source_node_name }}</span>
                                            <span>{{ server.source_egg_name }}</span>

                                            <span v-if="(server.source_metadata?.databases ?? []).length > 0">
                                                {{ (server.source_metadata?.databases ?? []).length }} {{ t('ui.database')
                                                }}{{ (server.source_metadata?.databases ?? []).length === 1 ? '' : 's' }}
                                            </span>
                                        </div>
                                    </div>
                                </button>

                                <div
                                    v-if="form.selected_server_ids.includes(server.id) && (server.source_metadata?.databases ?? []).length > 0"
                                    class="ml-9 mt-4 rounded-button border border-border bg-black/20 p-3"
                                >
                                    <div class="mb-2 text-[10px] font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.databases_to_transfer') }}
                                    </div>

                                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                        <button
                                            v-for="database in server.source_metadata.databases"
                                            :key="databaseId(database)"
                                            type="button"
                                            class="flex items-start gap-2 rounded-button border p-3 text-left transition"
                                            :class="
                                                databaseSelected(server.id, database) ? 'border-hive/40 bg-hive/10' : 'border-border bg-surface-dark'
                                            "
                                            @click="toggleDatabase(server.id, database)"
                                        >
                                            <div
                                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border"
                                                :class="databaseSelected(server.id, database) ? 'border-hive bg-hive text-black' : 'border-border'"
                                            >
                                                <Check v-if="databaseSelected(server.id, database)" class="size-3" />
                                            </div>

                                            <div class="min-w-0">
                                                <div class="truncate font-mono text-xs font-black text-foreground">
                                                    {{ database.database || `Database #${database.id}` }}
                                                </div>

                                                <div class="mt-1 truncate text-[11px] text-muted-foreground">
                                                    {{ database.host?.name || database.host?.host || 'Unknown host' }}
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="form.errors.mapping"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger"
                    >
                        <div class="flex items-start gap-2">
                            <CircleAlert class="mt-0.5 size-4 shrink-0" />
                            {{ form.errors.mapping }}
                        </div>
                    </section>

                    <section
                        class="flex flex-col gap-3 rounded-panel border border-border bg-surface p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"
                    >
                        <div>
                            <div class="font-black text-foreground">{{ selectedCount }} {{ t('ui.servers_selected') }}</div>

                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ t('ui.no_cells_will_be_created_until_the_execution_stage') }}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="form.processing || !canContinue"
                            @click="submit"
                        >
                            {{ form.processing ? 'Saving...' : 'Save & Review' }}
                            <ArrowRight class="size-4" />
                        </button>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
