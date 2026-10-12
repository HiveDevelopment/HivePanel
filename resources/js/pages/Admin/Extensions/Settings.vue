<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, CheckCircle2, Eye, EyeOff, LockKeyhole, Save, Settings2, SlidersHorizontal } from 'lucide-vue-next'
import { computed, ref } from 'vue'

type Setting = {
    key: string
    label: string
    description?: string
    type: 'text' | 'number' | 'toggle' | 'select' | 'password'
    options?: string[]
    min?: number
    max?: number
}

const props = defineProps<{
    extension: { id: string; name: string }
    schema: Setting[]
    values: Record<string, string | number | boolean | { configured: boolean }>
}>()

const initial: Record<string, string | number | boolean> = {}
for (const setting of props.schema) {
    const value = props.values[setting.key]
    initial[setting.key] = setting.type === 'password' ? '' : (value as string | number | boolean)
}

const form = useForm({ values: initial })
const showPasswords = ref<Record<string, boolean>>({})
const saved = ref(false)
const settingCount = computed(() => props.schema.length)
const sensitiveCount = computed(() => props.schema.filter((setting) => setting.type === 'password').length)

function submit() {
    saved.value = false
    form.put(`/admin/extensions/${encodeURIComponent(props.extension.id)}/settings`, {
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true
            for (const setting of props.schema) {
                if (setting.type === 'password') form.values[setting.key] = ''
            }
        },
    })
}
</script>

<template>
    <AppLayout context="admin">
        <Head :title="`${extension.name} settings`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Settings2 class="size-6 shrink-0 text-hive" />
                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">Extension Settings</h1>
                                    <p class="mt-2 text-sm text-zinc-400">Configure {{ extension.name }} and manage its preferences.</p>
                                </div>
                            </div>
                            <Link
                                :href="`/admin/extensions/${encodeURIComponent(extension.id)}`"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" /> Back to Extension
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Extension</div>
                            <div class="mt-2 truncate text-lg font-black" :title="extension.name">{{ extension.name }}</div>
                        </div>
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Available Settings</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ settingCount }}</div>
                        </div>
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Protected Fields</div>
                            <div class="mt-1 text-2xl font-black">{{ sensitiveCount }}</div>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                            <div class="flex items-center gap-3 border-b border-zinc-800 px-5 py-5 sm:px-6">
                                <SlidersHorizontal class="size-5 text-hive" />
                                <div>
                                    <h2 class="text-lg font-black">Configuration</h2>
                                    <p class="mt-1 text-sm text-zinc-500">Changes are validated and saved securely by HivePanel.</p>
                                </div>
                            </div>

                            <div v-if="!schema.length" class="px-5 py-10 text-center text-sm text-zinc-400 sm:px-6">
                                This extension has no configurable settings.
                            </div>

                            <div v-else class="grid gap-6 p-5 sm:grid-cols-2 sm:p-6">
                                <div
                                    v-for="setting in schema"
                                    :key="setting.key"
                                    class="space-y-2"
                                    :class="setting.type === 'toggle' ? 'sm:col-span-2' : ''"
                                >
                                    <template v-if="setting.type === 'toggle'">
                                        <label :for="setting.key" class="flex cursor-pointer items-center justify-between gap-4 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                            <span>
                                                <span class="block text-sm font-black text-white">{{ setting.label }}</span>
                                                <span v-if="setting.description" class="mt-1 block text-xs text-zinc-500">{{ setting.description }}</span>
                                            </span>
                                            <input :id="setting.key" v-model="form.values[setting.key]" type="checkbox" class="size-4 shrink-0 accent-hive" />
                                        </label>
                                    </template>
                                    <template v-else>
                                        <label :for="setting.key" class="block text-sm font-black">{{ setting.label }}</label>
                                        <p v-if="setting.description" class="text-xs text-zinc-500">{{ setting.description }}</p>
                                        <select
                                            v-if="setting.type === 'select'"
                                            :id="setting.key"
                                            v-model="form.values[setting.key]"
                                            class="w-full rounded-button border border-zinc-700 bg-[#0d0f11] px-3 py-2.5 text-sm text-white outline-none transition focus:border-hive"
                                        >
                                            <option v-for="option in setting.options" :key="option" :value="option">{{ option }}</option>
                                        </select>
                                        <div v-else class="relative">
                                            <input
                                                :id="setting.key"
                                                v-model="form.values[setting.key]"
                                                :type="setting.type === 'password' ? (showPasswords[setting.key] ? 'text' : 'password') : setting.type === 'number' ? 'number' : 'text'"
                                                :min="setting.min"
                                                :max="setting.max"
                                                :step="setting.type === 'number' ? 'any' : undefined"
                                                :placeholder="setting.type === 'password' && (values[setting.key] as { configured: boolean })?.configured ? 'Configured — leave blank to keep' : ''"
                                                autocomplete="off"
                                                class="w-full rounded-button border border-zinc-700 bg-[#0d0f11] px-3 py-2.5 text-sm text-white outline-none transition focus:border-hive"
                                                :class="setting.type === 'password' ? 'pr-11' : ''"
                                            />
                                            <button
                                                v-if="setting.type === 'password'"
                                                type="button"
                                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-zinc-500 hover:text-white"
                                                :aria-label="showPasswords[setting.key] ? 'Hide password' : 'Show password'"
                                                @click="showPasswords[setting.key] = !showPasswords[setting.key]"
                                            >
                                                <EyeOff v-if="showPasswords[setting.key]" class="size-4" />
                                                <Eye v-else class="size-4" />
                                            </button>
                                        </div>
                                        <p v-if="setting.type === 'password'" class="flex items-center gap-1 text-xs text-zinc-500">
                                            <LockKeyhole class="size-3" /> Sensitive values are not exposed to extensions.
                                        </p>
                                    </template>
                                    <p v-if="form.errors[`values.${setting.key}` as keyof typeof form.errors]" class="text-sm text-red-400">
                                        {{ form.errors[`values.${setting.key}` as keyof typeof form.errors] }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div>
                                <p class="text-sm font-black">Save configuration</p>
                                <p class="mt-1 text-xs text-zinc-500">Settings are stored per extension.</p>
                                <p v-if="form.errors.values" class="mt-2 text-sm text-red-400">{{ form.errors.values }}</p>
                                <p v-if="saved" class="mt-2 flex items-center gap-2 text-sm text-green-400" role="status">
                                    <CheckCircle2 class="size-4" /> Settings saved successfully.
                                </p>
                            </div>
                            <button
                                type="submit"
                                :disabled="form.processing || !schema.length"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-2.5 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save class="size-4" /> {{ form.processing ? 'Saving…' : 'Save Settings' }}
                            </button>
                        </section>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
