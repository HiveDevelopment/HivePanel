<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Bug, HardDrive, RefreshCw, Save, Server, Trash, Wrench } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    cell: any;
    shutdownCommandOverride?: string;
}>();

const shutdownCommand = ref(props.shutdownCommandOverride ?? '');
const savingShutdown = ref(false);

const saving = ref(false);
const runningUtility = ref('');
const error = ref('');
const toastMessage = ref('');

const form = ref({
    name: props.cell.name ?? '',
    timezone: props.cell.timezone ?? 'UTC',
    jarfile: props.cell.jarfile ?? props.cell.variables?.jarfile ?? 'server.jar',
    world_name: props.cell.world_name ?? props.cell.variables?.world_name ?? 'world',
});

const cellId = computed(() => props.cell?.id);

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function showToast(message: string) {
    toastMessage.value = message;
    setTimeout(() => (toastMessage.value = ''), 3000);
}

async function saveShutdown() {
    if (!cellId.value || savingShutdown.value) return;
    savingShutdown.value = true;
    error.value = '';
    try {
        const response = await fetch(`/cells/${cellId.value}/settings/shutdown`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ shutdown_command: shutdownCommand.value }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            error.value = data.errors?.shutdown_command?.[0] ?? data.message ?? 'The shutdown command could not be saved.';
            return;
        }
        showToast(data.message);
    } catch {
        error.value = 'The shutdown command could not be saved. Check your connection and try again.';
    } finally {
        savingShutdown.value = false;
    }
}

async function saveSettings() {
    if (!cellId.value) return;

    saving.value = true;
    error.value = '';

    try {
        const response = await fetch(`/cells/${cellId.value}/settings`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(form.value),
        });

        if (!response.ok) {
            error.value = await response.text();
            return;
        }

        showToast('Settings saved.');
    } finally {
        saving.value = false;
    }
}

async function runUtility(utility: string) {
    if (!cellId.value) return;
    if (!confirm(`Run ${utility.replace('-', ' ')}?`)) return;

    runningUtility.value = utility;
    error.value = '';

    try {
        const response = await fetch(`/cells/${cellId.value}/utilities/${utility}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        if (!response.ok) {
            error.value = await response.text();
            return;
        }

        showToast('Utility started.');
    } finally {
        runningUtility.value = '';
    }
}
</script>

<template>
    <AppLayout :context="'server'" :active-cell="cell" :active-cell-status="cell.status ?? 'offline'">
        <Head :title="`${cell.name} Settings`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <h1 class="text-2xl font-black sm:text-3xl">
                            {{ t('ui.server_settings') }}
                        </h1>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ t('ui.configure_panel_display_settings_startup_defaults_utilities_and_d') }}
                        </p>
                    </section>

                    <div
                        v-if="error"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger"
                    >
                        {{ error }}
                    </div>

                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <h2 class="text-lg font-black">{{ t('ui.shutdown_command_override') }}</h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ t('ui.leave_blank_to_use_the_comb_command_enter_one_console_command_for') }}
                        </p>
                        <input
                            v-model="shutdownCommand"
                            maxlength="1000"
                            :placeholder="t('ui.use_comb_default')"
                            class="mt-4 w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                        />
                        <button
                            type="button"
                            :disabled="savingShutdown"
                            class="mt-4 rounded-button bg-hive px-4 py-2 text-sm font-bold text-black disabled:opacity-50"
                            @click="saveShutdown"
                        >
                            {{ savingShutdown ? 'Saving...' : 'Save shutdown command' }}
                        </button>
                    </section>

                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <Server class="size-5 text-hive" />
                            <h2 class="text-lg font-black">{{ t('ui.general') }}</h2>
                        </div>

                        <div class="mt-5 grid gap-4 lg:grid-cols-2">
                            <label class="space-y-2">
                                <span class="text-sm font-bold text-muted-foreground">{{ t('ui.server_name') }}</span>
                                <input
                                    v-model="form.name"
                                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                />
                            </label>

                            <label class="space-y-2">
                                <span class="text-sm font-bold text-muted-foreground">{{ t('ui.timezone') }}</span>
                                <select
                                    v-model="form.timezone"
                                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 text-sm text-foreground outline-none focus:border-hive"
                                >
                                    <option value="UTC">{{ t('ui.utc') }}</option>
                                    <option value="Europe/London">{{ t('ui.europe_london') }}</option>
                                    <option value="Europe/Paris">{{ t('ui.europe_paris') }}</option>
                                    <option value="America/New_York">{{ t('ui.america_new_york') }}</option>
                                    <option value="America/Chicago">{{ t('ui.america_chicago') }}</option>
                                    <option value="America/Los_Angeles">{{ t('ui.america_los_angeles') }}</option>
                                    <option value="Asia/Singapore">{{ t('ui.asia_singapore') }}</option>
                                    <option value="Australia/Sydney">{{ t('ui.australia_sydney') }}</option>
                                </select>
                            </label>

                            <label class="space-y-2">
                                <span class="text-sm font-bold text-muted-foreground">{{ t('ui.jar_file') }}</span>
                                <input
                                    v-model="form.jarfile"
                                    :placeholder="t('ui.server_jar')"
                                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 font-mono text-sm text-foreground outline-none focus:border-hive"
                                />
                            </label>

                            <label class="space-y-2">
                                <span class="text-sm font-bold text-muted-foreground">{{ t('ui.world_name') }}</span>
                                <input
                                    v-model="form.world_name"
                                    :placeholder="t('ui.world')"
                                    class="w-full rounded-button border border-border bg-surface-light px-4 py-3 font-mono text-sm text-foreground outline-none focus:border-hive"
                                />
                            </label>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button
                                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-primary-foreground transition hover:bg-hive-light disabled:opacity-50"
                                :disabled="saving"
                                @click="saveSettings"
                            >
                                <Save class="size-4" />
                                {{ saving ? 'Saving...' : 'Save Settings' }}
                            </button>
                        </div>
                    </section>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-4">
                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <Wrench class="size-5 text-hive" />
                                <h2 class="text-lg font-black">{{ t('ui.tasks_utilities') }}</h2>
                            </div>

                            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                <button
                                    class="rounded-button border border-border bg-surface-light p-4 text-left transition hover:border-hive hover:text-hive"
                                    @click="runUtility('clear-logs')"
                                >
                                    <RefreshCw class="mb-3 size-5" />
                                    <div class="font-black">{{ t('ui.clear_logs') }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ t('ui.remove_old_log_files') }}</div>
                                </button>

                                <button
                                    class="rounded-button border border-border bg-surface-light p-4 text-left transition hover:border-hive hover:text-hive"
                                    @click="runUtility('repair-permissions')"
                                >
                                    <Wrench class="mb-3 size-5" />
                                    <div class="font-black">{{ t('ui.repair_permissions') }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ t('ui.fix_file_ownership_issues') }}</div>
                                </button>

                                <button
                                    class="rounded-button border border-status-danger/30 bg-status-danger/10 p-4 text-left text-status-danger transition hover:border-status-danger"
                                    @click="runUtility('reset-world')"
                                >
                                    <HardDrive class="mb-3 size-5" />
                                    <div class="font-black">{{ t('ui.reset_world') }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ t('ui.delete_and_recreate_world_files') }}</div>
                                </button>

                                <button
                                    class="rounded-button border border-status-danger/30 bg-status-danger/10 p-4 text-left text-status-danger transition hover:border-status-danger"
                                    @click="runUtility('reset-files')"
                                >
                                    <Trash class="mb-3 size-5" />
                                    <div class="font-black">{{ t('ui.reset_all_files') }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ t('ui.wipe_server_files_completely') }}</div>
                                </button>
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <Bug class="size-5 text-hive" />
                                <h2 class="text-lg font-black">{{ t('ui.debug_information') }}</h2>
                            </div>

                            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                <div class="rounded-button border border-border bg-surface-light p-4">
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.server_id') }}</div>
                                    <div class="mt-2 break-all font-mono text-sm text-foreground">{{ cell.uuid ?? cell.id }}</div>
                                </div>

                                <div class="rounded-button border border-border bg-surface-light p-4">
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.internal_id') }}</div>
                                    <div class="mt-2 break-all font-mono text-sm text-foreground">{{ cell.id }}</div>
                                </div>

                                <div class="rounded-button border border-border bg-surface-light p-4">
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.node') }}</div>
                                    <div class="mt-2 font-mono text-sm text-foreground">{{ cell.node?.name ?? 'worker-01' }}</div>
                                </div>

                                <div class="rounded-button border border-border bg-surface-light p-4">
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">{{ t('ui.allocation') }}</div>
                                    <div class="mt-2 font-mono text-sm text-foreground">
                                        {{ cell.allocation?.ip ?? '127.0.0.1' }}:{{ cell.allocation?.port ?? '25565' }}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-status-danger/30 bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black">
                                {{ t('ui.reinstall_server') }}
                            </h2>

                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                {{ t('ui.permanently_delete_all_server_files_and_install_a_fresh_copy_usin') }}
                            </p>

                            <Link
                                :href="`/cells/${cell.id}/reinstall`"
                                class="mt-5 inline-flex items-center gap-2 rounded-button border border-status-danger/40 bg-status-danger/10 px-4 py-2.5 text-sm font-black text-status-danger transition hover:border-status-danger hover:bg-status-danger/20"
                            >
                                <RefreshCw class="size-4" />
                                {{ t('ui.reinstall_server') }}
                            </Link>
                        </section>
                    </div>
                </div>
            </main>
        </div>

        <div
            v-if="toastMessage"
            class="fixed bottom-5 right-5 z-[60] rounded-button border border-status-success/40 bg-status-success/15 px-5 py-3 text-sm font-bold text-status-success shadow-[0_20px_70px_rgba(0,0,0,0.45)]"
        >
            {{ toastMessage }}
        </div>
    </AppLayout>
</template>
