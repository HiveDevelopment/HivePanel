<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { Sparkles, CheckCircle2, AlertCircle, LoaderCircle, RefreshCw } from 'lucide-vue-next'

type Provider = 'gemini' | 'openai' | 'openrouter' | 'ollama'
type Model = { id: string; name: string }
type SavedProvider = { has_key: boolean; model: string }

const props = defineProps<{
    settings: {
        enabled: boolean
        provider: Provider
        model: string
        url: string
        has_key: boolean
        providers?: Partial<Record<Provider, SavedProvider>>
    }
}>()

const form = useForm({
    enabled: props.settings.enabled,
    provider: props.settings.provider,
    model: props.settings.model,
    api_key: '',
    clear_key: false,
    url: props.settings.url || 'http://127.0.0.1:11434',
})

const savedProvider = ref<Provider>(props.settings.provider)
const saved = ref<Partial<Record<Provider, SavedProvider>>>({
    ...(props.settings.providers || {}),
    [props.settings.provider]: {
        has_key: props.settings.has_key,
        model: props.settings.model,
    },
})
const models = ref<Model[]>([])
const loadingModels = ref(false)
const modelsError = ref('')
const testing = ref(false)
const testResult = ref<{ ok: boolean; message: string } | null>(null)
const hasKey = computed(() => saved.value[form.provider]?.has_key ?? false)

watch(() => form.provider, provider => {
    form.model = saved.value[provider]?.model || ''
    form.api_key = ''
    form.clear_key = false
    models.value = []
    modelsError.value = ''
    testResult.value = null
})

function csrfHeaders(): Record<string, string> {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content
    const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1]
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
    }
}

async function fetchModels() {
    loadingModels.value = true
    modelsError.value = ''
    try {
        const response = await fetch('/admin/settings/ai/models', {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
            body: JSON.stringify({ provider: form.provider, api_key: form.api_key, url: form.url }),
        })
        const json = await response.json()
        if (!response.ok) throw new Error(json.message || 'Could not retrieve models.')
        models.value = json.models || []
        if (!models.value.length) modelsError.value = 'No compatible models were returned. You can enter a model ID manually.'
    } catch (error) {
        modelsError.value = error instanceof Error ? error.message : 'Could not retrieve models.'
    } finally {
        loadingModels.value = false
    }
}

function save() {
    const keyProvided = !!form.api_key
    const clearKey = form.clear_key
    form.patch('/admin/settings/ai', {
        preserveScroll: true,
        onSuccess: () => {
            savedProvider.value = form.provider
            saved.value[form.provider] = {
                has_key: form.provider !== 'ollama' && !clearKey && (keyProvided || hasKey.value),
                model: form.model,
            }
            form.api_key = ''
            form.clear_key = false
            form.defaults()
            testResult.value = null
        },
    })
}

async function test() {
    testing.value = true
    testResult.value = null
    try {
        const response = await fetch('/admin/settings/ai/test', {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
            body: '{}',
        })
        const json = await response.json()
        testResult.value = {
            ok: response.ok,
            message: (json.message || 'Test failed.') + (json.answer ? ' ' + json.answer : ''),
        }
    } catch {
        testResult.value = { ok: false, message: 'Could not reach the test endpoint.' }
    } finally {
        testing.value = false
    }
}
</script>

<template>
    <section class="space-y-6 rounded-panel border border-white/[0.06] bg-surface p-6">
        <div class="flex items-center gap-3">
            <div class="rounded-xl bg-hive/10 p-3">
                <Sparkles class="size-5 text-hive" />
            </div>
            <div>
                <h3 class="font-semibold text-white">Hive AI</h3>
                <p class="text-sm text-zinc-500">Configure console analysis and other AI features.</p>
            </div>
        </div>

        <form class="space-y-5" @submit.prevent="save">
            <label class="flex items-center gap-3 text-sm text-zinc-200">
                <input v-model="form.enabled" type="checkbox" class="accent-orange-500" />
                Enable Hive AI
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm text-zinc-300">
                    Provider
                    <select v-model="form.provider" class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white">
                        <option value="gemini">Google Gemini</option>
                        <option value="openai">OpenAI</option>
                        <option value="openrouter">OpenRouter</option>
                        <option value="ollama">Ollama (local)</option>
                    </select>
                </label>
                <label class="block text-sm text-zinc-300">
                    Model
                    <select
                        v-if="models.length"
                        v-model="form.model"
                        class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white"
                    >
                        <option value="" disabled>Select a model</option>
                        <option v-if="form.model && !models.some(m => m.id === form.model)" :value="form.model">{{ form.model }} (saved)</option>
                        <option v-for="model in models" :key="model.id" :value="model.id">{{ model.name }} — {{ model.id }}</option>
                    </select>
                    <input
                        v-else
                        v-model="form.model"
                        class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white"
                        placeholder="Fetch models or enter a model ID"
                    />
                </label>
            </div>

            <label v-if="form.provider !== 'ollama'" class="block text-sm text-zinc-300">
                API key
                <span class="text-zinc-500">{{ hasKey ? '(saved — leave blank to keep)' : '(required)' }}</span>
                <input
                    v-model="form.api_key"
                    type="password"
                    autocomplete="new-password"
                    class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white"
                    placeholder="Paste provider API key"
                />
            </label>
            <label v-else class="block text-sm text-zinc-300">
                Ollama URL
                <input v-model="form.url" class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white" />
                <span class="mt-1 block text-xs text-zinc-500">Only loopback addresses are supported.</span>
            </label>

            <div class="space-y-2">
                <button
                    type="button"
                    :disabled="loadingModels"
                    class="inline-flex items-center gap-2 rounded-lg border border-white/10 px-4 py-2.5 text-sm text-white disabled:opacity-40"
                    @click="fetchModels"
                >
                    <LoaderCircle v-if="loadingModels" class="size-4 animate-spin" />
                    <RefreshCw v-else class="size-4" />
                    {{ loadingModels ? 'Fetching models…' : 'Fetch models' }}
                </button>
                <p v-if="modelsError" class="text-sm text-amber-400">{{ modelsError }}</p>
                <p v-if="models.length" class="text-xs text-zinc-500">{{ models.length }} models found. A listed model may still require specific permissions or billing.</p>
            </div>

            <p v-if="form.errors.api_key || form.errors.model || form.errors.url" class="text-sm text-red-400">
                {{ form.errors.api_key || form.errors.model || form.errors.url }}
            </p>
            <p class="text-xs text-zinc-500">API keys are encrypted on the server. Save settings before testing the selected model.</p>

            <div class="flex flex-wrap gap-3">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-hive px-4 py-2.5 text-sm font-semibold text-black disabled:opacity-50">
                    {{ form.processing ? 'Saving…' : 'Save settings' }}
                </button>
                <button type="button" :disabled="testing || form.isDirty" @click="test" class="rounded-lg border border-white/10 px-4 py-2.5 text-sm text-white disabled:opacity-40">
                    <LoaderCircle v-if="testing" class="mr-1 inline size-4 animate-spin" />
                    Test connection
                </button>
            </div>
            <p v-if="form.isDirty" class="text-xs text-amber-400">Save your changes to enable connection testing.</p>
            <div v-if="testResult" class="flex items-start gap-2 rounded-lg border border-white/10 bg-white/[0.03] p-3 text-sm" :class="testResult.ok ? 'text-emerald-400' : 'text-red-400'">
                <CheckCircle2 v-if="testResult.ok" class="size-4 shrink-0" />
                <AlertCircle v-else class="size-4 shrink-0" />
                {{ testResult.message }}
            </div>
        </form>
    </section>
</template>
