<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Box, Save } from 'lucide-vue-next'
import { VueMonacoEditor } from '@guolao/vue-monaco-editor'
import { watch } from 'vue'

type CombCategory =
    | 'game'
    | 'web'
    | 'database'
    | 'application'
    | 'bot'
    | 'voice'
    | 'runtime'

const categories: Array<{
    value: CombCategory
    label: string
}> = [
    { value: 'game', label: 'Game' },
    { value: 'web', label: 'Web' },
    { value: 'database', label: 'Database' },
    { value: 'application', label: 'Application' },
    { value: 'bot', label: 'Bot' },
    { value: 'voice', label: 'Voice' },
    { value: 'runtime', label: 'Runtime' },
]

const commonGroups = [
    'minecraft',
    'steam',
    'php',
    'nodejs',
    'python',
    'mariadb',
    'postgresql',
    'redis',
]

const defaultManifest = {
    id: 'custom-comb',
    name: 'Custom Comb',
    category: 'game',
    group: 'minecraft',
    game: 'minecraft',
    image: 'hivepanel/java:25',
    working_dir: '/home/container',
    entrypoint: [],
    environment: {
        TZ: 'UTC',
    },
    mounts: [
        {
            source: 'instance',
            target: '/home/container',
        },
    ],
    startup:
        'java -Xms{{memory}}M -Xmx{{memory}}M -jar server.jar nogui',
    variables: {
        memory: '1024',
        version: '1.21.11',
    },
    install: [],
}

const form = useForm({
    external_id: 'custom-comb',
    name: 'Custom Comb',
    category: 'game' as CombCategory,
    group: 'minecraft',
    game: 'minecraft',
    manifest: JSON.stringify(defaultManifest, null, 2),
})

const editorOptions = {
    automaticLayout: true,
    minimap: {
        enabled: false,
    },
    fontSize: 13,
    fontFamily: 'JetBrains Mono, Consolas, monospace',
    scrollBeyondLastLine: false,
    tabSize: 2,
    wordWrap: 'on',
}

let synchronisingManifest = false

function syncManifestFromFields() {
    if (synchronisingManifest) {
        return
    }

    try {
        const manifest = JSON.parse(
            form.manifest || '{}',
        )

        manifest.id = form.external_id
        manifest.name = form.name
        manifest.category = form.category
        manifest.group = form.group

        if (form.game.trim()) {
            manifest.game = form.game.trim()
        } else {
            delete manifest.game
        }

        synchronisingManifest = true

        form.manifest = JSON.stringify(
            manifest,
            null,
            2,
        )

        synchronisingManifest = false
    } catch {
        // Leave invalid manually edited JSON alone.
        // Laravel will return the validation error
        // when the user attempts to save it.
    }
}

watch(
    () => [
        form.external_id,
        form.name,
        form.category,
        form.group,
        form.game,
    ],
    syncManifestFromFields,
)

function submit() {
    syncManifestFromFields()

    form.post('/admin/combs')
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Create Comb" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section
                        class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <Box class="size-6 text-hive" />

                                <div>
                                    <h1
                                        class="text-2xl font-black sm:text-3xl"
                                    >
                                        Create Manual Comb
                                    </h1>

                                    <p
                                        class="mt-2 text-sm text-zinc-400"
                                    >
                                        Add a custom Comb directly into
                                        this HivePanel instance.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/combs"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                            >
                                <ArrowLeft class="size-4" />
                                Back
                            </Link>
                        </div>
                    </section>

                    <form
                        class="space-y-5"
                        @submit.prevent="submit"
                    >
                        <section
                            class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                        >
                            <div class="mb-5">
                                <h2 class="text-lg font-black">
                                    General
                                </h2>

                                <p
                                    class="mt-1 text-sm text-zinc-500"
                                >
                                    Configure how this Comb is identified
                                    and organised throughout HivePanel.
                                </p>
                            </div>

                            <div
                                class="grid gap-4 md:grid-cols-2 xl:grid-cols-5"
                            >
                                <div class="xl:col-span-2">
                                    <label
                                        class="text-xs font-black uppercase tracking-wide text-zinc-500"
                                    >
                                        Comb ID
                                    </label>

                                    <input
                                        v-model="form.external_id"
                                        type="text"
                                        placeholder="e.g. steam-rust"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                                    />

                                    <p
                                        v-if="form.errors.external_id"
                                        class="mt-2 text-xs font-bold text-status-danger"
                                    >
                                        {{ form.errors.external_id }}
                                    </p>
                                </div>

                                <div class="xl:col-span-3">
                                    <label
                                        class="text-xs font-black uppercase tracking-wide text-zinc-500"
                                    >
                                        Name
                                    </label>

                                    <input
                                        v-model="form.name"
                                        type="text"
                                        placeholder="e.g. Rust"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                                    />

                                    <p
                                        v-if="form.errors.name"
                                        class="mt-2 text-xs font-bold text-status-danger"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="text-xs font-black uppercase tracking-wide text-zinc-500"
                                    >
                                        Category
                                    </label>

                                    <select
                                        v-model="form.category"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    >
                                        <option
                                            v-for="category in categories"
                                            :key="category.value"
                                            :value="category.value"
                                        >
                                            {{ category.label }}
                                        </option>
                                    </select>

                                    <p
                                        v-if="form.errors.category"
                                        class="mt-2 text-xs font-bold text-status-danger"
                                    >
                                        {{ form.errors.category }}
                                    </p>
                                </div>

                                <div class="xl:col-span-2">
                                    <label
                                        class="text-xs font-black uppercase tracking-wide text-zinc-500"
                                    >
                                        Group
                                    </label>

                                    <input
                                        v-model="form.group"
                                        type="text"
                                        list="comb-groups"
                                        placeholder="e.g. steam"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                                    />

                                    <datalist id="comb-groups">
                                        <option
                                            v-for="group in commonGroups"
                                            :key="group"
                                            :value="group"
                                        />
                                    </datalist>

                                    <p
                                        v-if="form.errors.group"
                                        class="mt-2 text-xs font-bold text-status-danger"
                                    >
                                        {{ form.errors.group }}
                                    </p>

                                    <p
                                        class="mt-2 text-xs text-zinc-600"
                                    >
                                        Used to group related Combs, such
                                        as Steam or Minecraft.
                                    </p>
                                </div>

                                <div class="xl:col-span-2">
                                    <label
                                        class="text-xs font-black uppercase tracking-wide text-zinc-500"
                                    >
                                        Game
                                    </label>

                                    <input
                                        v-model="form.game"
                                        type="text"
                                        placeholder="e.g. rust"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                                    />

                                    <p
                                        v-if="form.errors.game"
                                        class="mt-2 text-xs font-bold text-status-danger"
                                    >
                                        {{ form.errors.game }}
                                    </p>

                                    <p
                                        class="mt-2 text-xs text-zinc-600"
                                    >
                                        Optional compatibility identifier,
                                        primarily used by game Combs.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                        >
                            <div class="mb-4">
                                <h2 class="text-lg font-black">
                                    Comb JSON
                                </h2>

                                <p
                                    class="mt-1 text-sm text-zinc-500"
                                >
                                    ID, name, category, group and game are
                                    synchronised with the fields above.
                                </p>
                            </div>

                            <div
                                class="overflow-hidden rounded-button border border-zinc-800 bg-[#0d0f11]"
                            >
                                <VueMonacoEditor
                                    v-model:value="form.manifest"
                                    language="json"
                                    theme="vs-dark"
                                    height="560px"
                                    :options="editorOptions"
                                />
                            </div>

                            <p
                                v-if="form.errors.manifest"
                                class="mt-2 text-xs font-bold text-status-danger"
                            >
                                {{ form.errors.manifest }}
                            </p>
                        </section>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save class="size-4" />

                                {{
                                    form.processing
                                        ? 'Creating...'
                                        : 'Create Comb'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>