<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';

type SlotName = 'dashboard.widgets' | 'server.console.after' | 'server.overview.after';

type ExtensionWidget = {
    id: string;
    name: string;
    url: string;
};

const props = defineProps<{
    name: SlotName;
    cellId?: string;
}>();

const extensions = ref<ExtensionWidget[]>([]);
const loading = ref(false);
let requestNumber = 0;

async function load() {
    const currentRequest = ++requestNumber;
    loading.value = true;

    try {
        const response = await fetch(`/nectar/slots?slot=${encodeURIComponent(props.name)}${props.cellId ? `&cell=${encodeURIComponent(props.cellId)}` : ''}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });

        if (!response.ok) {
            throw new Error(`Unable to load Nectar slot (${response.status})`);
        }

        const payload = (await response.json()) as {
            extensions?: ExtensionWidget[];
        };

        if (currentRequest === requestNumber) {
            extensions.value = Array.isArray(payload.extensions) ? payload.extensions : [];
        }
    } catch {
        if (currentRequest === requestNumber) {
            extensions.value = [];
        }
    } finally {
        if (currentRequest === requestNumber) {
            loading.value = false;
        }
    }
}

onMounted(load);
watch(() => [props.name, props.cellId], load);
</script>

<template>
    <section v-if="extensions.length" class="grid gap-4" aria-label="Nectar extension widgets">
        <article v-for="extension in extensions" :key="extension.id" class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
            <header class="border-b border-zinc-800 px-5 py-3">
                <h2 class="text-sm font-black text-white">
                    {{ extension.name }}
                </h2>
            </header>

            <iframe
                :title="extension.name"
                :src="extension.url"
                sandbox="allow-scripts"
                referrerpolicy="no-referrer"
                loading="lazy"
                class="h-64 w-full border-0 bg-transparent"
            />
        </article>
    </section>
</template>
