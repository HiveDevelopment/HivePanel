<script setup lang="ts">
import CombConsoleSettings from '@/components/combs/CombConsoleSettings.vue';
import { useCombManifest } from '@/composables/useCombManifest';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { VueMonacoEditor } from '@guolao/vue-monaco-editor';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Box, Save } from 'lucide-vue-next';
import { computed } from 'vue';

type CombCategory = 'game' | 'web' | 'database' | 'application' | 'bot' | 'voice' | 'runtime';

const props = defineProps<{
    comb: {
        id: number | string;
        external_id: string;
        name: string;
        game?: string;
        category?: CombCategory;
        group?: string;
        source?: string;
        data: Record<string, any>;
    };
}>();

const categories: Array<{
    value: CombCategory;
    label: string;
}> = [
    { value: 'game', label: 'Game' },
    { value: 'web', label: 'Web' },
    { value: 'database', label: 'Database' },
    { value: 'application', label: 'Application' },
    { value: 'bot', label: 'Bot' },
    { value: 'voice', label: 'Voice' },
    { value: 'runtime', label: 'Runtime' },
];

const commonGroups = ['minecraft', 'steam', 'php', 'nodejs', 'python', 'mariadb', 'postgresql', 'redis'];

const form = useForm({
    console_commands_text: '',
    stop_command: 'stop',
    stop_timeout: 30,
    stop_signal: 'SIGTERM',
    external_id: props.comb.external_id,
    name: props.comb.name,
    category: (props.comb.category ?? props.comb.data?.category ?? 'application') as CombCategory,
    group: props.comb.group ?? props.comb.data?.group ?? props.comb.game ?? 'other',
    game: props.comb.game ?? props.comb.data?.game ?? '',
    manifest: JSON.stringify(props.comb.data ?? {}, null, 2),
});

const editorOptions = {
    automaticLayout: true,
    minimap: { enabled: false },
    fontSize: 13,
    fontFamily: 'JetBrains Mono, Consolas, monospace',
    scrollBeyondLastLine: false,
    tabSize: 2,
    wordWrap: 'on',
};

const sourceLabel = computed(() => {
    switch (props.comb.source) {
        case 'registry':
            return 'Registry';

        case 'registry_modified':
            return 'Modified';

        case 'manual':
            return 'Manual';

        default:
            return 'Local';
    }
});

const { syncManifestFromFields } = useCombManifest(form);

function submit() {
    syncManifestFromFields();

    form.put(`/admin/combs/${props.comb.id}`);
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Edit ${comb.name}`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Box class="size-6 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            {{ t('ui.edit_comb') }}
                                        </h1>

                                        <span
                                            class="rounded-full border border-border bg-surface-light px-2 py-0.5 text-xs font-bold text-muted-foreground"
                                        >
                                            {{ sourceLabel }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('ui.modify_this_local_comb_manifest_and_classification') }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="`/admin/combs/${comb.id}`"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                            >
                                <ArrowLeft class="size-4" />
                                {{ t('ui.back') }}
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <div class="mb-5">
                                <h2 class="text-lg font-black">
                                    {{ t('ui.general') }}
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.configure_how_this_comb_is_identified_and_organised_throughout_hi') }}
                                </p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                <div class="xl:col-span-2">
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.comb_id') }}
                                    </label>

                                    <input
                                        v-model="form.external_id"
                                        type="text"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <p v-if="form.errors.external_id" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.external_id }}
                                    </p>
                                </div>

                                <div class="xl:col-span-3">
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.name') }}
                                    </label>

                                    <input
                                        v-model="form.name"
                                        type="text"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    />

                                    <p v-if="form.errors.name" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.category') }}
                                    </label>

                                    <select
                                        v-model="form.category"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition focus:border-hive/50"
                                    >
                                        <option v-for="category in categories" :key="category.value" :value="category.value">
                                            {{ category.label }}
                                        </option>
                                    </select>

                                    <p v-if="form.errors.category" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.category }}
                                    </p>
                                </div>

                                <div class="xl:col-span-2">
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.group') }}
                                    </label>

                                    <input
                                        v-model="form.group"
                                        type="text"
                                        list="comb-groups"
                                        :placeholder="t('ui.e_g_steam')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                    />

                                    <datalist id="comb-groups">
                                        <option v-for="group in commonGroups" :key="group" :value="group" />
                                    </datalist>

                                    <p v-if="form.errors.group" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.group }}
                                    </p>

                                    <p class="mt-2 text-xs text-muted-foreground">
                                        {{ t('ui.used_to_group_related_combs_such_as_steam_or_minecraft') }}
                                    </p>
                                </div>

                                <div class="xl:col-span-2">
                                    <label class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.game') }}
                                    </label>

                                    <input
                                        v-model="form.game"
                                        type="text"
                                        :placeholder="t('ui.e_g_rust_1c24ea1')"
                                        class="mt-2 w-full rounded-button border border-border bg-surface-dark px-4 py-3 text-sm font-bold text-foreground outline-none transition placeholder:text-muted-foreground focus:border-hive/50"
                                    />

                                    <p v-if="form.errors.game" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.game }}
                                    </p>

                                    <p class="mt-2 text-xs text-muted-foreground">
                                        {{ t('ui.optional_compatibility_identifier_primarily_used_by_game_combs') }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <div class="mb-4">
                                <h2 class="text-lg font-black">
                                    {{ t('ui.comb_json') }}
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.id_name_category_group_and_game_are_synchronised_with_the_fields__07b3473') }}
                                </p>
                            </div>

                            <div class="overflow-hidden rounded-button border border-border bg-surface-dark">
                                <VueMonacoEditor
                                    v-model:value="form.manifest"
                                    language="json"
                                    theme="vs-dark"
                                    height="560px"
                                    :options="editorOptions"
                                />
                            </div>

                            <p v-if="form.errors.manifest" class="mt-2 text-xs font-bold text-status-danger">
                                {{ form.errors.manifest }}
                            </p>
                        </section>

                        <CombConsoleSettings :form="form" />

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save class="size-4" />

                                {{ form.processing ? 'Saving...' : 'Save Comb' }}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
