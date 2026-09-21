<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import {
    CircleCheck,
    Database,
    Pencil,
    Plus,
    RefreshCw,
    Server,
    Trash2,
    X,
} from 'lucide-vue-next'
import { ref } from 'vue'

type DatabaseHost = {
    id: string
    name: string
    driver: string
    host: string
    port: number
    username: string
    public_host?: string | null
    public_port?: number | null
    display_host: string
    display_port: number
    max_databases?: number | null
    databases_count: number
    enabled: boolean
}

const props = defineProps<{
    hosts: DatabaseHost[]
}>()

const modalOpen = ref(false)
const editingHost = ref<DatabaseHost | null>(null)
const deleteHost = ref<DatabaseHost | null>(null)
const testing = ref<string | null>(null)
const testResult = ref<Record<string, string>>({})

const form = useForm({
    name: '',
    driver: 'mysql',
    host: '',
    port: 3306,
    username: '',
    password: '',
    public_host: '',
    public_port: null as number | null,
    max_databases: null as number | null,
    enabled: true,
})

function openCreate() {
    editingHost.value = null
    form.reset()
    form.driver = 'mysql'
    form.port = 3306
    form.enabled = true
    modalOpen.value = true
}

function openEdit(host: DatabaseHost) {
    editingHost.value = host

    form.name = host.name
    form.driver = host.driver
    form.host = host.host
    form.port = host.port
    form.username = host.username
    form.password = ''
    form.public_host = host.public_host ?? ''
    form.public_port = host.public_port ?? null
    form.max_databases = host.max_databases ?? null
    form.enabled = host.enabled

    modalOpen.value = true
}

function closeModal() {
    if (form.processing) return

    modalOpen.value = false
    editingHost.value = null
    form.clearErrors()
}

function saveHost() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false
            editingHost.value = null
            form.reset()
        },
    }

    if (editingHost.value) {
        form.patch(
            `/admin/database-hosts/${editingHost.value.id}`,
            options,
        )
        return
    }

    form.post(
        '/admin/database-hosts',
        options,
    )
}

async function testHost(host: DatabaseHost) {
    testing.value = host.id
    testResult.value[host.id] = ''

    try {
        const response = await fetch(
            `/admin/database-hosts/${host.id}/test`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            },
        )

        const data = await response.json()

        testResult.value[host.id] = response.ok
            ? data.message ?? 'Connection successful.'
            : data.message ?? 'Connection failed.'
    } catch {
        testResult.value[host.id] = 'Connection failed.'
    } finally {
        testing.value = null
    }
}

function destroyHost() {
    if (!deleteHost.value) return

    router.delete(
        `/admin/database-hosts/${deleteHost.value.id}`,
        {
            preserveScroll: true,
            onFinish: () => {
                deleteHost.value = null
            },
        },
    )
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Database Hosts" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Database class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Database Hosts
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Configure MySQL or MariaDB infrastructure used by Cell databases and migrations.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                                @click="openCreate"
                            >
                                <Plus class="size-4" />
                                Add Database Host
                            </button>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div
                            v-if="hosts.length === 0"
                            class="p-10 text-center"
                        >
                            <Server class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No database hosts configured
                            </h2>

                            <p class="mt-2 text-sm text-zinc-500">
                                Add a MySQL or MariaDB host before Cells can create databases.
                            </p>
                        </div>

                        <div
                            v-else
                            class="divide-y divide-zinc-800"
                        >
                            <div
                                v-for="host in hosts"
                                :key="host.id"
                                class="p-5 sm:p-6"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-lg font-black text-white">
                                                {{ host.name }}
                                            </h2>

                                            <span
                                                class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide"
                                                :class="host.enabled
                                                    ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                    : 'border-zinc-700 bg-zinc-800 text-zinc-500'"
                                            >
                                                {{ host.enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </div>

                                        <div class="mt-2 font-mono text-sm text-zinc-400">
                                            {{ host.display_host }}:{{ host.display_port }}
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs font-bold text-zinc-500">
                                            <span>{{ host.databases_count }} database{{ host.databases_count === 1 ? '' : 's' }}</span>
                                            <span>
                                                Capacity:
                                                {{ host.max_databases ?? 'Unlimited' }}
                                            </span>
                                            <span>{{ host.driver }}</span>
                                        </div>

                                        <p
                                            v-if="testResult[host.id]"
                                            class="mt-3 text-xs font-bold"
                                            :class="testResult[host.id].toLowerCase().includes('success')
                                                ? 'text-status-success'
                                                : 'text-status-danger'"
                                        >
                                            {{ testResult[host.id] }}
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive disabled:opacity-50"
                                            :disabled="testing === host.id"
                                            @click="testHost(host)"
                                        >
                                            <CircleCheck
                                                v-if="testing !== host.id"
                                                class="size-4"
                                            />

                                            <RefreshCw
                                                v-else
                                                class="size-4 animate-spin"
                                            />

                                            Test
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                            @click="openEdit(host)"
                                        >
                                            <Pencil class="size-4" />
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 py-2 text-xs font-black text-status-danger transition hover:bg-status-danger/20"
                                            @click="deleteHost = host"
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
            v-if="modalOpen"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-xl rounded-panel border border-zinc-800 bg-surface shadow-[0_25px_80px_rgba(0,0,0,0.55)]">
                <div class="flex items-center justify-between border-b border-zinc-800 px-6 py-5">
                    <h2 class="text-lg font-black text-white">
                        {{ editingHost ? 'Edit Database Host' : 'Add Database Host' }}
                    </h2>

                    <button
                        type="button"
                        class="text-zinc-500 transition hover:text-white"
                        :disabled="form.processing"
                        @click="closeModal"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <form @submit.prevent="saveHost">
                    <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Name
                            </label>

                            <input
                                v-model="form.name"
                                class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                placeholder="Primary MariaDB"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Internal Host
                            </label>

                            <input
                                v-model="form.host"
                                class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive"
                                placeholder="127.0.0.1"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Port
                            </label>

                            <input
                                v-model.number="form.port"
                                type="number"
                                min="1"
                                max="65535"
                                class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Admin Username
                            </label>

                            <input
                                v-model="form.username"
                                class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Admin Password
                            </label>

                            <input
                                v-model="form.password"
                                type="password"
                                class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive"
                                :placeholder="editingHost ? 'Leave blank to keep current password' : ''"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Public Host
                            </label>

                            <input
                                v-model="form.public_host"
                                class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive"
                                placeholder="db01.example.com"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Public Port
                            </label>

                            <input
                                v-model.number="form.public_port"
                                type="number"
                                min="1"
                                max="65535"
                                class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive"
                                placeholder="3306"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Maximum Databases
                            </label>

                            <input
                                v-model.number="form.max_databases"
                                type="number"
                                min="1"
                                class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                placeholder="Unlimited"
                            />
                        </div>

                        <label class="flex items-center gap-3 pt-7">
                            <input
                                v-model="form.enabled"
                                type="checkbox"
                                class="size-4 accent-hive"
                            />

                            <span class="text-sm font-black text-zinc-300">
                                Enabled
                            </span>
                        </label>

                        <div
                            v-if="form.hasErrors"
                            class="sm:col-span-2 rounded-button border border-status-danger/20 bg-status-danger/5 p-3 text-sm text-status-danger"
                        >
                            {{ Object.values(form.errors)[0] }}
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-zinc-800 px-6 py-4">
                        <button
                            type="button"
                            class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300"
                            :disabled="form.processing"
                            @click="closeModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Host' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <ConfirmationModal
            :open="deleteHost !== null"
            title="Delete Database Host?"
            :description="deleteHost
                ? `Delete ${deleteHost.name}? This is only allowed when no Cell databases remain assigned to it.`
                : ''"
            confirm-text="Delete Host"
            :danger="true"
            @cancel="deleteHost = null"
            @confirm="destroyHost"
        />
    </AppLayout>
</template>
