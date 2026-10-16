<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue';
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Box, PencilIcon, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    comb: {
        id: number | string;
        external_id: string;
        name: string;
        game?: string;
        category?: string;
        group?: string;
        tags?: string[];
        capabilities?: string[];
        source?: string;
        data: any;
        created_at?: string;
        updated_at?: string;
    };
}>();

const deleteOpen = ref(false);
const deleting = ref(false);

const manifest = computed(() => JSON.stringify(props.comb.data ?? {}, null, 2));

const category = computed(() => props.comb.category ?? props.comb.data?.category ?? 'application');

const group = computed(() => props.comb.group ?? props.comb.data?.group ?? props.comb.game ?? 'other');

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

const sourceClass = computed(() => {
    switch (props.comb.source) {
        case 'registry':
            return 'border-status-success/30 bg-status-success/10 text-status-success';

        case 'registry_modified':
            return 'border-hive/30 bg-hive/10 text-hive';

        default:
            return 'border-border bg-surface-light text-muted-foreground';
    }
});

function formatLabel(value?: string) {
    if (!value) {
        return 'Unknown';
    }

    return value.replace(/[-_]/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
}

function formatDate(value?: string) {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
}

function destroyComb() {
    deleting.value = true;

    router.delete(`/admin/combs/${props.comb.id}`, {
        preserveScroll: true,

        onFinish: () => {
            deleting.value = false;
            deleteOpen.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="comb.name" />

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
                                            {{ comb.name }}
                                        </h1>

                                        <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                            {{ formatLabel(category) }}
                                        </span>

                                        <span
                                            class="rounded-full border border-border bg-surface-light px-2 py-0.5 text-xs font-bold text-foreground"
                                        >
                                            {{ formatLabel(group) }}
                                        </span>

                                        <span class="rounded-full border px-2 py-0.5 text-xs font-bold" :class="sourceClass">
                                            {{ sourceLabel }}
                                        </span>
                                    </div>

                                    <p class="mt-2 font-mono text-sm text-muted-foreground">
                                        {{ comb.external_id }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    href="/admin/combs"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                                >
                                    <ArrowLeft class="size-4" />
                                    {{ t('ui.back') }}
                                </Link>

                                <Link
                                    :href="`/admin/combs/${comb.id}/edit`"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-border bg-surface-dark px-4 py-2 text-sm font-black text-foreground transition hover:border-hive/40 hover:text-foreground"
                                >
                                    <PencilIcon class="size-4" />
                                    {{ t('ui.edit') }}
                                </Link>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-status-danger/40 bg-status-danger/10 px-4 py-2 text-sm font-black text-status-danger transition hover:bg-status-danger/20"
                                    @click="deleteOpen = true"
                                >
                                    <Trash2 class="size-4" />
                                    {{ t('ui.delete') }}
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-5 lg:grid-cols-3">
                        <div class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black">
                                {{ t('ui.details') }}
                            </h2>

                            <div class="mt-4 space-y-4 text-sm">
                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.local_id') }}
                                    </div>

                                    <div class="mt-1 font-mono text-foreground">
                                        {{ comb.id }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.external_id') }}
                                    </div>

                                    <div class="mt-1 font-mono text-foreground">
                                        {{ comb.external_id }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.category') }}
                                    </div>

                                    <div class="mt-1 text-foreground">
                                        {{ formatLabel(category) }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.group') }}
                                    </div>

                                    <div class="mt-1 text-foreground">
                                        {{ formatLabel(group) }}
                                    </div>
                                </div>

                                <div v-if="comb.game">
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.game') }}
                                    </div>

                                    <div class="mt-1 text-foreground">
                                        {{ formatLabel(comb.game) }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.source') }}
                                    </div>

                                    <div
                                        class="mt-1"
                                        :class="
                                            sourceLabel === 'Registry'
                                                ? 'text-status-success'
                                                : sourceLabel === 'Modified'
                                                  ? 'text-hive'
                                                  : 'text-foreground'
                                        "
                                    >
                                        {{ sourceLabel }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ t('ui.updated') }}
                                    </div>

                                    <div class="mt-1 text-foreground">
                                        {{ formatDate(comb.updated_at) }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="comb.tags?.length" class="mt-6 border-t border-border pt-5">
                                <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                    {{ t('ui.tags') }}
                                </div>

                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tag in comb.tags"
                                        :key="tag"
                                        class="rounded-full border border-border bg-surface/70 px-2 py-0.5 text-[11px] font-bold text-muted-foreground"
                                    >
                                        {{ tag }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="comb.capabilities?.length" class="mt-6 border-t border-border pt-5">
                                <div class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                    {{ t('ui.capabilities') }}
                                </div>

                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="capability in comb.capabilities"
                                        :key="capability"
                                        class="rounded-full border border-hive/20 bg-hive/5 px-2 py-0.5 text-[11px] font-bold text-hive/80"
                                    >
                                        {{ formatLabel(capability) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-panel border border-border bg-surface p-5 sm:p-6 lg:col-span-2">
                            <div>
                                <h2 class="text-lg font-black">
                                    {{ t('ui.manifest_preview') }}
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ t('ui.complete_local_comb_definition_used_by_hivepanel') }}
                                </p>
                            </div>

                            <pre
                                class="mt-4 max-h-[650px] overflow-auto rounded-button border border-border bg-surface-dark p-4 text-xs leading-6 text-foreground"
                            ><code>{{ manifest }}</code></pre>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="deleteOpen"
            :title="t('ui.delete_comb')"
            :description="`Delete ${comb.name} from this HivePanel installation? This does not remove it from HiveRegistry.`"
            :confirm-text="t('ui.delete_comb_9c27c74')"
            :processing="deleting"
            destructive
            @close="deleteOpen = false"
            @confirm="destroyComb"
        />
    </AppLayout>
</template>
