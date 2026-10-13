<script setup lang="ts">
import { nextTick, onUnmounted, ref, watch } from 'vue';
import { Terminal, ChevronDown } from 'lucide-vue-next';
const props = defineProps<{ cellId: string }>();
const open = ref(false);
const lines = ref<Array<{ timestamp: string; message: string }>>([]);
const error = ref('');
const output = ref<HTMLElement | null>(null);
const follow = ref(true);
let timer: ReturnType<typeof setInterval> | undefined;
let loading = false;
let request: AbortController | undefined;
async function refresh() {
    if (loading || !open.value) return;
    const requestedId = props.cellId;
    loading = true;
    request = new AbortController();
    try {
        const response = await fetch(`/cells/${props.cellId}/console-json`, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: request.signal });
        if (!response.ok) throw new Error();
        const data = await response.json();
        if (!Array.isArray(data)) throw new Error();
        if (requestedId !== props.cellId) return;
        lines.value = data.slice(-500);
        error.value = '';
        await nextTick();
        if (follow.value && output.value) output.value.scrollTop = output.value.scrollHeight;
    } catch (exception) {
        if (!(exception instanceof DOMException && exception.name === 'AbortError')) error.value = 'Unable to fetch logs. Retrying…';
    } finally { loading = false; }
}
watch(open, value => {
    if (timer) clearInterval(timer);
    if (value) { void refresh(); timer = setInterval(refresh, 2000); }
    else request?.abort();
});
watch(() => props.cellId, () => { request?.abort(); lines.value = []; error.value = ''; });
onUnmounted(() => { if (timer) clearInterval(timer); request?.abort(); });
</script>

<template>
    <section class="overflow-hidden rounded-panel border border-white/[0.08] bg-surface mt-4">
        <button type="button" :aria-expanded="open" aria-controls="installation-log-output" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left" @click="open = !open">
            <span class="flex items-center gap-3">
                <Terminal class="size-4 text-hive" />
                <span>
                    <span class="block text-sm font-semibold text-zinc-200">Installation logs</span>
                    <span class="mt-1 block text-xs text-zinc-500">Live installer output, including Docker and SteamCMD steps.</span>
                </span>
            </span>
            <ChevronDown class="size-4 text-zinc-500 transition" :class="{ 'rotate-180': open }" />
        </button>
        <div v-if="open" id="installation-log-output" class="border-t border-white/[0.06]">
            <div class="flex items-center justify-between px-5 py-3 text-xs text-zinc-500"><span>Latest 500 lines</span><label class="flex items-center gap-2"><input v-model="follow" type="checkbox" /> Follow output</label></div>
            <p v-if="error" role="status" class="px-5 pb-3 text-xs text-status-warning">{{ error }}</p>
            <div ref="output" class="max-h-[420px] min-h-40 overflow-auto bg-surface-dark p-4 font-mono text-xs leading-6 text-zinc-300">
                <p v-if="!lines.length" class="text-zinc-500">Waiting for installer output…</p>
                <p v-for="(line, index) in lines" :key="index" class="whitespace-pre-wrap break-all"><span class="mr-3 text-zinc-600">{{ line.timestamp?.slice(11, 19) }}</span>{{ line.message }}</p>
            </div>
        </div>
    </section>
</template>
