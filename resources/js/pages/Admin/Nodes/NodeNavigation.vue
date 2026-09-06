<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
    Activity,
    Database,
    HardDrive,
    Server,
    Settings,
    SlidersHorizontal,
} from 'lucide-vue-next'

type Tab =
    | 'overview'
    | 'settings'
    | 'configuration'
    | 'allocations'
    | 'database-hosts'
    | 'cells'

defineProps<{
    nodeId: string
    active: Tab
}>()

const tabs = [
    {
        key: 'overview',
        label: 'Overview',
        path: '',
        icon: Activity,
    },
    {
        key: 'settings',
        label: 'Settings',
        path: '/settings',
        icon: Settings,
    },
    {
        key: 'configuration',
        label: 'Configuration',
        path: '/configuration',
        icon: SlidersHorizontal,
    },
    {
        key: 'allocations',
        label: 'Allocations',
        path: '/allocations',
        icon: HardDrive,
    },
    {
        key: 'database-hosts',
        label: 'Database Hosts',
        path: '/database-hosts',
        icon: Database,
    },
    {
        key: 'cells',
        label: 'Cells',
        path: '/cells',
        icon: Server,
    },
] as const
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-1">
        <div class="flex flex-wrap gap-1">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="`/admin/nodes/${nodeId}${tab.path}`"
                class="rounded-button px-4 pt-3 pb-2 text-sm transition"
                :class="
                    active === tab.key
                        ? 'bg-hive/10 font-black text-hive'
                        : 'font-bold text-zinc-400 hover:bg-surface-light hover:text-white'
                "
            >
                <span class="inline-flex items-center gap-2">
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.label }}
                </span>
            </Link>
        </div>
    </section>
</template>