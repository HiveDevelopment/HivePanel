<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import {
    Check,
    Clipboard,
    Database,
    Eye,
    EyeOff,
    KeyRound,
    Plus,
    RefreshCw,
    Server,
    Trash2,
    X,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

type DatabaseHost = {
    id: string
    name: string
    driver: string
    host: string
    port: number
}

type CellDatabase = {
    id: string
    database_name: string
    username: string
    password?: string | null
    allowed_host: string
    charset: string
    collation: string
    managed: boolean
    source: string
    host?: DatabaseHost | null
    created_at?: string | null
}

const props = defineProps<{
    cell: any
    databases: CellDatabase[]
}>()

const databaseList = ref<CellDatabase[]>([
    ...props.databases,
])

const createOpen = ref(false)
const createLoading = ref(false)
const createError = ref('')
const newDatabase = ref({
    name: '',
    allowed_host: '%',
})

const credentialsOpen = ref(false)
const credentialsLoading = ref(false)
const credentialDatabase = ref<CellDatabase | null>(null)
const passwordVisible = ref(false)
const copied = ref<string | null>(null)

const resetDatabase = ref<CellDatabase | null>(null)
const resetLoading = ref(false)

const deleteDatabase = ref<CellDatabase | null>(null)
const deleteLoading = ref(false)

const usage = computed(() =>
    `${databaseList.value.length}/${props.cell.database_limit ?? 0}`
)

const canCreate = computed(() =>
    Number(props.cell.database_limit ?? 0) > databaseList.value.length
)

function csrfToken() {
    return document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]'
    )?.content ?? ''
}

async function readError(
    response: Response,
    fallback: string,
) {
    try {
        const payload = await response.json()

        return payload.message
            ?? fallback
    } catch {
        return fallback
    }
}

function openCreate() {
    createError.value = ''
    newDatabase.value = {
        name: '',
        allowed_host: '%',
    }
    createOpen.value = true
}

async function createDatabase() {
    if (
        createLoading.value
        || !newDatabase.value.name.trim()
    ) {
        return
    }

    createLoading.value = true
    createError.value = ''

    try {
        const response = await fetch(
            `/cells/${props.cell.id}/databases`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    name:
                        newDatabase.value.name.trim(),
                    allowed_host:
                        newDatabase.value.allowed_host.trim()
                        || '%',
                }),
            },
        )

        if (!response.ok) {
            createError.value = await readError(
                response,
                'Unable to create database.',
            )
            return
        }

        const database = await response.json()

        databaseList.value.unshift(
            database
        )

        createOpen.value = false
        credentialDatabase.value =
            database
        passwordVisible.value = true
        credentialsOpen.value = true
    } finally {
        createLoading.value = false
    }
}

async function openCredentials(
    database: CellDatabase,
) {
    credentialsLoading.value = true
    credentialsOpen.value = true
    credentialDatabase.value =
        database
    passwordVisible.value = false

    try {
        const response = await fetch(
            `/cells/${props.cell.id}/databases/${database.id}/credentials`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        )

        if (!response.ok) {
            return
        }

        credentialDatabase.value =
            await response.json()
    } finally {
        credentialsLoading.value = false
    }
}

async function copyValue(
    key: string,
    value?: string | number | null,
) {
    if (
        value === null
        || value === undefined
        || value === ''
    ) {
        return
    }

    await navigator.clipboard.writeText(
        String(value)
    )

    copied.value = key

    window.setTimeout(() => {
        if (copied.value === key) {
            copied.value = null
        }
    }, 1500)
}

async function resetPassword() {
    if (
        !resetDatabase.value
        || resetLoading.value
    ) {
        return
    }

    resetLoading.value = true

    try {
        const response = await fetch(
            `/cells/${props.cell.id}/databases/${resetDatabase.value.id}/reset-password`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            return
        }

        const payload = await response.json()

        const database =
            resetDatabase.value

        resetDatabase.value = null

        await openCredentials(
            database
        )

        if (credentialDatabase.value) {
            credentialDatabase.value.password =
                payload.password
        }

        passwordVisible.value = true
    } finally {
        resetLoading.value = false
    }
}

async function destroyDatabase() {
    if (
        !deleteDatabase.value
        || deleteLoading.value
    ) {
        return
    }

    const database =
        deleteDatabase.value

    deleteLoading.value = true

    try {
        const response = await fetch(
            `/cells/${props.cell.id}/databases/${database.id}`,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            return
        }

        databaseList.value =
            databaseList.value.filter(
                (item) =>
                    item.id !== database.id
            )

        deleteDatabase.value = null
    } finally {
        deleteLoading.value = false
    }
}

function connectionString(
    database: CellDatabase,
) {
    if (!database.host) {
        return ''
    }

    return `mysql://${database.username}@${database.host.host}:${database.host.port}/${database.database_name}`
}

function formatDate(value?: string | null) {
    if (!value) return 'Unknown'

    return new Date(
        value
    ).toLocaleString()
}
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${cell.name} Databases`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Database class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Databases
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Create and manage databases assigned to this Cell.
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2">
                                    <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                        Usage
                                    </div>
                                    <div class="text-sm font-black text-white">
                                        {{ usage }}
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!canCreate"
                                    @click="openCreate"
                                >
                                    <Plus class="size-4" />
                                    New Database
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="Number(cell.database_limit ?? 0) === 0"
                            class="mt-4 rounded-button border border-status-warning/20 bg-status-warning/5 p-3 text-sm text-status-warning"
                        >
                            This Cell does not currently have a database allowance.
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div
                            v-if="databaseList.length === 0"
                            class="p-10 text-center"
                        >
                            <Database class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No databases
                            </h2>

                            <p class="mt-2 text-sm text-zinc-500">
                                Create a database for applications running inside this Cell.
                            </p>
                        </div>

                        <div
                            v-else
                            class="divide-y divide-zinc-800"
                        >
                            <div
                                v-for="database in databaseList"
                                :key="database.id"
                                class="p-5 sm:p-6"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="break-all font-mono text-base font-black text-white">
                                                {{ database.database_name }}
                                            </h2>

                                            <span
                                                v-if="database.source === 'migration'"
                                                class="rounded-full border border-hive/20 bg-hive/5 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-hive"
                                            >
                                                Migrated
                                            </span>
                                        </div>

                                        <div class="mt-3 grid gap-x-8 gap-y-2 text-xs sm:grid-cols-2">
                                            <div>
                                                <span class="font-black uppercase tracking-wide text-zinc-600">
                                                    Host
                                                </span>
                                                <div class="mt-1 font-mono text-zinc-400">
                                                    {{ database.host?.host ?? 'Unknown' }}:{{ database.host?.port ?? '' }}
                                                </div>
                                            </div>

                                            <div>
                                                <span class="font-black uppercase tracking-wide text-zinc-600">
                                                    Username
                                                </span>
                                                <div class="mt-1 break-all font-mono text-zinc-400">
                                                    {{ database.username }}
                                                </div>
                                            </div>

                                            <div>
                                                <span class="font-black uppercase tracking-wide text-zinc-600">
                                                    Allowed Host
                                                </span>
                                                <div class="mt-1 font-mono text-zinc-400">
                                                    {{ database.allowed_host }}
                                                </div>
                                            </div>

                                            <div>
                                                <span class="font-black uppercase tracking-wide text-zinc-600">
                                                    Created
                                                </span>
                                                <div class="mt-1 text-zinc-400">
                                                    {{ formatDate(database.created_at) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                            @click="openCredentials(database)"
                                        >
                                            <KeyRound class="size-4" />
                                            Credentials
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                            @click="resetDatabase = database"
                                        >
                                            <RefreshCw class="size-4" />
                                            Reset Password
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 py-2 text-xs font-black text-status-danger transition hover:bg-status-danger/20"
                                            @click="deleteDatabase = database"
                                        >
                                            <Trash2 class="size-4" />
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <div
            v-if="createOpen"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-lg rounded-panel border border-zinc-800 bg-surface shadow-[0_25px_80px_rgba(0,0,0,0.55)]">
                <div class="flex items-center justify-between border-b border-zinc-800 px-6 py-5">
                    <h2 class="text-lg font-black text-white">
                        Create Database
                    </h2>

                    <button
                        type="button"
                        class="text-zinc-500 transition hover:text-white"
                        :disabled="createLoading"
                        @click="createOpen = false"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="space-y-4 px-6 py-5">

                    <div>
                        <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                            Name
                        </label>

                        <input
                            v-model="newDatabase.name"
                            class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                            placeholder="minecraft"
                            @keydown.enter.prevent="createDatabase"
                        />

                        <p class="mt-2 text-xs leading-5 text-zinc-600">
                            HivePanel adds a unique Cell prefix automatically.
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                            Allowed Host
                        </label>

                        <input
                            v-model="newDatabase.allowed_host"
                            class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive"
                            placeholder="%"
                        />

                        <p class="mt-2 text-xs leading-5 text-zinc-600">
                            Use % to permit the database user from any source address, or restrict it to the Cell/Node network where appropriate.
                        </p>
                    </div>

                    <div
                        v-if="createError"
                        class="rounded-button border border-status-danger/20 bg-status-danger/5 p-3 text-sm text-status-danger"
                    >
                        {{ createError }}
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-800 px-6 py-4">
                    <button
                        type="button"
                        class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300"
                        :disabled="createLoading"
                        @click="createOpen = false"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black disabled:opacity-50"
                        :disabled="createLoading || !newDatabase.name.trim()"
                        @click="createDatabase"
                    >
                        {{ createLoading ? 'Creating...' : 'Create Database' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="credentialsOpen"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-xl rounded-panel border border-zinc-800 bg-surface shadow-[0_25px_80px_rgba(0,0,0,0.55)]">
                <div class="flex items-center justify-between border-b border-zinc-800 px-6 py-5">
                    <h2 class="text-lg font-black text-white">
                        Database Credentials
                    </h2>

                    <button
                        type="button"
                        class="text-zinc-500 transition hover:text-white"
                        @click="credentialsOpen = false"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div
                    v-if="credentialsLoading || !credentialDatabase"
                    class="p-10 text-center text-sm text-zinc-500"
                >
                    Loading credentials...
                </div>

                <div
                    v-else
                    class="space-y-3 px-6 py-5"
                >
                    <div
                        v-for="item in [
                            ['Host', credentialDatabase.host?.host],
                            ['Port', credentialDatabase.host?.port],
                            ['Database', credentialDatabase.database_name],
                            ['Username', credentialDatabase.username],
                        ]"
                        :key="String(item[0])"
                        class="flex items-center justify-between gap-4 rounded-button border border-zinc-800 bg-[#0d0f11] p-3"
                    >
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                {{ item[0] }}
                            </div>

                            <div class="mt-1 break-all font-mono text-sm text-zinc-300">
                                {{ item[1] }}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="shrink-0 text-zinc-500 transition hover:text-hive"
                            @click="copyValue(String(item[0]), item[1])"
                        >
                            <Check
                                v-if="copied === String(item[0])"
                                class="size-4 text-status-success"
                            />
                            <Clipboard
                                v-else
                                class="size-4"
                            />
                        </button>
                    </div>

                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                    Password
                                </div>

                                <div class="mt-1 break-all font-mono text-sm text-zinc-300">
                                    {{
                                        passwordVisible
                                            ? credentialDatabase.password
                                            : '••••••••••••••••••••••••'
                                    }}
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <button
                                    type="button"
                                    class="text-zinc-500 transition hover:text-hive"
                                    @click="passwordVisible = !passwordVisible"
                                >
                                    <EyeOff
                                        v-if="passwordVisible"
                                        class="size-4"
                                    />
                                    <Eye
                                        v-else
                                        class="size-4"
                                    />
                                </button>

                                <button
                                    type="button"
                                    class="text-zinc-500 transition hover:text-hive"
                                    @click="copyValue('Password', credentialDatabase.password)"
                                >
                                    <Check
                                        v-if="copied === 'Password'"
                                        class="size-4 text-status-success"
                                    />
                                    <Clipboard
                                        v-else
                                        class="size-4"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                    Connection
                                </div>

                                <div class="mt-1 break-all font-mono text-xs text-zinc-400">
                                    {{ connectionString(credentialDatabase) }}
                                </div>
                            </div>

                            <button
                                type="button"
                                class="shrink-0 text-zinc-500 transition hover:text-hive"
                                @click="copyValue('Connection', connectionString(credentialDatabase))"
                            >
                                <Check
                                    v-if="copied === 'Connection'"
                                    class="size-4 text-status-success"
                                />
                                <Clipboard
                                    v-else
                                    class="size-4"
                                />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmationModal
            :open="resetDatabase !== null"
            title="Reset Database Password?"
            :description="resetDatabase
                ? `Reset the password for ${resetDatabase.database_name}? Applications using the current password will stop connecting until they are updated.`
                : ''"
            confirm-text="Reset Password"
            :loading="resetLoading"
            @cancel="resetDatabase = null"
            @confirm="resetPassword"
        />

        <ConfirmationModal
            :open="deleteDatabase !== null"
            title="Delete Database?"
            :description="deleteDatabase
                ? `Permanently delete ${deleteDatabase.database_name} and its database user? All data inside this database will be lost.`
                : ''"
            confirm-text="Delete Database"
            :danger="true"
            :loading="deleteLoading"
            @cancel="deleteDatabase = null"
            @confirm="destroyDatabase"
        />
    </AppLayout>
</template>
