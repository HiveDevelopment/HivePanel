<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Database, KeyRound, ServerCog, ShieldCheck } from 'lucide-vue-next';

const form = useForm({
    source_type: 'pterodactyl',
    name: '',
    panel_url: '',
    api_key: '',

    database_enabled: false,
    database_host: '127.0.0.1',
    database_port: 3306,
    database_name: 'panel',
    database_username: '',
    database_password: '',
    preserve_passwords: true,
});

function submit() {
    form.post('/admin/migrations', {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="t('ui.new_migration')" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <ServerCog class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        {{ t('ui.new_platform_migration') }}
                                    </h1>

                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.connect_hivepanel_to_the_source_hosting_panel_and_discover_its_se') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/migrations"
                                class="inline-flex items-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back') }}
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label class="text-sm font-bold text-muted-foreground">
                                    {{ t('ui.source_platform') }}
                                </label>

                                <select
                                    v-model="form.source_type"
                                    class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive"
                                >
                                    <option value="pterodactyl">
                                        {{ t('ui.pterodactyl') }}
                                    </option>
                                    <option value="pterodactyl_fork">
                                        {{ t('ui.pterodactyl_fork') }}
                                    </option>
                                </select>

                                <p class="mt-2 text-xs leading-5 text-muted-foreground">
                                    {{ t('ui.use_pterodactyl_fork_for_panels_based_on_or_compatible_with_ptero') }}
                                </p>
                            </div>

                            <div>
                                <label class="text-sm font-bold text-muted-foreground">
                                    {{ t('ui.migration_name') }}
                                </label>

                                <input
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="t('ui.old_production_panel')"
                                    class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                />

                                <div v-if="form.errors.name" class="mt-1 text-xs font-bold text-status-danger">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label class="text-sm font-bold text-muted-foreground">
                                    {{ t('ui.panel_url') }}
                                </label>

                                <input
                                    v-model="form.panel_url"
                                    type="url"
                                    placeholder="https://panel.example.com"
                                    class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 font-mono text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                />

                                <div v-if="form.errors.panel_url" class="mt-1 text-xs font-bold text-status-danger">
                                    {{ form.errors.panel_url }}
                                </div>
                            </div>

                            <div>
                                <label class="text-sm font-bold text-muted-foreground">
                                    {{ t('ui.api_key') }}
                                </label>

                                <div class="relative mt-2">
                                    <KeyRound class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />

                                    <input
                                        v-model="form.api_key"
                                        type="password"
                                        autocomplete="off"
                                        :placeholder="t('ui.ptla')"
                                        class="w-full rounded-button border border-border bg-surface-dark py-3 pl-10 pr-4 font-mono text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive"
                                    />
                                </div>

                                <div v-if="form.errors.api_key" class="mt-1 text-xs font-bold text-status-danger">
                                    {{ form.errors.api_key }}
                                </div>
                            </div>

                            <div class="rounded-panel border border-border bg-surface-dark p-4 sm:p-5">
                                <div class="flex items-start gap-3">
                                    <Database class="mt-0.5 size-5 shrink-0 text-hive" />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <div class="text-sm font-black text-foreground">
                                                    {{ t('ui.advanced_database_migration') }}
                                                </div>

                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                                    {{ t('ui.optional_read_only_access_to_the_source_panel_database_enables_pa') }}
                                                </p>
                                            </div>

                                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-black text-foreground">
                                                <input v-model="form.database_enabled" type="checkbox" class="size-4 accent-hive" />
                                                {{ t('ui.enable') }}
                                            </label>
                                        </div>

                                        <div v-if="form.database_enabled" class="mt-5 space-y-4">
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label class="text-xs font-black text-muted-foreground">
                                                        {{ t('ui.database_host') }}
                                                    </label>

                                                    <input
                                                        v-model="form.database_host"
                                                        class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                    />
                                                </div>

                                                <div>
                                                    <label class="text-xs font-black text-muted-foreground">
                                                        {{ t('ui.port') }}
                                                    </label>

                                                    <input
                                                        v-model.number="form.database_port"
                                                        type="number"
                                                        class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                    />
                                                </div>

                                                <div>
                                                    <label class="text-xs font-black text-muted-foreground">
                                                        {{ t('ui.database_name') }}
                                                    </label>

                                                    <input
                                                        v-model="form.database_name"
                                                        class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                    />
                                                </div>

                                                <div>
                                                    <label class="text-xs font-black text-muted-foreground">
                                                        {{ t('ui.username') }}
                                                    </label>

                                                    <input
                                                        v-model="form.database_username"
                                                        class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                    />
                                                </div>

                                                <div class="sm:col-span-2">
                                                    <label class="text-xs font-black text-muted-foreground">
                                                        {{ t('ui.password') }}
                                                    </label>

                                                    <input
                                                        v-model="form.database_password"
                                                        type="password"
                                                        autocomplete="off"
                                                        class="mt-1 w-full rounded-button border border-border bg-black/30 px-3 py-2.5 text-sm font-bold text-foreground outline-none focus:border-hive"
                                                    />
                                                </div>
                                            </div>

                                            <label class="flex cursor-pointer items-start gap-3 rounded-button border border-border bg-black/20 p-3">
                                                <input v-model="form.preserve_passwords" type="checkbox" class="mt-0.5 size-4 accent-hive" />

                                                <div>
                                                    <div class="text-xs font-black text-foreground">
                                                        {{ t('ui.preserve_compatible_user_password_hashes') }}
                                                    </div>

                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                                        {{ t('ui.compatible_source_hashes_can_be_copied_directly_when_hivepanel_cr') }}
                                                    </p>
                                                </div>
                                            </label>

                                            <p class="text-xs leading-5 text-muted-foreground">
                                                {{ t('ui.use_a_read_only_mysql_mariadb_account_where_possible_hivepanel_on') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-button border border-hive/20 bg-hive/5 p-4">
                                <div class="flex items-start gap-3">
                                    <ShieldCheck class="mt-0.5 size-5 shrink-0 text-hive" />

                                    <div>
                                        <div class="text-sm font-black text-foreground">
                                            {{ t('ui.source_credentials_are_encrypted') }}
                                        </div>

                                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                            {{ t('ui.hivepanel_stores_the_api_key_using_laravel_s_encrypted_cast_we_wi') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-3 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="form.processing"
                            >
                                <ServerCog class="size-4" :class="{ 'animate-pulse': form.processing }" />
                                {{ form.processing ? 'Creating Migration...' : 'Create Migration' }}
                            </button>

                            <p v-if="form.processing" class="text-center text-xs font-bold text-muted-foreground">
                                {{ t('ui.hivepanel_will_create_the_migration_immediately_and_queue_source_') }}
                            </p>
                        </form>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
