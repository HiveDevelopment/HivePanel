<script setup lang="ts">
import NavMixed from '@/components/NavMixed.vue';
import SidebarActiveCell from '@/components/sidebar/SidebarActiveCell.vue';
import type { NavEntry } from '@/components/sidebar/navigation';
import { adminNavigation, dashboardNavigation, serverNavigation } from '@/components/sidebar/navigation';
import { Sidebar, SidebarContent, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import { Clock3, Puzzle } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppLogo from './AppLogo.vue';

type CellRuntimeStatus = 'offline' | 'starting' | 'running' | 'stopping' | 'installing';
type CellInstallStatus = 'pending' | 'installing' | 'installed' | 'failed';

const props = defineProps<{
    context?: 'dashboard' | 'server' | 'admin';
    activeCell?: any;
    activeCellStatus?: CellRuntimeStatus;
}>();

const page = usePage<any>();
const extensionPages = ref<Array<{ href: string; title: string; extensionId: string }>>([]);

async function loadExtensionPages() {
    try {
        const response = await fetch('/nectar/navigation', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        if (!response.ok) return;
        const data = await response.json();
        extensionPages.value = Array.isArray(data.pages) ? data.pages : [];
    } catch {
        extensionPages.value = [];
    }
}

onMounted(loadExtensionPages);

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
    const extensionGroup: NavEntry[] = extensionPages.value.length
        ? [
              {
                  type: 'group',
                  title: 'Extensions',
                  items: extensionPages.value.map((extension) => ({
                      title: extension.title,
                      href: extension.href,
                      icon: Puzzle,
                  })),
              },
          ]
        : [];

    // Extension pages are user-facing, not administrator configuration pages.
    if (page.url.startsWith('/extensions/')) {
        return [...dashboardNavigation, ...extensionGroup];
    }
    if (props.context === 'admin') {
        const permissions = page.props.auth?.adminPermissions;
        return adminNavigation(Boolean(page.props.auth?.user?.is_admin), Array.isArray(permissions) ? permissions : []);
    }

    if (props.context !== 'server' || !props.activeCell) return [...dashboardNavigation, ...extensionGroup];

    const cell = props.activeCell;
    const comb = cell?.comb_data ?? cell?.combData ?? cell?.comb?.data ?? {};
    const game = String(comb?.game ?? cell?.game ?? cell?.comb?.game ?? '').toLowerCase();
    return [...serverNavigation(cell.id, game, installed.value, installStatus(cell) === 'failed'), ...extensionGroup];
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
                        <p class="text-xs font-black text-zinc-200">{{ installTitle }}</p>
                        <p class="mt-1 text-[11px] leading-4 text-zinc-500">
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
