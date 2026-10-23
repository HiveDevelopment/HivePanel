<script setup lang="ts">
import { extensionTheme } from '@/composables/useDesignTokens';
import { extensionMessages, locale } from '@/composables/useLanguage';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    extensionId: string;
    src: string;
    title: string;
    cellId?: string;
    compact?: boolean;
    autoHeight?: boolean;
}>();

type BridgeMessage = {
    nectar: 'v1';
    type: string;
    nonce?: string;
    requestId?: string;
    method?: string;
    events?: string[];
    params?: Record<string, unknown>;
    height?: number;
};

const frame = ref<HTMLIFrameElement | null>(null);
const contentHeight = ref(480);
const hasReportedHeight = ref(false);
const nonce = Array.from(crypto.getRandomValues(new Uint8Array(24)), (value) => value.toString(16).padStart(2, '0')).join('');
const pending = new Set<string>();
let subscriptionGeneration = 0;
const subscriptions = new Set<string>();
let pollTimer: ReturnType<typeof setInterval> | undefined;
let previousCells: Map<string, string> | null = null;
let polling = false;
let disposed = false;
const activeRequests = new Set<AbortController>();

async function frameFetch(url: string, options: RequestInit) {
    const controller = new AbortController();
    activeRequests.add(controller);
    const timer = setTimeout(() => controller.abort(), 12000);
    try {
        const response = await fetch(url, { ...options, signal: controller.signal });
        const payload = await response.json().catch(() => ({}));
        if (disposed) throw new Error('Extension frame was closed');
        return { ok: response.ok, status: response.status, payload };
    } finally {
        clearTimeout(timer);
        activeRequests.delete(controller);
    }
}

function setSubscriptions(events: string[]) {
    subscriptionGeneration++;
    subscriptions.clear();
    events.forEach((value) => subscriptions.add(value));
    previousCells = null;
    if (pollTimer) clearInterval(pollTimer);
    if (subscriptions.size && !disposed) {
        void pollCells();
        pollTimer = setInterval(() => void pollCells(), 15000);
    }
}

async function pollCells() {
    if (disposed || polling || !subscriptions.size || document.hidden) return;
    polling = true;
    const generation = subscriptionGeneration;
    try {
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await frameFetch(`/nectar/capabilities/${encodeURIComponent(props.extensionId)}/cells`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
            body: '{}',
            cache: 'no-store',
        });
        if (generation !== subscriptionGeneration) return;
        if (!response.ok) {
            subscriptions.clear();
            return;
        }
        const data = response.payload;
        if (!Array.isArray(data.cells)) throw new Error('Invalid Cell catalogue response');
        if (generation !== subscriptionGeneration) return;
        const current = new Map<string, string>();
        for (const cell of Array.isArray(data.cells) ? data.cells : []) {
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
    } catch {
        /* Transient failures must not emit false delete events. */
    } finally {
        polling = false;
    }
}

function send(message: Record<string, unknown>) {
    // Sandboxed iframes have an opaque origin, so targetOrigin must be '*'.
    // The source window and nonce are checked for every incoming request.
    if (disposed) return;
    frame.value?.contentWindow?.postMessage({ nectar: 'v1', ...message }, '*');
}

async function handleMessage(event: MessageEvent) {
    if (disposed || event.data?.nectar !== 'v1') {
        return;
    }

    if (event.source !== frame.value?.contentWindow || event.origin !== 'null') return;
    const message = event.data as BridgeMessage | null;
    if (!message || message.nectar !== 'v1') return;

    if (message.type === 'ready') {
        send({
            type: 'init',
            nonce,
            cellId: props.cellId ?? null,
            ui: { theme: extensionTheme(), locale: locale.value, messages: extensionMessages() },
        });
        return;
    }

    if (message.type === 'resize' && message.nonce === nonce) {
        if (props.autoHeight && typeof message.height === 'number' && Number.isFinite(message.height)) {
            contentHeight.value = Math.min(12000, Math.max(80, Math.ceil(message.height)));
            hasReportedHeight.value = true;
        }
        return;
    }

    if (message.type === 'subscribe' && message.nonce === nonce) {
        const allowed = new Set(['cell.created', 'cell.updated', 'cell.deleted']);
        const requested = Array.isArray(message.events) ? message.events : [];
        if (requested.length > 3 || requested.some((value) => typeof value !== 'string' || !allowed.has(value))) return;
        // Only accept subscriptions declared in the installed extension manifest.
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const verification = await frameFetch(`/nectar/capabilities/${encodeURIComponent(props.extensionId)}/events`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
            body: JSON.stringify({ events: requested }),
        }).catch(() => null);
        if (disposed || !verification?.ok) return;
        setSubscriptions(requested);
        return;
    }

    if (message.type !== 'request' || message.nonce !== nonce) return;
    if (typeof message.requestId !== 'string' || !/^[\w-]{1,80}$/.test(message.requestId)) return;
    if (pending.has(message.requestId) || pending.size >= 10) return;

    const requestId = message.requestId;
    pending.add(requestId);

    let status: number | undefined;
    try {
        const endpoint =
            message.method === 'events.subscribe'
                ? 'events'
                : message.method === 'cells.list'
                  ? 'cells'
                  : message.method === 'settings.get'
                    ? 'settings'
                    : message.method === 'network.http'
                      ? 'network/http'
                      : message.method === 'network.tcp'
                        ? 'network/tcp'
                        : null;
        if (!endpoint) throw new Error('Unsupported method');
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await frameFetch(`/nectar/capabilities/${encodeURIComponent(props.extensionId)}/${endpoint}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify(message.params ?? {}),
        });
        status = response.status;
        const payload = response.payload;
        if (!response.ok) throw new Error(payload.message ?? `Capability denied (${response.status})`);
        if (message.method === 'events.subscribe') {
            const events = message.params?.events;
            if (!Array.isArray(events) || events.some((value) => typeof value !== 'string')) throw new Error('Invalid events');
            setSubscriptions(events);
        }
        send({ type: 'response', nonce, requestId, ok: true, data: payload });
    } catch (error) {
        send({ type: 'response', nonce, requestId, ok: false, status, error: error instanceof Error ? error.message : 'Request failed' });
    } finally {
        pending.delete(requestId);
    }
}

const sendUi = () => send({ type: 'ui', nonce, ui: { theme: extensionTheme(), locale: locale.value, messages: extensionMessages() } });
onMounted(() => {
    window.addEventListener('message', handleMessage);
    window.addEventListener('hivepanel-ui-changed', sendUi);
});
onBeforeUnmount(() => {
    disposed = true;
    subscriptionGeneration++;
    subscriptions.clear();
    window.removeEventListener('message', handleMessage);
    window.removeEventListener('hivepanel-ui-changed', sendUi);
    if (pollTimer) clearInterval(pollTimer);
    activeRequests.forEach((controller) => controller.abort());
    activeRequests.clear();
});
</script>

<template>
    <iframe
        ref="frame"
        :title="title"
        :src="src"
        sandbox="allow-scripts"
        referrerpolicy="no-referrer"
        class="block w-full border-0 bg-transparent"
        :class="autoHeight && hasReportedHeight ? '' : compact ? 'h-96' : 'min-h-[70vh]'"
        :style="autoHeight && hasReportedHeight ? { height: `${contentHeight}px` } : undefined"
    />
</template>
