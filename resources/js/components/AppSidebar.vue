<script setup lang="ts">
import NavMixed from '@/components/NavMixed.vue';
import SidebarActiveCell from '@/components/sidebar/SidebarActiveCell.vue';
import type { NavEntry } from '@/components/sidebar/navigation';
import { adminNavigation, dashboardNavigation, serverNavigation } from '@/components/sidebar/navigation';
import { Sidebar, SidebarContent, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import { Clock3, Download, Network, Puzzle, Settings, Terminal, Vote } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AppLogo from './AppLogo.vue';

type CellRuntimeStatus = 'offline' | 'starting' | 'running' | 'stopping' | 'installing';
type CellInstallStatus = 'pending' | 'installing' | 'installed' | 'failed';

const props = defineProps<{
    context?: 'dashboard' | 'server' | 'admin';
    activeCell?: any;
    activeCellStatus?: CellRuntimeStatus;
}>();

const page = usePage<any>();
type ExtensionPage = { href: string; title: string; extensionId: string; sidebarGroup?: string; icon?: string };
const extensionPages = ref<ExtensionPage[]>([]);
let navigationRequest = 0;

async function loadExtensionPages() {
    const current = ++navigationRequest;
    try {
        const response = await fetch(
            `/nectar/navigation${props.context === 'server' && props.activeCell?.id ? `?cell=${encodeURIComponent(props.activeCell.id)}` : ''}`,
            {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            },
        );
        if (!response.ok) {
            if (current === navigationRequest) extensionPages.value = [];
            return;
        }
        const data = await response.json();
        if (current === navigationRequest) extensionPages.value = Array.isArray(data.pages) ? data.pages : [];
    } catch {
        if (current === navigationRequest) extensionPages.value = [];
    }
}

onMounted(loadExtensionPages);
watch(() => [props.context, props.activeCell?.id, page.url], loadExtensionPages);

function installStatus(cell: any): CellInstallStatus {
    const status = String(cell?.install_status ?? cell?.installStatus ?? 'installed').toLowerCase();
    return ['pending', 'installing', 'installed', 'failed'].includes(status) ? (status as CellInstallStatus) : 'installed';
}

const installed = computed(() => !props.activeCell || installStatus(props.activeCell) === 'installed');
const sidebarCellStatus = computed<CellRuntimeStatus>(() =>
    props.activeCell && !installed.value ? 'installing' : (props.activeCellStatus ?? 'offline'),
);

const installTitle = computed(() => {
    const status = installStatus(props.activeCell);
    if (status === 'failed') return 'Installation failed';
    return status === 'pending' ? 'Waiting to install' : 'Installation in progress';
});

const installDescription = computed(() =>
    installStatus(props.activeCell) === 'failed'
        ? 'Server controls are unavailable until the installation is retried successfully.'
        : 'Server controls will become available when installation has completed.',
);

const navigation = computed<NavEntry[]>(() => {
    const icons = { puzzle: Puzzle, vote: Vote, settings: Settings, download: Download, network: Network, terminal: Terminal };
    const integrate = (base: NavEntry[]): NavEntry[] => {
        const entries = base.map((entry) => (entry.type === 'group' ? { ...entry, items: [...entry.items] } : { ...entry }));
        for (const extension of extensionPages.value) {
            const requested = extension.sidebarGroup ?? 'extensions';
            let target = entries.find((entry) => entry.type === 'group' && entry.id === requested);
            if (!target || target.type !== 'group') {
                const id = requested === 'tools' ? 'tools' : 'extensions';
                target = entries.find((entry) => entry.type === 'group' && entry.id === id);
                if (!target) {
                    target = { type: 'group', id, title: id === 'tools' ? 'Tools' : 'Extensions', items: [] };
                    entries.push(target);
                }
            }
            if (target.type === 'group')
                target.items.push({ title: extension.title, href: extension.href, icon: icons[extension.icon as keyof typeof icons] ?? Puzzle });
        }
        return entries;
    };

    // Extension pages are user-facing, not administrator configuration pages.
    if (page.url.startsWith('/extensions/')) {
        return integrate(dashboardNavigation);
    }
    if (props.context === 'admin') {
        const permissions = page.props.auth?.adminPermissions;
        return adminNavigation(Boolean(page.props.auth?.user?.is_admin), Array.isArray(permissions) ? permissions : []);
    }

    if (props.context !== 'server' || !props.activeCell) return integrate(dashboardNavigation);

    const cell = props.activeCell;
    const comb = cell?.comb_data ?? cell?.combData ?? cell?.comb?.data ?? {};
    const game = String(comb?.game ?? cell?.game ?? cell?.comb?.game ?? '').toLowerCase();
    return integrate(serverNavigation(cell.id, { ...comb, game }, installed.value, installStatus(cell) === 'failed'));
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarActiveCell v-if="context === 'server' && activeCell" :cell="activeCell" :status="sidebarCellStatus" />

            <div
                v-if="context === 'server' && activeCell && !installed"
                class="mx-3 mb-3 rounded-button border border-hive/20 bg-hive/5 px-3 py-3 group-data-[collapsible=icon]:hidden"
            >
                <div class="flex items-start gap-2">
                    <Clock3 class="mt-0.5 size-4 shrink-0 text-hive" />
                    <div class="min-w-0">
                        <p class="text-xs font-black text-foreground">{{ installTitle }}</p>
                        <p class="mt-1 text-[11px] leading-4 text-muted-foreground">
                            {{ installDescription }}
                        </p>
                    </div>
                </div>
            </div>

            <NavMixed :items="navigation" />
        </SidebarContent>
    </Sidebar>

    <slot />
</template>
