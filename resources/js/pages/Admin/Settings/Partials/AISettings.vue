
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import {
    Sparkles,
    CheckCircle2,
    AlertCircle,
    LoaderCircle,
} from 'lucide-vue-next'

const props = defineProps<{
    settings: {
        enabled: boolean
        provider: string
        model: string
        url: string
        has_key: boolean
    }
}>()

const defaults: Record<string, string> = {
    gemini: 'gemini-2.5-flash-lite',
    openai: 'gpt-4o-mini',
    openrouter: 'openai/gpt-oss-20b:free',
    ollama: 'qwen2.5:7b',
}

const form = useForm({
    enabled: props.settings.enabled,
    provider: props.settings.provider,
    model: props.settings.model,
    api_key: '',
    clear_key: false,
    url: props.settings.url || 'http://127.0.0.1:11434',
})

const savedProvider = ref(props.settings.provider)
const hasKey = ref(props.settings.has_key)
const testing = ref(false)
const testResult = ref<{ ok: boolean; message: string } | null>(null)

watch(
    () => form.provider,
    (provider) => {
        form.model = defaults[provider] || ''
        form.api_key = ''
        form.clear_key = false
        testResult.value = null
    },
)

function save() {
    form.patch('/admin/settings/ai', {
        preserveScroll: true,
        onSuccess: () => {
            savedProvider.value = form.provider
            hasKey.value =
                form.provider === 'ollama'
                    ? false
                    : !!form.api_key || (hasKey.value && !form.clear_key)

            form.api_key = ''
            form.clear_key = false
            testResult.value = null
        },
    })
}

async function test() {
    testing.value = true
    testResult.value = null

    try {
        const token = (
            document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
        )?.content

        const response = await fetch('/admin/settings/ai/test', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] || '',
                ),
            },
            body: '{}',
        })

        const json = await response.json()

        testResult.value = {
            ok: response.ok,
            message: json.message + (json.answer ? ' ' + json.answer : ''),
        }
    } catch {
        testResult.value = {
            ok: false,
            message: 'Could not reach the test endpoint.',
        }
    } finally {
        testing.value = false
    }
}
</script>

<template>
    <section
        class="rounded-panel border border-white/[0.06] bg-surface p-6 space-y-6"
    >
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="rounded-xl bg-hive/10 p-3">
                <Sparkles class="size-5 text-hive" />
            </div>

            <div>
                <h3 class="font-semibold text-white">
                    Hive AI
                </h3>

                <p class="text-sm text-zinc-500">
                    Configure console analysis and other AI features.
                </p>
            </div>
        </div>

        <!-- Settings Form -->
        <form
            class="space-y-5"
            @submit.prevent="save"
        >
            <!-- Enable AI -->
            <label class="flex items-center gap-3 text-sm text-zinc-200">
                <input
                    v-model="form.enabled"
                    type="checkbox"
                    class="accent-orange-500"
                />

                Enable Hive AI
            </label>

            <!-- Provider & Model -->
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm text-zinc-300">
                    Provider

                    <select
                        v-model="form.provider"
                        class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white"
                    >
                        <option value="gemini">
                            Google Gemini
                        </option>

                        <option value="openai">
                            OpenAI
                        </option>

                        <option value="openrouter">
                            OpenRouter
                        </option>

                        <option value="ollama">
                            Ollama (local)
                        </option>
                    </select>
                </label>

                <label class="block text-sm text-zinc-300">
                    Model

                    <input
                        v-model="form.model"
                        class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white"
                        placeholder="Model identifier"
                    />
                </label>
            </div>

            <!-- API Key -->
            <label
                v-if="form.provider !== 'ollama'"
                class="block text-sm text-zinc-300"
            >
                API key

                <span class="text-zinc-500">
                    {{
                        savedProvider === form.provider && hasKey
                            ? '(saved — leave blank to keep)'
                            : '(required)'
                    }}
                </span>

                <input
                    v-model="form.api_key"
                    type="password"
                    autocomplete="new-password"
                    class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white"
                    placeholder="Paste provider API key"
                />
            </label>

            <!-- Ollama URL -->
            <label
                v-else
                class="block text-sm text-zinc-300"
            >
                Ollama URL

                <input
                    v-model="form.url"
                    class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white"
                    placeholder="http://127.0.0.1:11434"
                />

                <span class="mt-1 block text-xs text-zinc-500">
                    For security, only local loopback addresses are supported.
                </span>
            </label>

            <!-- Validation Errors -->
            <p
                v-if="form.errors.api_key || form.errors.model || form.errors.url"
                class="text-sm text-red-400"
            >
                {{
                    form.errors.api_key ||
                    form.errors.model ||
                    form.errors.url
                }}
            </p>

            <!-- Security Notice -->
            <p class="text-xs text-zinc-500">
                API keys are encrypted on the server and never returned to your
                browser. Save changes before testing.
            </p>

            <!-- Actions -->
            <div class="flex flex-wrap gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-hive px-4 py-2.5 text-sm font-semibold text-black disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save settings' }}
                </button>

                <button
                    type="button"
                    :disabled="testing || form.isDirty"
                    @click="test"
                    class="rounded-lg border border-white/10 px-4 py-2.5 text-sm text-white disabled:opacity-40"
                >
                    <LoaderCircle
                        v-if="testing"
                        class="mr-1 inline size-4 animate-spin"
                    />

                    Test connection
                </button>
            </div>

            <!-- Unsaved Changes Notice -->
            <p
                v-if="form.isDirty"
                class="text-xs text-amber-400"
            >
                Save your changes to enable connection testing.
            </p>

            <!-- Connection Test Result -->
            <div
                v-if="testResult"
                class="flex items-start gap-2 rounded-lg border border-white/10 bg-white/[0.03] p-3 text-sm"
                :class="testResult.ok ? 'text-emerald-400' : 'text-red-400'"
            >
                <CheckCircle2
                    v-if="testResult.ok"
                    class="size-4 shrink-0"
                />

                <AlertCircle
                    v-else
                    class="size-4 shrink-0"
                />

                {{ testResult.message }}
            </div>
        </form>
    </section>
</template>