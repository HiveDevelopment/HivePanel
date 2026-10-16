<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, ChevronDown, Clipboard, CpuIcon, Download, HardDrive, KeyRound, ShieldCheck, Terminal } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import NodeNavigation from './NodeNavigation.vue';

const props = defineProps<{
    node: any;
    registrationToken?: string | null;
    workerYaml: string;
    systemdService: string;
    commands: string[];
    installScriptUrl: string;
    oneClickCommand: string;
}>();

const copied = ref<string | null>(null);
const generating = ref(false);
const setupMode = ref<'one-click' | 'manual'>('one-click');
const installExpanded = ref(!props.node.is_registered);

const token = computed(() => props.registrationToken || null);

async function copy(text: string, key: string) {
    await navigator.clipboard.writeText(text);

    copied.value = key;

    window.setTimeout(() => {
        if (copied.value === key) copied.value = null;
    }, 1800);
}

function generateToken() {
    generating.value = true;

    router.post(
        `/admin/nodes/${props.node.id}/registration-token`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                generating.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${node.name} Configuration`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            {{ node.name }}
                                        </h1>

                                        <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                            {{ node.location }}
                                        </span>

                                        <span
                                            class="rounded-full border border-border bg-surface-light px-2 py-0.5 text-xs font-bold text-muted-foreground"
                                        >
                                            {{ node.scheme }}
                                        </span>
                                    </div>

                                    <p class="mt-2 font-mono text-sm text-muted-foreground">
                                        {{ node.fqdn }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    href="/admin/nodes"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                                >
                                    <ArrowLeft class="size-4" />
                                    {{ t('ui.back') }}
                                </Link>
                            </div>
                        </div>
                    </section>

                    <NodeNavigation :node-id="node.id" active="configuration" />

                    <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
                        <div class="space-y-5">
                            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-start gap-3">
                                        <HardDrive class="mt-0.5 size-5 text-hive" />

                                        <div>
                                            <h2 class="text-lg font-black text-foreground">
                                                {{ t('ui.allocations') }}
                                            </h2>

                                            <p class="mt-1 text-sm text-muted-foreground">
                                                {{ t('ui.allocation_addresses_are_managed_from_the_dedicated_allocations_p') }}
                                            </p>
                                        </div>
                                    </div>

                                    <Link
                                        :href="`/admin/nodes/${node.id}/allocations`"
                                        class="inline-flex items-center justify-center rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive hover:text-hive"
                                    >
                                        {{ t('ui.manage_allocations') }}
                                    </Link>
                                </div>

                                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-button border border-border bg-surface-dark p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.total') }}
                                        </div>

                                        <div class="mt-1 text-2xl font-black text-foreground">
                                            {{ node.allocation_count ?? 0 }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-border bg-surface-dark p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.available') }}
                                        </div>

                                        <div class="mt-1 text-2xl font-black text-status-success">
                                            {{ node.available_allocation_count ?? 0 }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-border bg-surface-dark p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                            {{ t('ui.assigned') }}
                                        </div>

                                        <div class="mt-1 text-2xl font-black text-hive">
                                            {{ node.assigned_allocation_count ?? 0 }}
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section v-if="node.is_registered" class="rounded-panel border border-status-success/30 bg-status-success/10 p-5 sm:p-6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-start gap-3">
                                        <ShieldCheck class="mt-0.5 size-5 text-status-success" />

                                        <div>
                                            <h2 class="text-lg font-black text-foreground">
                                                {{ t('ui.worker_installed') }}
                                            </h2>

                                            <p class="mt-1 text-sm text-muted-foreground">
                                                {{ t('ui.this_node_is_registered_and_actively_configured_installation_deta') }}
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive hover:text-hive"
                                        @click="installExpanded = !installExpanded"
                                    >
                                        {{ installExpanded ? 'Hide Install Details' : 'Show Install Details' }}

                                        <ChevronDown class="size-4 transition-transform" :class="installExpanded ? 'rotate-180' : ''" />
                                    </button>
                                </div>
                            </section>

                            <template v-if="installExpanded">
                                <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                        <div class="flex items-center gap-3">
                                            <KeyRound class="size-5 text-hive" />

                                            <div>
                                                <h2 class="text-lg font-black">{{ t('ui.registration_token') }}</h2>
                                                <p class="mt-1 text-sm text-muted-foreground">
                                                    {{
                                                        node.is_registered
                                                            ? 'Generate a fresh one-time token only if you are reinstalling or re-registering this Worker.'
                                                            : 'Generate a one-time token used by the Worker to register with the panel.'
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <button
                                            class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:opacity-90 disabled:opacity-50"
                                            :disabled="generating"
                                            @click="generateToken"
                                        >
                                            <KeyRound class="size-4" />
                                            {{ generating ? 'Generating...' : node.is_registered ? 'Generate New Token' : 'Generate Token' }}
                                        </button>
                                    </div>

                                    <div v-if="token" class="mt-5 rounded-button border border-status-success/30 bg-status-success/10 p-4">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div>
                                                <div class="text-xs font-black uppercase tracking-wide text-status-success">
                                                    {{ t('ui.token_generated') }}
                                                </div>

                                                <div class="mt-2 break-all font-mono text-sm font-bold text-foreground">
                                                    {{ token }}
                                                </div>

                                                <p class="mt-2 text-xs font-bold text-muted-foreground">
                                                    {{ t('ui.copy_this_now_it_will_only_be_shown_once') }}
                                                </p>
                                            </div>

                                            <button
                                                class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-light px-3 py-2 text-xs font-black text-foreground transition hover:border-hive hover:text-hive"
                                                @click="copy(token, 'token')"
                                            >
                                                <Check v-if="copied === 'token'" class="size-4" />
                                                <Clipboard v-else class="size-4" />
                                                {{ copied === 'token' ? 'Copied' : 'Copy' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-else
                                        class="mt-5 rounded-button border border-border bg-surface-dark p-4 text-sm font-bold text-muted-foreground"
                                    >
                                        {{
                                            node.is_registered
                                                ? 'No fresh registration token has been generated.'
                                                : 'Generate a registration token before installing the Worker.'
                                        }}
                                    </div>
                                </section>

                                <section class="rounded-panel border border-border bg-surface p-1">
                                    <div class="grid grid-cols-2 gap-1">
                                        <button
                                            type="button"
                                            class="rounded-button px-4 py-3 text-sm font-black transition"
                                            :class="
                                                setupMode === 'one-click'
                                                    ? 'bg-hive/10 text-hive'
                                                    : 'text-muted-foreground hover:bg-surface-light hover:text-foreground'
                                            "
                                            @click="setupMode = 'one-click'"
                                        >
                                            {{ t('ui.one_click_installer') }}
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-button px-4 py-3 text-sm font-black transition"
                                            :class="
                                                setupMode === 'manual'
                                                    ? 'bg-hive/10 text-hive'
                                                    : 'text-muted-foreground hover:bg-surface-light hover:text-foreground'
                                            "
                                            @click="setupMode = 'manual'"
                                        >
                                            {{ t('ui.manual_setup') }}
                                        </button>
                                    </div>
                                </section>

                                <template v-if="setupMode === 'one-click'">
                                    <section class="rounded-panel border border-hive/30 bg-hive/10 p-5 sm:p-6">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                            <div class="flex items-start gap-3">
                                                <Download class="mt-1 size-5 text-hive" />

                                                <div>
                                                    <h2 class="text-lg font-black text-foreground">
                                                        {{ t('ui.one_command_install') }}
                                                    </h2>

                                                    <p class="mt-1 text-sm text-muted-foreground">
                                                        {{ t('ui.run_this_command_on_the_target_node_as_root_or_with_sudo') }}
                                                    </p>
                                                </div>
                                            </div>

                                            <button
                                                v-if="token"
                                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:opacity-90"
                                                @click="copy(oneClickCommand, 'oneClick')"
                                            >
                                                <Check v-if="copied === 'oneClick'" class="size-4" />
                                                <Clipboard v-else class="size-4" />
                                                {{ copied === 'oneClick' ? 'Copied' : 'Copy Command' }}
                                            </button>
                                        </div>

                                        <div v-if="token" class="mt-5 rounded-button border border-border bg-surface-dark p-4">
                                            <code class="block break-all font-mono text-sm leading-6 text-foreground">
                                                {{ oneClickCommand }}
                                            </code>
                                        </div>

                                        <div
                                            v-else
                                            class="mt-5 rounded-button border border-border bg-surface-dark p-4 text-sm font-bold text-muted-foreground"
                                        >
                                            {{ t('ui.generate_a_registration_token_first') }}
                                        </div>
                                    </section>

                                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                        <div class="flex items-center gap-3">
                                            <ShieldCheck class="size-5 text-hive" />
                                            <h2 class="text-lg font-black">{{ t('ui.what_the_installer_does') }}</h2>
                                        </div>

                                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                                            <div
                                                class="rounded-button border border-border bg-surface-dark p-4 text-sm font-bold text-muted-foreground"
                                            >
                                                {{ t('ui.creates') }} <span class="font-mono text-foreground">/etc/hivepanel/worker.yml</span>
                                            </div>

                                            <div
                                                class="rounded-button border border-border bg-surface-dark p-4 text-sm font-bold text-muted-foreground"
                                            >
                                                {{ t('ui.creates_required_data_directories') }}
                                            </div>

                                            <div
                                                class="rounded-button border border-border bg-surface-dark p-4 text-sm font-bold text-muted-foreground"
                                            >
                                                {{ t('ui.installs_the_systemd_service') }}
                                            </div>

                                            <div
                                                class="rounded-button border border-border bg-surface-dark p-4 text-sm font-bold text-muted-foreground"
                                            >
                                                {{ t('ui.starts_hiveworker_automatically') }}
                                            </div>
                                        </div>

                                        <p class="mt-5 text-xs font-bold text-muted-foreground">
                                            {{ t('ui.installer_url') }}
                                            <span class="font-mono text-muted-foreground">{{ installScriptUrl }}</span>
                                        </p>
                                    </section>
                                </template>

                                <template v-else>
                                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div>
                                                <h2 class="text-lg font-black">worker.yml</h2>
                                                <p class="mt-1 text-sm text-muted-foreground">
                                                    {{ t('ui.save_this_file_at') }}
                                                    <span class="font-mono text-foreground">/etc/hivepanel/worker.yml</span>.
                                                </p>
                                            </div>

                                            <button
                                                class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-light px-3 py-2 text-xs font-black text-foreground transition hover:border-hive hover:text-hive"
                                                @click="copy(workerYaml, 'workerYaml')"
                                            >
                                                <Check v-if="copied === 'workerYaml'" class="size-4" />
                                                <Clipboard v-else class="size-4" />
                                                {{ copied === 'workerYaml' ? 'Copied' : 'Copy YAML' }}
                                            </button>
                                        </div>

                                        <pre
                                            class="max-h-[420px] overflow-auto rounded-button border border-border bg-surface-dark p-4 text-sm leading-6 text-foreground"
                                        ><code>{{ workerYaml }}</code></pre>
                                    </section>

                                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div>
                                                <h2 class="text-lg font-black">{{ t('ui.systemd_service') }}</h2>
                                                <p class="mt-1 text-sm text-muted-foreground">
                                                    {{ t('ui.save_this_file_at') }}
                                                    <span class="font-mono text-foreground">/etc/systemd/system/hivepanel-worker.service</span>.
                                                </p>
                                            </div>

                                            <button
                                                class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-light px-3 py-2 text-xs font-black text-foreground transition hover:border-hive hover:text-hive"
                                                @click="copy(systemdService, 'systemd')"
                                            >
                                                <Check v-if="copied === 'systemd'" class="size-4" />
                                                <Clipboard v-else class="size-4" />
                                                {{ copied === 'systemd' ? 'Copied' : 'Copy Service' }}
                                            </button>
                                        </div>

                                        <pre
                                            class="max-h-[420px] overflow-auto rounded-button border border-border bg-surface-dark p-4 text-sm leading-6 text-foreground"
                                        ><code>{{ systemdService }}</code></pre>
                                    </section>
                                </template>
                            </template>
                        </div>

                        <aside class="space-y-5">
                            <section v-if="installExpanded" class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <div class="flex items-center gap-3">
                                    <Terminal class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">{{ t('ui.manual_commands') }}</h2>
                                </div>

                                <div class="mt-5 space-y-3">
                                    <div
                                        v-for="(command, index) in commands"
                                        :key="command"
                                        class="rounded-button border border-border bg-surface-dark p-3"
                                    >
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                                {{ t('ui.step') }} {{ index + 1 }}
                                            </span>

                                            <button
                                                class="inline-flex items-center gap-1 text-xs font-black text-muted-foreground transition hover:text-hive"
                                                @click="copy(command, `command-${index}`)"
                                            >
                                                <Check v-if="copied === `command-${index}`" class="size-3" />
                                                <Clipboard v-else class="size-3" />
                                                {{ copied === `command-${index}` ? 'Copied' : 'Copy' }}
                                            </button>
                                        </div>

                                        <code class="block break-all font-mono text-sm text-foreground">
                                            {{ command }}
                                        </code>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">{{ t('ui.node_details') }}</h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.runtime_connection_details_for_this_worker') }}
                                </p>

                                <div class="mt-5 divide-y divide-border">
                                    <div class="flex justify-between gap-4 py-3 text-sm">
                                        <span class="font-bold text-muted-foreground">{{ t('ui.name') }}</span>
                                        <span class="text-right font-black text-foreground">{{ node.name }}</span>
                                    </div>

                                    <div class="flex justify-between gap-4 py-3 text-sm">
                                        <span class="font-bold text-muted-foreground">{{ t('ui.public_url') }}</span>
                                        <span class="break-all text-right font-mono text-xs font-black text-foreground">
                                            {{ node.public_url }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between gap-4 py-3 text-sm">
                                        <span class="font-bold text-muted-foreground">{{ t('ui.base_url') }}</span>
                                        <span class="break-all text-right font-mono text-xs font-black text-foreground">
                                            {{ node.base_url }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between gap-4 py-3 text-sm">
                                        <span class="font-bold text-muted-foreground">{{ t('ui.registered') }}</span>
                                        <span
                                            class="rounded-full border px-2 py-0.5 text-xs font-black"
                                            :class="
                                                node.is_registered
                                                    ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                    : 'border-border bg-surface-light text-muted-foreground'
                                            "
                                        >
                                            {{ node.is_registered ? 'Yes' : 'No' }}
                                        </span>
                                    </div>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
