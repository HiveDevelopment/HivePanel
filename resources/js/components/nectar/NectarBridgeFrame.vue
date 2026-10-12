<script setup lang="ts">
import { extensionTheme } from '@/composables/useDesignTokens';
import { locale, extensionMessages } from '@/composables/useLanguage';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    extensionId: string;
    src: string;
    title: string;
    cellId?: string;
    compact?: boolean;
}>();

type BridgeMessage = {
    nectar: 'v1';
    type: string;
    nonce?: string;
    requestId?: string;
    method?: string;
    events?: string[];
    params?: Record<string, unknown>;
};

const frame = ref<HTMLIFrameElement | null>(null);
const nonce = Array.from(crypto.getRandomValues(new Uint8Array(24)), value => value.toString(16).padStart(2, '0')).join('');
const pending = new Set<string>();
const subscriptions = new Set<string>();
let pollTimer: ReturnType<typeof setInterval> | undefined;
let previousCells: Map<string, string> | null = null;
let polling = false;

async function pollCells() {
    if (polling || !subscriptions.size || document.hidden) return;
    polling = true;
    try {
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/nectar/capabilities/${encodeURIComponent(props.extensionId)}/cells`, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
            body: '{}', cache: 'no-store',
        });
        if (!response.ok) { subscriptions.clear(); return; }
        const data = await response.json();
        const current = new Map<string, string>();
        for (const cell of (Array.isArray(data.cells) ? data.cells : [])) {
            if (typeof cell.id === 'string') current.set(cell.id, JSON.stringify(cell));
        }
        if (previousCells !== null) {
            for (const [id, value] of current) {
                const type = !previousCells.has(id) ? 'cell.created' : previousCells.get(id) !== value ? 'cell.updated' : null;
                if (type && subscriptions.has(type)) send({ type: 'event', nonce, event: type, cell: JSON.parse(value) });
            }
            for (const id of previousCells.keys()) {
                if (!current.has(id) && subscriptions.has('cell.deleted')) send({ type: 'event', nonce, event: 'cell.deleted', cell: { id } });
            }
        }
        previousCells = current;
    } catch { /* Transient failures must not emit false delete events. */ }
    finally { polling = false; }
}


function send(message: Record<string, unknown>) {
    // Sandboxed iframes have an opaque origin, so targetOrigin must be '*'.
    // The source window and nonce are checked for every incoming request.
    frame.value?.contentWindow?.postMessage({ nectar: 'v1', ...message }, '*');
}

async function handleMessage(event: MessageEvent) {
    if (event.data?.nectar !== 'v1') {
        return
    }

    if (event.source !== frame.value?.contentWindow || event.origin !== 'null') return;
    const message = event.data as BridgeMessage | null;
    if (!message || message.nectar !== 'v1') return;

    if (message.type === 'ready') {
        send({ type: 'init', nonce, cellId: props.cellId ?? null, ui: { theme: extensionTheme(), locale: locale.value, messages: extensionMessages() } });
        return;
    }

    if (message.type === 'subscribe' && message.nonce === nonce) {
        const allowed = new Set(['cell.created', 'cell.updated', 'cell.deleted']);
        const requested = Array.isArray(message.events) ? message.events : [];
        if (requested.length > 3 || requested.some(value => typeof value !== 'string' || !allowed.has(value))) return;
        // Only accept subscriptions declared in the installed extension manifest.
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const verification = await fetch(`/nectar/capabilities/${encodeURIComponent(props.extensionId)}/events`, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
            body: JSON.stringify({ events: requested }),
        }).catch(() => null);
        if (!verification?.ok) return;
        subscriptions.clear();
        requested.forEach(value => subscriptions.add(value));
        previousCells = null;
        if (pollTimer) clearInterval(pollTimer);
        if (subscriptions.size) {
            void pollCells();
            pollTimer = setInterval(() => void pollCells(), 15000);
        }
        return;
    }

    if (message.type !== 'request' || message.nonce !== nonce) return;
    if (typeof message.requestId !== 'string' || !/^[\w-]{1,80}$/.test(message.requestId)) return;
    if (pending.has(message.requestId) || pending.size >= 10) return;

    const requestId = message.requestId;
    pending.add(requestId);

    try {
        const endpoint = message.method === 'cells.list'
            ? 'cells'
            : message.method === 'settings.get'
                ? 'settings'
                : message.method === 'network.http' ? 'network/http'
                    : message.method === 'network.tcp' ? 'network/tcp' : null;
        if (!endpoint) throw new Error('Unsupported method');
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/nectar/capabilities/${encodeURIComponent(props.extensionId)}/${endpoint}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify(message.params ?? {}),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message ?? `Capability denied (${response.status})`);
        send({ type: 'response', nonce, requestId, ok: true, data: payload });
    } catch (error) {
        send({ type: 'response', nonce, requestId, ok: false, error: error instanceof Error ? error.message : 'Request failed' });
    } finally {
        pending.delete(requestId);
    }
}

const sendUi = () => send({ type: 'ui', nonce, ui: { theme: extensionTheme(), locale: locale.value, messages: extensionMessages() } });
onMounted(() => { window.addEventListener('message', handleMessage); window.addEventListener('hivepanel-ui-changed', sendUi); });
onBeforeUnmount(() => { window.removeEventListener('message', handleMessage); window.removeEventListener('hivepanel-ui-changed', sendUi); if (pollTimer) clearInterval(pollTimer); });
</script>

<template>
    <iframe
        ref="frame"
        :title="title"
        :src="src"
        sandbox="allow-scripts"
        referrerpolicy="no-referrer"
        class="w-full border-0 bg-transparent"
        :class="compact ? 'h-96' : 'min-h-[70vh]'"
    />
</template>
