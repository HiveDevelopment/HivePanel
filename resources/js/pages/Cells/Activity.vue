<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Activity, Archive, Calendar, ChevronDown, Clock, FileText, Monitor, Server, Terminal, User, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type AuditUser = {
    id: number;
    name?: string;
    email?: string;
};

type AuditLog = {
    id: number;
    event: string;
    description?: string;
    metadata?: Record<string, any>;
    ip_address?: string;
    user_agent?: string;
    created_at?: string;
    user?: AuditUser | null;
};

const props = defineProps<{
    cell: any;
    logs: AuditLog[];
}>();

const logs = ref<AuditLog[]>([...props.logs]);
const expanded = ref<Record<number, boolean>>({});

const groupedLogs = computed(() => {
    return logs.value.reduce<Record<string, AuditLog[]>>((groups, log) => {
        const key = log.created_at
            ? new Date(log.created_at).toLocaleDateString(undefined, {
                  weekday: 'long',
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              })
            : 'Unknown';

        groups[key] ??= [];
        groups[key].push(log);

        return groups;
    }, {});
});

function formatDate(value?: string) {
    if (!value) return 'Unknown';

    return new Date(value).toLocaleString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function eventLabel(event: string) {
    return event
        .split('.')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function eventClass(event: string) {
    if (event.startsWith('backup.')) return 'border-blue-500/30 bg-blue-500/10 text-blue-300';
    if (event.startsWith('file.')) return 'border-purple-500/30 bg-purple-500/10 text-purple-300';
    if (event.startsWith('server.')) return 'border-hive/30 bg-hive/10 text-hive';
    if (event.startsWith('console.')) return 'border-status-warning/30 bg-status-warning/10 text-status-warning';
    if (event.startsWith('player.')) return 'border-status-danger/30 bg-status-danger/10 text-status-danger';
    if (event.startsWith('schedule.')) return 'border-status-success/30 bg-status-success/10 text-status-success';

    return 'border-border bg-surface-light text-foreground';
}

function eventIcon(event: string) {
    if (event.startsWith('backup.')) return Archive;
    if (event.startsWith('file.')) return FileText;
    if (event.startsWith('server.')) return Server;
    if (event.startsWith('console.')) return Terminal;
    if (event.startsWith('player.')) return Users;
    if (event.startsWith('schedule.')) return Calendar;

    return Activity;
}

function hasMetadata(log: AuditLog) {
    return !!log.metadata && Object.keys(log.metadata).length > 0;
}

function toggleMetadata(id: number) {
    expanded.value[id] = !expanded.value[id];
}
</script>

<template>
    <AppLayout context="server" :active-cell="cell" :active-cell-status="cell.status ?? 'offline'">
        <Head :title="`${cell.name} Activity`" />

        <div class="min-h-screen bg-surface-dark text-foreground">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="overflow-hidden rounded-panel border border-hive/10 bg-surface">
                        <div class="relative p-5 sm:p-6">
                            <div
                                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,196,0,0.12),transparent_35%)]"
                            />

                            <div class="relative flex items-center gap-3">
                                <div class="rounded-xl border border-hive/20 bg-hive/10 p-3">
                                    <Activity class="size-6 text-hive" />
                                </div>

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        {{ t('Activity') }}
                                    </h1>
                                    <p class="mt-1 text-sm text-muted-foreground">{{ t('ui.audit_log_for') }} {{ cell.name }}.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-border bg-surface p-5 sm:p-6">
                        <div v-if="logs.length === 0" class="rounded-button border border-border bg-surface-dark p-10 text-center">
                            <Activity class="mx-auto size-10 text-muted-foreground" />
                            <h2 class="mt-4 text-lg font-black text-foreground">
                                {{ t('ui.no_activity_yet_0a12b92') }}
                            </h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('ui.actions_on_this_server_will_appear_here') }}
                            </p>
                        </div>

                        <div v-else class="space-y-8">
                            <div v-for="(items, date) in groupedLogs" :key="date" class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-px flex-1 bg-surface-light" />
                                    <h2 class="text-xs font-black uppercase tracking-wide text-muted-foreground">
                                        {{ date }}
                                    </h2>
                                    <div class="h-px flex-1 bg-surface-light" />
                                </div>

                                <div class="relative space-y-4">
                                    <div class="absolute bottom-0 left-5 top-0 w-px bg-surface-light" />

                                    <article v-for="log in items" :key="log.id" class="relative flex gap-4">
                                        <div
                                            class="z-10 flex size-10 shrink-0 items-center justify-center rounded-full border bg-surface-dark"
                                            :class="eventClass(log.event)"
                                        >
                                            <component :is="eventIcon(log.event)" class="size-4" />
                                        </div>

                                        <div
                                            class="min-w-0 flex-1 rounded-button border border-border bg-surface-dark p-4 transition hover:border-hive/20"
                                        >
                                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span
                                                            class="rounded-full border px-2 py-0.5 text-xs font-bold"
                                                            :class="eventClass(log.event)"
                                                        >
                                                            {{ eventLabel(log.event) }}
                                                        </span>

                                                        <span class="text-sm font-bold text-foreground">
                                                            {{ log.description || eventLabel(log.event) }}
                                                        </span>
                                                    </div>

                                                    <div class="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
                                                        <span class="inline-flex items-center gap-1">
                                                            <User class="size-3" />
                                                            {{ log.user?.name || log.user?.email || 'System' }}
                                                        </span>

                                                        <span class="inline-flex items-center gap-1">
                                                            <Clock class="size-3" />
                                                            {{ formatDate(log.created_at) }}
                                                        </span>

                                                        <span v-if="log.ip_address" class="inline-flex items-center gap-1">
                                                            <Monitor class="size-3" />
                                                            {{ log.ip_address }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <button
                                                    v-if="hasMetadata(log)"
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-button border border-border px-3 py-1.5 text-xs font-bold text-muted-foreground transition hover:border-hive/30 hover:text-hive"
                                                    @click="toggleMetadata(log.id)"
                                                >
                                                    {{ t('ui.details') }}
                                                    <ChevronDown class="size-3 transition" :class="{ 'rotate-180': expanded[log.id] }" />
                                                </button>
                                            </div>

                                            <pre
                                                v-if="hasMetadata(log) && expanded[log.id]"
                                                class="mt-4 max-h-80 overflow-auto rounded-button border border-border bg-surface-light p-3 text-xs text-muted-foreground"
                                                >{{ JSON.stringify(log.metadata, null, 2) }}</pre
                                            >
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
