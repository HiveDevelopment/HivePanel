<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Box, CloudDownload, Plus, Search } from 'lucide-vue-next'
import { computed, ref } from 'vue'

type CombCategory =
    | 'game'
    | 'web'
    | 'database'
    | 'application'
    | 'bot'
    | 'voice'
    | 'runtime'

type CombCapability =
    | 'console'
    | 'files'
    | 'logs'
    | 'environment'
    | 'allocations'
    | 'sftp'
    | 'backups'
    | 'schedules'
    | 'domains'
    | 'ssl'
    | 'credentials'
    | 'database'

type CombRecord = {
    id: string | number
    external_id?: string
    name: string
    game?: string
    category?: CombCategory
    group?: string
    tags?: string[]
    capabilities?: CombCapability[]
    source?:
        | 'local'
        | 'registry'
        | 'registry_modified'
        | 'manual'
    created_at?: string
    updated_at?: string
}

type RegistryCombRecord = {
    id: string
    name: string
    category?: CombCategory
    group?: string
    game?: string
    tags?: string[]
    capabilities?: CombCapability[]
}

type CombGroup<T> = {
    name: string
    combs: T[]
}

type CombCategoryGroup<T> = {
    category: string
    groups: CombGroup<T>[]
    count: number
}

type RegistryCategory =
    CombCategoryGroup<RegistryCombRecord>

type LocalCategory =
    CombCategoryGroup<CombRecord>

const props = defineProps<{
    combs: CombRecord[]
    registryCombs: RegistryCombRecord[]
}>()

const search = ref('')
const selectedCategory = ref('all')

const categoryOrder = [
    'game',
    'web',
    'database',
    'application',
    'bot',
    'voice',
    'runtime',
]

const filteredLocalCombs = computed(() => {
    const q = search.value
        .toLowerCase()
        .trim()

    if (!q) {
        return props.combs
    }

    return props.combs.filter(comb => {
        const category =
            normaliseCategory(comb.category)

        const group =
            normaliseGroup(comb)

        return (
            comb.name
                .toLowerCase()
                .includes(q) ||
            (comb.game ?? '')
                .toLowerCase()
                .includes(q) ||
            String(
                comb.external_id ?? comb.id,
            )
                .toLowerCase()
                .includes(q) ||
            category
                .toLowerCase()
                .includes(q) ||
            group
                .toLowerCase()
                .includes(q) ||
            (comb.tags ?? []).some(tag =>
                tag
                    .toLowerCase()
                    .includes(q),
            ) ||
            (comb.capabilities ?? []).some(
                capability =>
                    capability
                        .toLowerCase()
                        .includes(q),
            )
        )
    })
})

const groupedLocalCombs =
    computed<LocalCategory[]>(() => {
        const categories =
            new Map<
                string,
                Map<string, CombRecord[]>
            >()

        for (
            const comb
            of filteredLocalCombs.value
        ) {
            const category =
                normaliseCategory(
                    comb.category,
                )

            const group =
                normaliseGroup(comb)

            if (
                !categories.has(category)
            ) {
                categories.set(
                    category,
                    new Map(),
                )
            }

            const groups =
                categories.get(category)!

            if (!groups.has(group)) {
                groups.set(group, [])
            }

            groups
                .get(group)!
                .push(comb)
        }

        return Array.from(
            categories.entries(),
        )
            .sort(([a], [b]) =>
                compareCategories(a, b),
            )
            .map(
                ([
                    category,
                    groups,
                ]) => {
                    const mappedGroups =
                        Array.from(
                            groups.entries(),
                        )
                            .sort(
                                ([a], [b]) =>
                                    a.localeCompare(
                                        b,
                                    ),
                            )
                            .map(
                                ([
                                    name,
                                    combs,
                                ]) => ({
                                    name,
                                    combs: [
                                        ...combs,
                                    ].sort(
                                        (
                                            a,
                                            b,
                                        ) =>
                                            a.name.localeCompare(
                                                b.name,
                                            ),
                                    ),
                                }),
                            )

                    return {
                        category,
                        groups:
                            mappedGroups,
                        count:
                            mappedGroups.reduce(
                                (
                                    total,
                                    group,
                                ) =>
                                    total +
                                    group
                                        .combs
                                        .length,
                                0,
                            ),
                    }
                },
            )
    })

const registryCategories =
    computed(() => {
        const categories =
            new Map<string, number>()

        for (
            const comb
            of props.registryCombs
        ) {
            const category =
                normaliseCategory(
                    comb.category,
                )

            categories.set(
                category,
                (
                    categories.get(
                        category,
                    ) ?? 0
                ) + 1,
            )
        }

        return Array.from(
            categories.entries(),
        )
            .sort(([a], [b]) =>
                compareCategories(a, b),
            )
            .map(
                ([
                    category,
                    count,
                ]) => ({
                    category,
                    count,
                }),
            )
    })

const filteredRegistryCombs =
    computed(() => {
        const q = search.value
            .toLowerCase()
            .trim()

        return props.registryCombs.filter(
            comb => {
                const category =
                    normaliseCategory(
                        comb.category,
                    )

                const group =
                    normaliseGroup(comb)

                if (
                    selectedCategory.value !==
                        'all' &&
                    category !==
                        selectedCategory.value
                ) {
                    return false
                }

                if (!q) {
                    return true
                }

                return (
                    comb.name
                        .toLowerCase()
                        .includes(q) ||
                    comb.id
                        .toLowerCase()
                        .includes(q) ||
                    category
                        .toLowerCase()
                        .includes(q) ||
                    group
                        .toLowerCase()
                        .includes(q) ||
                    (comb.game ?? '')
                        .toLowerCase()
                        .includes(q) ||
                    (comb.tags ?? []).some(
                        tag =>
                            tag
                                .toLowerCase()
                                .includes(q),
                    ) ||
                    (
                        comb.capabilities ??
                        []
                    ).some(capability =>
                        capability
                            .toLowerCase()
                            .includes(q),
                    )
                )
            },
        )
    })

const groupedRegistryCombs =
    computed<RegistryCategory[]>(
        () => {
            const categories =
                new Map<
                    string,
                    Map<
                        string,
                        RegistryCombRecord[]
                    >
                >()

            for (
                const comb
                of filteredRegistryCombs.value
            ) {
                const category =
                    normaliseCategory(
                        comb.category,
                    )

                const group =
                    normaliseGroup(comb)

                if (
                    !categories.has(
                        category,
                    )
                ) {
                    categories.set(
                        category,
                        new Map(),
                    )
                }

                const groups =
                    categories.get(
                        category,
                    )!

                if (
                    !groups.has(group)
                ) {
                    groups.set(
                        group,
                        [],
                    )
                }

                groups
                    .get(group)!
                    .push(comb)
            }

            return Array.from(
                categories.entries(),
            )
                .sort(([a], [b]) =>
                    compareCategories(
                        a,
                        b,
                    ),
                )
                .map(
                    ([
                        category,
                        groups,
                    ]) => {
                        const mappedGroups =
                            Array.from(
                                groups.entries(),
                            )
                                .sort(
                                    (
                                        [a],
                                        [b],
                                    ) =>
                                        a.localeCompare(
                                            b,
                                        ),
                                )
                                .map(
                                    ([
                                        name,
                                        combs,
                                    ]) => ({
                                        name,
                                        combs: [
                                            ...combs,
                                        ].sort(
                                            (
                                                a,
                                                b,
                                            ) =>
                                                a.name.localeCompare(
                                                    b.name,
                                                ),
                                        ),
                                    }),
                                )

                        return {
                            category,
                            groups:
                                mappedGroups,
                            count:
                                mappedGroups.reduce(
                                    (
                                        total,
                                        group,
                                    ) =>
                                        total +
                                        group
                                            .combs
                                            .length,
                                    0,
                                ),
                        }
                    },
                )
        },
    )

function normaliseCategory(
    category?: string,
) {
    const value = category
        ?.trim()
        .toLowerCase()

    return value || 'application'
}

function normaliseGroup(
    comb:
        | RegistryCombRecord
        | CombRecord,
) {
    const group = comb.group
        ?.trim()
        .toLowerCase()

    if (group) {
        return group
    }

    const game = comb.game
        ?.trim()
        .toLowerCase()

    if (game) {
        return game
    }

    return (
        comb.category
            ?.trim()
            .toLowerCase() ||
        'application'
    )
}

function compareCategories(
    a: string,
    b: string,
) {
    const aIndex =
        categoryOrder.indexOf(a)

    const bIndex =
        categoryOrder.indexOf(b)

    if (
        aIndex === -1 &&
        bIndex === -1
    ) {
        return a.localeCompare(b)
    }

    if (aIndex === -1) {
        return 1
    }

    if (bIndex === -1) {
        return -1
    }

    return aIndex - bIndex
}

function formatDate(value?: string) {
    if (!value) {
        return 'Never'
    }

    return new Date(
        value,
    ).toLocaleString()
}

function formatLabel(value?: string) {
    if (!value) {
        return 'Unknown'
    }

    return value
        .replace(/[-_]/g, ' ')
        .replace(
            /\b\w/g,
            character =>
                character.toUpperCase(),
        )
}

function formatCategoryName(
    category: string,
) {
    const names: Record<
        string,
        string
    > = {
        game: 'Games',
        web: 'Web',
        database: 'Databases',
        application: 'Applications',
        bot: 'Bots',
        voice: 'Voice',
        runtime: 'Runtimes',
    }

    return (
        names[category] ??
        formatLabel(category)
    )
}

function shouldShowGroupHeading<
    T extends
        | RegistryCombRecord
        | CombRecord,
>(
    group: CombGroup<T>,
) {
    if (group.combs.length > 1) {
        return true
    }

    const comb = group.combs[0]

    if (!comb) {
        return false
    }

    const groupName =
        formatLabel(group.name)
            .toLowerCase()

    const combName =
        comb.name.toLowerCase()

    return groupName !== combName
}

function selectCategory(
    category: string,
) {
    selectedCategory.value =
        category
}

function importComb(id: string) {
    router.post(
        `/admin/combs/registry/${id}/import`,
        {},
        {
            preserveScroll: true,
        },
    )
}

function isImported(id: string) {
    return props.combs.some(
        comb =>
            comb.external_id === id,
    )
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Admin Combs" />

        <div
            class="min-h-screen bg-surface-dark text-white"
        >
            <main
                class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8"
            >
                <div
                    class="mx-auto space-y-5"
                >
                    <!-- Header -->
                    <section
                        class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <Box
                                    class="size-6 text-hive"
                                />

                                <div>
                                    <h1
                                        class="text-2xl font-black sm:text-3xl"
                                    >
                                        Combs
                                    </h1>

                                    <p
                                        class="mt-2 text-sm text-zinc-400"
                                    >
                                        Manage local server
                                        templates and import
                                        official Combs from
                                        HiveRegistry.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/combs/create"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                            >
                                <Plus
                                    class="size-4"
                                />

                                New Manual Comb
                            </Link>
                        </div>
                    </section>

                    <!-- Search -->
                    <section
                        class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                    >
                        <div
                            class="relative"
                        >
                            <Search
                                class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500"
                            />

                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search combs, categories, groups, tags..."
                                class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] py-3 pl-10 pr-4 text-sm font-bold text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                            />
                        </div>
                    </section>

                    <!-- Local Combs -->
                    <section
                        class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                    >
                        <div
                            class="mb-5 flex items-center justify-between"
                        >
                            <div>
                                <h2
                                    class="text-lg font-black"
                                >
                                    Local Combs
                                </h2>

                                <p
                                    class="mt-1 text-sm text-zinc-500"
                                >
                                    Combs already
                                    installed into this
                                    panel.
                                </p>
                            </div>

                            <span
                                v-if="
                                    props.combs
                                        .length
                                "
                                class="rounded-full border border-zinc-800 bg-[#0d0f11] px-3 py-1 text-xs font-black text-zinc-500"
                            >
                                {{
                                    props.combs
                                        .length
                                }}
                            </span>
                        </div>

                        <div
                            v-if="
                                filteredLocalCombs
                                    .length === 0
                            "
                            class="rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center"
                        >
                            <Box
                                class="mx-auto size-10 text-zinc-700"
                            />

                            <h3
                                class="mt-4 text-lg font-black text-zinc-300"
                            >
                                {{
                                    search
                                        ? 'No matching local combs'
                                        : 'No local combs yet'
                                }}
                            </h3>

                            <p
                                class="mt-2 text-sm text-zinc-500"
                            >
                                {{
                                    search
                                        ? 'Try another search.'
                                        : 'Import from HiveRegistry or create a manual comb.'
                                }}
                            </p>
                        </div>

                        <div
                            v-else
                            class="space-y-10"
                        >
                            <div
                                v-for="category in groupedLocalCombs"
                                :key="
                                    category.category
                                "
                            >
                                <div
                                    class="mb-5 flex items-center gap-3"
                                >
                                    <h3
                                        class="text-sm font-black uppercase tracking-wide text-zinc-300"
                                    >
                                        {{
                                            formatCategoryName(
                                                category.category,
                                            )
                                        }}
                                    </h3>

                                    <span
                                        class="rounded-full border border-zinc-800 bg-[#0d0f11] px-2 py-0.5 text-[10px] font-black text-zinc-500"
                                    >
                                        {{
                                            category.count
                                        }}
                                        {{
                                            category.count ===
                                            1
                                                ? 'Comb'
                                                : 'Combs'
                                        }}
                                    </span>

                                    <div
                                        class="h-px flex-1 bg-zinc-900"
                                    ></div>
                                </div>

                                <div
                                    class="space-y-7"
                                >
                                    <div
                                        v-for="group in category.groups"
                                        :key="`${category.category}-${group.name}`"
                                    >
                                        <div
                                            v-if="
                                                shouldShowGroupHeading(
                                                    group,
                                                )
                                            "
                                            class="mb-3 flex items-center gap-3"
                                        >
                                            <h4
                                                class="text-xs font-black uppercase tracking-wide text-zinc-500"
                                            >
                                                {{
                                                    formatLabel(
                                                        group.name,
                                                    )
                                                }}
                                            </h4>

                                            <span
                                                v-if="
                                                    group
                                                        .combs
                                                        .length >
                                                    1
                                                "
                                                class="rounded-full border border-zinc-800 bg-[#0d0f11] px-2 py-0.5 text-[10px] font-black text-zinc-600"
                                            >
                                                {{
                                                    group
                                                        .combs
                                                        .length
                                                }}
                                            </span>

                                            <div
                                                class="h-px flex-1 bg-zinc-900/70"
                                            ></div>
                                        </div>

                                        <div
                                            class="grid gap-3 xl:grid-cols-2"
                                        >
                                            <Link
                                                v-for="comb in group.combs"
                                                :key="
                                                    comb.id
                                                "
                                                :href="`/admin/combs/${comb.id}`"
                                                class="block rounded-button border border-zinc-900 bg-[#0d0f11] p-4 transition hover:-translate-y-0.5 hover:border-hive/40 hover:bg-surface-hover active:translate-y-0"
                                            >
                                                <div
                                                    class="flex h-full flex-col justify-between gap-4 sm:flex-row sm:items-center"
                                                >
                                                    <div
                                                        class="min-w-0"
                                                    >
                                                        <div
                                                            class="flex flex-wrap items-center gap-2"
                                                        >
                                                            <h4
                                                                class="text-base font-black text-white"
                                                            >
                                                                {{
                                                                    comb.name
                                                                }}
                                                            </h4>

                                                            <span
                                                                v-if="
                                                                    comb.game
                                                                "
                                                                class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive"
                                                            >
                                                                {{
                                                                    formatLabel(
                                                                        comb.game,
                                                                    )
                                                                }}
                                                            </span>

                                                            <span
                                                                class="rounded-full border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-xs font-bold text-zinc-400"
                                                            >
                                                                {{
                                                                    comb.source ||
                                                                    'local'
                                                                }}
                                                            </span>
                                                        </div>

                                                        <p
                                                            class="mt-1 truncate font-mono text-xs font-medium text-zinc-600"
                                                        >
                                                            {{
                                                                comb.external_id ||
                                                                comb.id
                                                            }}
                                                        </p>

                                                        <div
                                                            v-if="
                                                                comb
                                                                    .tags
                                                                    ?.length
                                                            "
                                                            class="mt-3 flex flex-wrap gap-1.5"
                                                        >
                                                            <span
                                                                v-for="tag in comb.tags"
                                                                :key="
                                                                    tag
                                                                "
                                                                class="rounded-full border border-zinc-800 bg-zinc-900/70 px-2 py-0.5 text-[11px] font-bold text-zinc-500"
                                                            >
                                                                {{
                                                                    tag
                                                                }}
                                                            </span>
                                                        </div>

                                                        <p
                                                            class="mt-3 text-xs text-zinc-600"
                                                        >
                                                            Updated
                                                            {{
                                                                formatDate(
                                                                    comb.updated_at,
                                                                )
                                                            }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="shrink-0 text-sm font-black text-hive"
                                                    >
                                                        Open
                                                        →
                                                    </div>
                                                </div>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- HiveRegistry -->
                    <section
                        class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                    >
                        <div
                            class="flex flex-col gap-4"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div>
                                    <div
                                        class="flex items-center gap-2"
                                    >
                                        <CloudDownload
                                            class="size-5 text-hive"
                                        />

                                        <h2
                                            class="text-lg font-black"
                                        >
                                            HiveRegistry
                                        </h2>

                                        <span
                                            class="rounded-full border border-zinc-800 bg-[#0d0f11] px-2.5 py-0.5 text-xs font-black text-zinc-500"
                                        >
                                            {{
                                                props
                                                    .registryCombs
                                                    .length
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-1 text-sm text-zinc-500"
                                    >
                                        Browse official and
                                        remote Combs available
                                        to import. Wish to add
                                        your own Comb to the
                                        registry? Submit it on
                                        GitHub.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="
                                    registryCategories.length
                                "
                                class="flex flex-wrap gap-2 border-t border-zinc-900 pt-4"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border px-3 py-2 text-xs font-black transition"
                                    :class="
                                        selectedCategory ===
                                        'all'
                                            ? 'border-hive/40 bg-hive/10 text-hive'
                                            : 'border-zinc-800 bg-[#0d0f11] text-zinc-400 hover:border-zinc-700 hover:text-white'
                                    "
                                    @click="
                                        selectCategory(
                                            'all',
                                        )
                                    "
                                >
                                    All

                                    <span
                                        class="rounded-full px-1.5 py-0.5 text-[10px]"
                                        :class="
                                            selectedCategory ===
                                            'all'
                                                ? 'bg-hive/15 text-hive'
                                                : 'bg-zinc-900 text-zinc-500'
                                        "
                                    >
                                        {{
                                            props
                                                .registryCombs
                                                .length
                                        }}
                                    </span>
                                </button>

                                <button
                                    v-for="item in registryCategories"
                                    :key="
                                        item.category
                                    "
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border px-3 py-2 text-xs font-black transition"
                                    :class="
                                        selectedCategory ===
                                        item.category
                                            ? 'border-hive/40 bg-hive/10 text-hive'
                                            : 'border-zinc-800 bg-[#0d0f11] text-zinc-400 hover:border-zinc-700 hover:text-white'
                                    "
                                    @click="
                                        selectCategory(
                                            item.category,
                                        )
                                    "
                                >
                                    {{
                                        formatCategoryName(
                                            item.category,
                                        )
                                    }}

                                    <span
                                        class="rounded-full px-1.5 py-0.5 text-[10px]"
                                        :class="
                                            selectedCategory ===
                                            item.category
                                                ? 'bg-hive/15 text-hive'
                                                : 'bg-zinc-900 text-zinc-500'
                                        "
                                    >
                                        {{
                                            item.count
                                        }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="
                                filteredRegistryCombs
                                    .length === 0
                            "
                            class="mt-5 rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center"
                        >
                            <CloudDownload
                                class="mx-auto size-10 text-zinc-700"
                            />

                            <h3
                                class="mt-4 text-lg font-black text-zinc-300"
                            >
                                No registry combs
                                found
                            </h3>

                            <p
                                class="mt-2 text-sm text-zinc-500"
                            >
                                Try another search
                                or select a different
                                category.
                            </p>
                        </div>

                        <div
                            v-else
                            class="mt-6 space-y-10"
                        >
                            <div
                                v-for="category in groupedRegistryCombs"
                                :key="
                                    category.category
                                "
                            >
                                <div
                                    class="mb-5 flex items-center gap-3"
                                >
                                    <h3
                                        class="text-sm font-black uppercase tracking-wide text-zinc-300"
                                    >
                                        {{
                                            formatCategoryName(
                                                category.category,
                                            )
                                        }}
                                    </h3>

                                    <span
                                        class="rounded-full border border-zinc-800 bg-[#0d0f11] px-2 py-0.5 text-[10px] font-black text-zinc-500"
                                    >
                                        {{
                                            category.count
                                        }}
                                        {{
                                            category.count ===
                                            1
                                                ? 'Comb'
                                                : 'Combs'
                                        }}
                                    </span>

                                    <div
                                        class="h-px flex-1 bg-zinc-900"
                                    ></div>
                                </div>

                                <div
                                    class="space-y-7"
                                >
                                    <div
                                        v-for="group in category.groups"
                                        :key="`${category.category}-${group.name}`"
                                    >
                                        <div
                                            v-if="
                                                shouldShowGroupHeading(
                                                    group,
                                                )
                                            "
                                            class="mb-3 flex items-center gap-3"
                                        >
                                            <h4
                                                class="text-xs font-black uppercase tracking-wide text-zinc-500"
                                            >
                                                {{
                                                    formatLabel(
                                                        group.name,
                                                    )
                                                }}
                                            </h4>

                                            <span
                                                v-if="
                                                    group
                                                        .combs
                                                        .length >
                                                    1
                                                "
                                                class="rounded-full border border-zinc-800 bg-[#0d0f11] px-2 py-0.5 text-[10px] font-black text-zinc-600"
                                            >
                                                {{
                                                    group
                                                        .combs
                                                        .length
                                                }}
                                            </span>

                                            <div
                                                class="h-px flex-1 bg-zinc-900/70"
                                            ></div>
                                        </div>

                                        <div
                                            class="grid gap-3 xl:grid-cols-2"
                                        >
                                            <div
                                                v-for="comb in group.combs"
                                                :key="
                                                    comb.id
                                                "
                                                class="flex flex-col justify-between gap-4 rounded-button border border-zinc-900 bg-[#0d0f11] p-4 transition hover:border-zinc-800 hover:bg-surface-hover sm:flex-row sm:items-center"
                                            >
                                                <div
                                                    class="min-w-0"
                                                >
                                                    <div
                                                        class="flex flex-wrap items-center gap-2"
                                                    >
                                                        <h4
                                                            class="text-base font-black text-white"
                                                        >
                                                            {{
                                                                comb.name
                                                            }}
                                                        </h4>

                                                        <span
                                                            v-if="
                                                                isImported(
                                                                    comb.id,
                                                                )
                                                            "
                                                            class="rounded-full border border-status-success/30 bg-status-success/10 px-2 py-0.5 text-xs font-bold text-status-success"
                                                        >
                                                            Imported
                                                        </span>
                                                    </div>

                                                    <p
                                                        class="mt-1 truncate font-mono text-xs font-medium text-zinc-600"
                                                    >
                                                        {{
                                                            comb.id
                                                        }}
                                                    </p>

                                                    <div
                                                        v-if="
                                                            comb
                                                                .tags
                                                                ?.length
                                                        "
                                                        class="mt-3 flex flex-wrap gap-1.5"
                                                    >
                                                        <span
                                                            v-for="tag in comb.tags"
                                                            :key="
                                                                tag
                                                            "
                                                            class="rounded-full border border-zinc-800 bg-zinc-900/70 px-2 py-0.5 text-[11px] font-bold text-zinc-500"
                                                        >
                                                            {{
                                                                tag
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border px-4 py-2 text-sm font-black transition disabled:cursor-not-allowed disabled:opacity-50"
                                                    :class="
                                                        isImported(
                                                            comb.id,
                                                        )
                                                            ? 'border-zinc-700 bg-zinc-800 text-zinc-400'
                                                            : 'border-hive bg-hive text-black hover:bg-hive-light'
                                                    "
                                                    :disabled="
                                                        isImported(
                                                            comb.id,
                                                        )
                                                    "
                                                    @click="
                                                        importComb(
                                                            comb.id,
                                                        )
                                                    "
                                                >
                                                    <CloudDownload
                                                        class="size-4"
                                                    />

                                                    {{
                                                        isImported(
                                                            comb.id,
                                                        )
                                                            ? 'Imported'
                                                            : 'Import'
                                                    }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>