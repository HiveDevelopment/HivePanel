<script setup lang="ts">
import InstallJobProgress from '@/components/InstallJobProgress.vue';
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Boxes, Download, LoaderCircle, Package, Plus, Save, Search, ShieldCheck, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
type Selection = { provider: string; project: string; version: string; name?: string; number?: string };
const props = defineProps<{
    cell: any;
    mode: string;
    minecraftSettings: Record<string, any>;
    packs: any[];
    canInstall: boolean;
    canWipe: boolean;
    canChangeRuntime: boolean;
}>();
const base = `/cells/${props.cell.id}/minecraft`;
const titles: Record<string, string> = { mods: 'Mods', plugins: 'Plugins', jars: 'Server JAR', modpacks: 'Modpacks', builder: 'Modpack builder' };
const title = computed(() => titles[props.mode]);
const building = computed(() => props.mode === 'builder');
const changingRuntime = computed(() => ['builder', 'modpacks', 'jars'].includes(props.mode));
const kinds: Record<string, string> = { mods: 'mod', plugins: 'plugin', jars: 'jar', modpacks: 'modpack', builder: 'builder' };
const kind = computed(() => kinds[props.mode] ?? 'mod');
const catalogueKind = computed(() => (props.mode === 'modpacks' ? 'modpack' : props.mode === 'plugins' ? 'plugin' : 'mod'));
const metadata = { minecraft_content: props.minecraftSettings ?? {}, variables: props.cell.variables ?? {} };
const detected = String(props.cell.comb ?? '').toLowerCase();
const minecraft = ref(String(metadata.minecraft_content?.minecraft ?? props.cell.variables?.version ?? metadata.variables?.version ?? ''));
const loader = ref(
    props.mode === 'plugins'
        ? 'paper'
        : (metadata.minecraft_content?.loader ??
              (detected.includes('neoforge')
                  ? 'neoforge'
                  : detected.includes('forge')
                    ? 'forge'
                    : detected.includes('fabric')
                      ? 'fabric'
                      : props.mode === 'mods' || building.value
                        ? 'fabric'
                        : 'paper')),
);
const loaderVersion = ref('');
const versions = ref<Array<{ id: string; type: string }>>([]);
const loaderVersions = ref<string[]>([]);
const runtimes = computed(() =>
    props.mode === 'plugins'
        ? ['paper']
        : props.mode === 'mods' || building.value
          ? ['fabric', 'forge', 'neoforge']
          : ['vanilla', 'paper', 'fabric', 'forge', 'neoforge'],
);
const provider = ref('modrinth');
const providers = ref({ modrinth: true, curseforge: false });
const query = ref('');
const hits = ref<any[]>([]);
const offset = ref(0);
const total = ref(0);
const selections = ref<Selection[]>([]);
const name = ref('My modpack');
const packs = ref([...props.packs]);
const activeProject = ref<any>(null);
const releases = ref<any[]>([]);
const releaseTotal = ref(0);
const releaseOffset = ref(0);
const dialog = ref<HTMLDialogElement | null>(null);
const ticket = ref('');
const plan = ref<any>(null);
const job = ref<any>({ state: 'idle', logs: [] });
const busy = ref('');
const error = ref('');
const notice = ref('');
const previewElement = ref<HTMLElement | null>(null);
const errorElement = ref<HTMLElement | null>(null);
const jobElement = ref<HTMLElement | null>(null);
const installConfirmation = ref(false);
const wipesServer = computed(() => ['builder', 'modpack'].includes(plan.value?.kind));
const ready = ref(false);
const installing = computed(() => job.value.state === 'installing');
const showJob = ref(false);
const installView = computed(() => installing.value || showJob.value);
const allowed = computed(() => props.canInstall && (!changingRuntime.value || props.canChangeRuntime) && (!wipesServer.value || props.canWipe));
const requestSpec = computed(() => ({
    kind: kind.value,
    minecraft: minecraft.value,
    loader: loader.value,
    loaderVersion: loaderVersion.value || undefined,
    name: name.value,
    selections: selections.value.map(({ provider, project, version }) => ({ provider, project, version })),
}));
const size = computed(() => ((plan.value?.files ?? []).reduce((n: number, f: any) => n + (f.size || 0), 0) / 1024 / 1024).toFixed(1));
let stopped = false;
let pollTimer: ReturnType<typeof setTimeout> | undefined;
const pending = new Set<AbortController>();
async function request(path: string, data?: any, method = 'POST', binary = false): Promise<any> {
    const controller = new AbortController();
    pending.add(controller);
    const xsrf = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='))
        ?.slice(11);
    try {
        const response = await fetch(base + path, {
            method: data === undefined ? 'GET' : method,
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                ...(data === undefined ? {} : { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '' }),
            },
            body: data === undefined ? undefined : JSON.stringify(data),
        });
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(
                Object.values(body.errors ?? {})
                    .flat()
                    .join(' ') ||
                    body.message ||
                    `Request failed (${response.status}). Please try again.`,
            );
        }
        if (response.status === 204) return null;
        return binary ? response.blob() : response.json();
    } finally {
        pending.delete(controller);
    }
}
async function run(label: string, action: () => Promise<void>) {
    if (busy.value || installing.value) return;
    busy.value = label;
    error.value = '';
    notice.value = '';
    try {
        await action();
    } catch (e: any) {
        if (!stopped) {
            error.value = e.message;
            await nextTick();
            errorElement.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    } finally {
        busy.value = '';
    }
}
function invalidate() {
    plan.value = null;
    ticket.value = '';
    installConfirmation.value = false;
}
watch([selections, name, loaderVersion], invalidate, { deep: true });
watch([minecraft, loader], async () => {
    invalidate();
    if (!ready.value) return;
    selections.value = [];
    await run('Loading catalogue', async () => {
        await loadLoaders();
        await searchCatalogue();
    });
});
watch(provider, async () => {
    if (ready.value) await run('Loading catalogue', searchCatalogue);
});
async function loadLoaders() {
    loaderVersion.value = '';
    loaderVersions.value = [];
    if (!changingRuntime.value || props.mode === 'modpacks' || loader.value === 'vanilla') return;
    const data = await request('/catalogue?' + new URLSearchParams({ action: 'loaders', minecraft: minecraft.value, loader: loader.value }));
    loaderVersions.value = data.versions;
}
async function searchCatalogue(more = false) {
    if (props.mode === 'jars') return;
    const next = more ? offset.value + 20 : 0;
    const data = await request(
        '/catalogue?' +
            new URLSearchParams({
                action: 'search',
                provider: provider.value,
                kind: catalogueKind.value,
                query: query.value,
                minecraft: minecraft.value,
                loader: props.mode === 'modpacks' ? '' : loader.value,
                offset: String(next),
            }),
    );
    hits.value = more ? [...hits.value, ...data.hits] : data.hits;
    offset.value = next;
    total.value = data.total;
}
async function selectProject(project: any) {
    activeProject.value = project;
    releases.value = [];
    releaseOffset.value = 0;
    releaseTotal.value = 0;
    dialog.value?.showModal();
    await run('Loading versions', async () => {
        await loadReleases();
    });
}
async function loadReleases(more = false) {
    const next = more ? releaseOffset.value + 100 : 0;
    const data = await request(
        '/catalogue?' +
            new URLSearchParams({
                action: 'releases',
                provider: activeProject.value.provider,
                project: activeProject.value.project,
                minecraft: minecraft.value,
                loader: props.mode === 'modpacks' ? '' : loader.value,
                offset: String(next),
            }),
    );
    releases.value = more ? [...releases.value, ...data.versions] : data.versions;
    releaseOffset.value = next;
    releaseTotal.value = data.total;
}
function choose(release: any) {
    const selected = {
        provider: activeProject.value.provider,
        project: activeProject.value.project,
        version: release.id,
        name: activeProject.value.name,
        number: release.number,
    };
    if (building.value)
        selections.value = [...selections.value.filter((s) => s.provider !== selected.provider || s.project !== selected.project), selected];
    else selections.value = [selected];
    dialog.value?.close();
    invalidate();
    if (!building.value) void preview();
}
async function preview() {
    await run('Checking compatibility', async () => {
        const data = await request('/preview', requestSpec.value);
        plan.value = data.plan;
        ticket.value = data.ticket;
    });
    if (plan.value && !error.value) {
        await nextTick();
        previewElement.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (['mod', 'plugin', 'modpack'].includes(plan.value.kind) && allowed.value) installConfirmation.value = true;
    }
}
async function install() {
    await run('Starting installation', async () => {
        job.value = await request('/install', { ticket: ticket.value, confirm_wipe: wipesServer.value });
        showJob.value = true;
        installConfirmation.value = false;
        await nextTick();
        jobElement.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        await poll();
    });
    installConfirmation.value = false;
}
function requestInstall() {
    if (!allowed.value || busy.value || installing.value) return;
    installConfirmation.value = true;
}
async function poll() {
    if (stopped) return;
    try {
        const previous = job.value.state;
        job.value = await request('/status');
        if (job.value.state === 'installing') showJob.value = true;
        if (previous === 'installing' && job.value.state === 'complete') {
            notice.value = 'Installation complete. Start the Cell from its console.';
            invalidate();
        }
    } catch (e: any) {
        if (!stopped) error.value = `Could not read installation progress: ${e.message}`;
    }
    if (!stopped && job.value.state === 'installing') pollTimer = setTimeout(poll, 2000);
}
async function savePack() {
    await run('Saving pack', async () => {
        const pack = await request('/packs', requestSpec.value);
        packs.value.unshift(pack);
        notice.value = 'Modpack saved to your account.';
    });
}
async function removePack(pack: any) {
    await run('Deleting saved pack', async () => {
        await request('/packs/' + pack.id, {}, 'DELETE');
        packs.value = packs.value.filter((p) => p.id !== pack.id);
    });
}
async function loadPack(pack: any) {
    await run('Loading saved pack', async () => {
        ready.value = false;
        minecraft.value = pack.spec.minecraft;
        loader.value = pack.spec.loader;
        name.value = pack.name;
        await loadLoaders();
        loaderVersion.value = pack.spec.loaderVersion ?? '';
        selections.value = pack.spec.selections;
        await searchCatalogue();
        invalidate();
    });
    ready.value = true;
}
async function exportPack(format: string) {
    await run('Exporting pack', async () => {
        const blob = await request('/export/' + format, requestSpec.value, 'POST', true);
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'custom-pack.' + (format === 'modrinth' ? 'mrpack' : 'zip');
        anchor.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
}
async function initializeCatalogue() {
    if (ready.value || stopped) return;
    await run('Loading catalogue', async () => {
        const data = await request('/catalogue?action=versions');
        versions.value = data.versions.filter((v: any) => v.type === 'release');
        providers.value = data.providers;
        if (!versions.value.some((v) => v.id === minecraft.value)) minecraft.value = versions.value[0]?.id ?? '';
        if (!runtimes.value.includes(loader.value)) loader.value = runtimes.value[0];
        await loadLoaders();
        await searchCatalogue();
    });
    ready.value = !error.value;
}
async function backToCatalogue() {
    showJob.value = false;
    invalidate();
    await initializeCatalogue();
}
onMounted(async () => {
    await poll();
    if (!installing.value) await initializeCatalogue();
});
onBeforeUnmount(() => {
    stopped = true;
    clearTimeout(pollTimer);
    for (const controller of pending) controller.abort();
});
</script>

<template>
    <AppLayout context="server" :active-cell="cell">
        <Head :title="`${cell.name} - ${title}`" />
        <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <header class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-foreground/[0.08] bg-surface p-5 sm:p-6">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/5">
                        <Package class="size-5 text-hive" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-foreground">{{ installView ? 'Installation' : title }}</h1>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                installView
                                    ? 'Follow installation progress and review the Worker output.'
                                    : building
                                      ? 'Choose your version and loader, then build a pack around them.'
                                      : mode === 'jars'
                                        ? 'Install the server software and version you want to run.'
                                        : 'Discover compatible releases and install them through HiveRegistry.'
                            }}
                        </p>
                    </div>
                </div>
                <span class="flex items-center gap-2 rounded-full border border-hive/15 px-3 py-1.5 text-[11px] text-hive"
                    ><ShieldCheck class="size-3.5" /> {{ t('ui.hiveregistry') }}</span
                >
            </header>
            <p
                v-if="error"
                ref="errorElement"
                role="alert"
                class="rounded-panel border border-status-danger/20 bg-status-danger/5 p-4 text-xs leading-5 text-status-danger"
            >
                {{ error }}
            </p>
            <p v-if="notice" role="status" class="rounded-panel border border-status-success/20 bg-status-success/5 p-4 text-xs text-status-success">
                {{ notice }}
            </p>
            <div v-if="installView" ref="jobElement"><InstallJobProgress :job="job" /></div>
            <div v-if="installView && !installing" class="flex items-center gap-4">
                <button class="rounded-button bg-hive px-5 py-3 text-xs font-bold text-black" @click="backToCatalogue">
                    {{ t('ui.back_to_catalogue') }}</button
                ><Link :href="`/cells/${cell.id}`" class="text-xs text-muted-foreground">{{ t('ui.open_console') }}</Link>
            </div>
            <template v-if="!installView">
                <button v-if="job.state !== 'idle'" class="text-xs text-muted-foreground hover:text-hive" @click="showJob = true">
                    {{ t('ui.view_last_installation_logs') }}
                </button>
                <fieldset
                    :disabled="Boolean(busy) || installing"
                    class="grid gap-4 rounded-panel border border-foreground/[0.08] bg-surface p-5 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <label class="text-xs text-muted-foreground"
                        >{{ t('ui.minecraft_version')
                        }}<select
                            v-model="minecraft"
                            class="mt-2 h-10 w-full rounded-button border border-foreground/[0.08] bg-surface-dark px-3 text-xs text-foreground"
                        >
                            <option v-for="version in versions" :key="version.id" :value="version.id">{{ version.id }}</option>
                        </select></label
                    >
                    <label class="text-xs text-muted-foreground"
                        >{{ mode === 'jars' ? 'Server software' : 'Mod loader'
                        }}<select
                            v-model="loader"
                            :disabled="mode === 'modpacks'"
                            class="mt-2 h-10 w-full rounded-button border border-foreground/[0.08] bg-surface-dark px-3 text-xs capitalize text-foreground disabled:opacity-40"
                        >
                            <option v-for="runtime in runtimes" :key="runtime" :value="runtime">{{ runtime }}</option>
                        </select></label
                    >
                    <label v-if="changingRuntime && mode !== 'modpacks'" class="text-xs text-muted-foreground"
                        >{{ t('ui.runtime_version')
                        }}<select
                            v-model="loaderVersion"
                            class="mt-2 h-10 w-full rounded-button border border-foreground/[0.08] bg-surface-dark px-3 text-xs text-foreground"
                        >
                            <option value="">{{ t('ui.latest_compatible') }}</option>
                            <option v-for="version in loaderVersions" :key="version" :value="version">{{ version }}</option>
                        </select></label
                    >
                    <label v-if="mode !== 'jars'" class="text-xs text-muted-foreground"
                        >{{ t('ui.catalogue')
                        }}<select
                            v-model="provider"
                            class="mt-2 h-10 w-full rounded-button border border-foreground/[0.08] bg-surface-dark px-3 text-xs text-foreground"
                        >
                            <option value="modrinth">{{ t('ui.modrinth') }}</option>
                            <option value="curseforge" :disabled="!providers.curseforge">
                                {{ providers.curseforge ? 'CurseForge' : 'CurseForge · not configured' }}
                            </option>
                        </select></label
                    >
                </fieldset>
                <p v-if="mode === 'modpacks'" class="text-xs leading-5 text-muted-foreground">
                    {{ t('ui.the_pack_s_manifest_selects_its_exact_minecraft_version_loader_re') }}
                </p>
                <section v-if="plan" ref="previewElement" class="overflow-hidden rounded-panel border border-hive/20 bg-surface">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-foreground/[0.06] px-5 py-4">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <ShieldCheck class="size-4 text-hive" /> {{ t('ui.installation_preview') }}
                        </h2>
                        <span class="text-[11px] text-muted-foreground"
                            >{{ plan.files.length }} {{ t('ui.files') }} {{ size }} {{ t('ui.mb_cbab118') }} {{ plan.minecraft }} /
                            {{ plan.loader }}</span
                        >
                    </header>
                    <div class="space-y-4 p-5">
                        <p class="text-xs leading-5 text-muted-foreground">
                            {{ t('ui.version_and_loader_checks_passed_required_dependencies_are_includ') }}
                        </p>
                        <ul
                            v-if="plan.warnings.length"
                            class="space-y-1 rounded-button border border-status-warning/20 bg-status-warning/5 p-3 text-xs leading-5 text-status-warning"
                        >
                            <li v-for="warning in plan.warnings" :key="warning">{{ warning }}</li>
                        </ul>
                        <details>
                            <summary class="cursor-pointer text-xs text-muted-foreground">{{ t('ui.view_files_and_dependencies') }}</summary>
                            <div class="mt-3 max-h-56 overflow-auto rounded-button bg-surface-dark p-3">
                                <p
                                    v-for="file in plan.files"
                                    :key="file.path"
                                    class="break-all font-mono text-[11px] leading-6 text-muted-foreground"
                                >
                                    {{ file.path }}
                                </p>
                            </div>
                        </details>
                        <p class="text-xs leading-5 text-muted-foreground">
                            {{
                                wipesServer
                                    ? 'Wipe the server’s existing worlds, plugins, mods and configuration, then install this modpack.'
                                    : 'Install these files and replace files with the same names.'
                            }}
                            {{ t('ui.replaced_files_are_retained_in_a_backup_on_the_worker_stop_the_ce') }}
                        </p>
                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                :disabled="!allowed || Boolean(busy) || installing"
                                class="flex items-center gap-2 rounded-button bg-hive px-5 py-3 text-xs font-bold text-black disabled:opacity-40"
                                @click="requestInstall"
                            >
                                <Download class="size-4" /> {{ t('ui.install') }}
                                {{ plan.kind === 'modpack' || plan.kind === 'builder' ? 'modpack' : 'selection' }}</button
                            ><Link :href="`/cells/${cell.id}`" class="text-xs text-muted-foreground hover:text-foreground">{{
                                t('ui.open_console')
                            }}</Link>
                            <p v-if="!allowed" class="text-xs text-status-warning">
                                {{ t('ui.installing_requires_file_upload_write_permissions')
                                }}{{ changingRuntime ? ' and permission to update startup.' : '.'
                                }}{{ wipesServer ? ' Wiping also requires file deletion permission.' : '' }}
                            </p>
                        </div>
                    </div>
                </section>
                <div class="grid items-start gap-5" :class="building ? 'xl:grid-cols-[minmax(0,1fr)_360px]' : ''">
                    <section v-if="mode !== 'jars'" class="min-w-0 space-y-4">
                        <form class="flex gap-3" @submit.prevent="run('Searching', searchCatalogue)">
                            <div class="flex min-w-0 flex-1 items-center gap-2 rounded-button border border-foreground/[0.08] bg-surface px-3">
                                <Search class="size-4 text-muted-foreground" /><input
                                    v-model="query"
                                    :aria-label="t('ui.search_catalogue')"
                                    :placeholder="t('ui.search_projects')"
                                    class="h-11 w-full bg-transparent text-sm text-foreground outline-none"
                                />
                            </div>
                            <button
                                :disabled="Boolean(busy) || installing"
                                class="rounded-button border border-foreground/[0.08] bg-surface px-4 text-xs text-foreground disabled:opacity-40"
                            >
                                {{ t('ui.search') }}
                            </button>
                        </form>
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-semibold text-foreground">
                                {{ query ? 'Search results' : building ? 'Suggested mods' : 'Popular projects' }}
                            </h2>
                            <span class="text-[11px] text-muted-foreground">{{ total.toLocaleString() }} {{ t('ui.projects') }}</span>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2" :class="building ? '' : '2xl:grid-cols-3'">
                            <article
                                v-for="project in hits"
                                :key="`${project.provider}:${project.project}`"
                                class="flex flex-col rounded-panel border border-foreground/[0.08] bg-surface p-5"
                            >
                                <div class="flex items-start gap-3">
                                    <img
                                        v-if="project.icon?.startsWith('https://')"
                                        :src="project.icon"
                                        alt=""
                                        loading="lazy"
                                        referrerpolicy="no-referrer"
                                        class="size-10 shrink-0 rounded-lg object-cover"
                                    /><Boxes v-else class="size-10 shrink-0 rounded-lg bg-foreground/5 p-2 text-muted-foreground" />
                                    <div class="min-w-0">
                                        <h3 class="break-words text-sm font-semibold text-foreground">{{ project.name }}</h3>
                                        <p class="mt-1 text-[10px] capitalize text-muted-foreground">
                                            {{ project.provider }} · {{ Number(project.downloads || 0).toLocaleString() }} {{ t('ui.downloads') }}
                                        </p>
                                    </div>
                                </div>
                                <p class="mt-3 line-clamp-3 text-xs leading-5 text-muted-foreground">{{ project.summary }}</p>
                                <button
                                    :disabled="Boolean(busy) || installing"
                                    class="mt-auto flex items-center justify-between gap-2 pt-4 text-xs font-semibold text-hive disabled:opacity-40"
                                    @click="selectProject(project)"
                                >
                                    <span>{{ building ? 'Choose a version' : 'View versions & install' }}</span
                                    ><Plus class="size-3.5" />
                                </button>
                            </article>
                        </div>
                        <p
                            v-if="!hits.length && !busy"
                            class="rounded-panel border border-foreground/[0.08] bg-surface p-10 text-center text-xs text-muted-foreground"
                        >
                            {{ t('ui.no_projects_match_this_version_and_loader_try_another_search_or_c') }}
                        </p>
                        <button
                            v-if="hits.length < total"
                            :disabled="Boolean(busy) || installing"
                            class="w-full rounded-button border border-foreground/[0.08] p-3 text-xs text-muted-foreground disabled:opacity-40"
                            @click="run('Loading more', () => searchCatalogue(true))"
                        >
                            {{ t('ui.load_more_projects') }}
                        </button>
                    </section>
                    <aside v-if="building" class="space-y-4 rounded-panel border border-foreground/[0.08] bg-surface p-5">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <Package class="size-4 text-hive" /> {{ t('ui.your_modpack') }}
                        </h2>
                        <label class="block text-[11px] text-muted-foreground"
                            >{{ t('ui.pack_name')
                            }}<input
                                v-model="name"
                                maxlength="100"
                                :disabled="installing"
                                class="mt-2 h-10 w-full rounded-button border border-foreground/[0.08] bg-surface-dark px-3 text-sm text-foreground"
                        /></label>
                        <div
                            v-for="(selection, index) in selections"
                            :key="`${selection.provider}:${selection.project}`"
                            class="flex items-center justify-between gap-2 border-b border-foreground/[0.06] py-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-xs text-foreground">{{ selection.name || selection.project }}</p>
                                <p class="mt-1 truncate text-[10px] text-muted-foreground">
                                    {{ selection.provider }} · {{ selection.number || selection.version }}
                                </p>
                            </div>
                            <button
                                :disabled="installing"
                                :aria-label="`Remove ${selection.name || selection.project}`"
                                class="text-muted-foreground hover:text-status-danger"
                                @click="selections.splice(index, 1)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <p v-if="!selections.length" class="py-4 text-xs leading-5 text-muted-foreground">
                            {{ t('ui.add_mods_from_the_catalogue_required_dependencies_are_included_wh') }}
                        </p>
                        <button
                            :disabled="!selections.length || Boolean(busy) || installing"
                            class="flex w-full items-center justify-center gap-2 rounded-button bg-hive px-4 py-3 text-xs font-bold text-black disabled:opacity-40"
                            @click="preview"
                        >
                            <ShieldCheck class="size-4" /> {{ t('ui.check_review_pack') }}
                        </button>
                        <button
                            :disabled="!selections.length || !name || Boolean(busy) || installing"
                            class="flex w-full items-center justify-center gap-2 rounded-button border border-foreground/[0.08] px-4 py-2.5 text-xs text-foreground disabled:opacity-40"
                            @click="savePack"
                        >
                            <Save class="size-3.5" /> {{ t('ui.save_pack') }}
                        </button>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="format in ['modrinth', 'curseforge']"
                                :key="format"
                                :disabled="!selections.length || Boolean(busy) || installing"
                                class="flex items-center justify-center gap-1 rounded-button border border-foreground/[0.08] px-2 py-2 text-[11px] capitalize text-muted-foreground disabled:opacity-40"
                                @click="exportPack(format)"
                            >
                                <Download class="size-3" />{{ format }}
                            </button>
                        </div>
                        <p class="text-[10px] leading-4 text-muted-foreground">
                            {{ t('ui.modrinth_exports_support_mixed_sources_curseforge_exports_require') }}
                        </p>
                        <div v-if="packs.length" class="space-y-2 border-t border-foreground/[0.06] pt-4">
                            <h3 class="text-xs font-semibold text-muted-foreground">{{ t('ui.saved_packs') }}</h3>
                            <div v-for="pack in packs" :key="pack.id" class="flex items-center justify-between gap-2">
                                <button
                                    :disabled="Boolean(busy) || installing"
                                    class="truncate text-xs text-foreground hover:text-hive"
                                    @click="loadPack(pack)"
                                >
                                    {{ pack.name }}</button
                                ><button
                                    :disabled="Boolean(busy) || installing"
                                    :aria-label="`Delete ${pack.name}`"
                                    class="text-muted-foreground"
                                    @click="removePack(pack)"
                                >
                                    <Trash2 class="size-3" />
                                </button>
                            </div>
                        </div>
                    </aside>
                    <section v-if="mode === 'jars'" class="rounded-panel border border-foreground/[0.08] bg-surface p-6">
                        <h2 class="text-sm font-semibold capitalize text-foreground">{{ loader }} {{ t('ui.minecraft_406eff6') }} {{ minecraft }}</h2>
                        <p class="mt-2 max-w-2xl text-xs leading-6 text-muted-foreground">
                            {{ t('ui.hiveregistry_resolves_the_selected_build_forge_and_neoforge_insta') }}
                        </p>
                        <button
                            :disabled="!minecraft || Boolean(busy) || installing"
                            class="mt-5 rounded-button bg-hive px-5 py-3 text-xs font-bold text-black disabled:opacity-40"
                            @click="preview"
                        >
                            {{ t('ui.review_installation') }}
                        </button>
                    </section>
                </div>
            </template>
            <div v-if="busy" role="status" class="flex items-center gap-2 text-xs text-hive">
                <LoaderCircle class="size-4 animate-spin" /> {{ busy }}…
            </div>
        </main>
        <ConfirmationModal
            :open="installConfirmation"
            :title="wipesServer ? 'Wipe server and install modpack?' : 'Install selected files?'"
            :description="
                (plan ? `${plan.files.length} files for Minecraft ${plan.minecraft} / ${plan.loader}. ` : '') +
                (wipesServer
                    ? 'This replaces all current server files, including worlds, plugins, mods and configuration. The old files are retained in a rollback backup. Downloads and loader preparation must succeed before any live files are removed. Stop the Cell before continuing.'
                    : 'Install the selected files and required dependencies. Files with matching names will be replaced and retained in a rollback backup. Stop the Cell before continuing.') +
                (plan?.warnings?.length
                    ? ' Warnings: ' +
                      plan.warnings.slice(0, 2).join(' ') +
                      (plan.warnings.length > 2 ? ` Review ${plan.warnings.length - 2} more warnings in the preview.` : '')
                    : '')
            "
            :confirm-text="wipesServer ? 'Wipe and install' : 'Install'"
            :danger="wipesServer"
            :loading="Boolean(busy) || installing"
            @cancel="installConfirmation = false"
            @confirm="install"
        />
        <dialog
            ref="dialog"
            aria-labelledby="release-title"
            class="m-auto max-h-[80vh] w-[min(640px,calc(100%_-_2rem))] overflow-auto rounded-panel border border-foreground/[0.12] bg-surface p-0 text-foreground backdrop:bg-black/70"
        >
            <header class="sticky top-0 flex items-center justify-between border-b border-foreground/[0.06] bg-surface px-5 py-4">
                <div>
                    <h2 id="release-title" class="text-sm font-semibold">{{ activeProject?.name }}</h2>
                    <p class="mt-1 text-[11px] text-muted-foreground">{{ t('ui.compatible_releases_for_minecraft') }} {{ minecraft }}</p>
                </div>
                <button :aria-label="t('ui.close_versions')" @click="dialog?.close()"><X class="size-4 text-muted-foreground" /></button>
            </header>
            <div class="space-y-2 p-5">
                <p v-if="busy" role="status" class="text-xs text-muted-foreground">{{ t('ui.loading_releases') }}</p>
                <p v-if="error" role="alert" class="text-xs text-status-danger">{{ error }}</p>
                <button
                    v-for="release in releases"
                    :key="release.id"
                    class="flex w-full items-center justify-between gap-3 rounded-button border border-foreground/[0.08] bg-surface-dark p-3 text-left hover:border-hive/30"
                    :disabled="Boolean(busy) || installing"
                    @click="choose(release)"
                >
                    <div class="min-w-0">
                        <p class="truncate text-xs font-semibold">{{ release.name || release.number }}</p>
                        <p class="mt-1 truncate text-[10px] text-muted-foreground">{{ release.number }} · {{ release.published?.slice(0, 10) }}</p>
                    </div>
                    <Plus class="size-3.5 shrink-0 text-hive" />
                </button>
                <p v-if="!releases.length && !busy" class="text-xs text-muted-foreground">{{ t('ui.no_compatible_releases_were_found') }}</p>
                <button
                    v-if="releases.length < releaseTotal"
                    class="w-full p-3 text-xs text-hive"
                    :disabled="Boolean(busy)"
                    @click="run('Loading versions', () => loadReleases(true))"
                >
                    {{ t('ui.load_more_versions') }}
                </button>
            </div>
        </dialog>
    </AppLayout>
</template>
