import type { NavItem } from '@/types'
import {
    Archive,
    Boxes,
    Calendar,
    Clock3,
    Cpu,
    Database,
    DownloadCloud,
    FolderOpen,
    Glasses,
    Home,
    Network,
    RefreshCw,
    Rocket,
    Server,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Terminal,
    Users,
    Puzzle,
} from 'lucide-vue-next'

export type NavEntry = (NavItem & { type?: 'item' }) | {
    type: 'group'
    id?: string
    title: string
    items: NavItem[]
}

const item = (title: string, href: string, icon: NavItem['icon']): NavItem => ({ title, href, icon })
const groupIds: Record<string, string> = { 'Overview & Access': 'overview', Management: 'management', 'Automation & Recovery': 'automation', Configuration: 'configuration', Minecraft: 'game', 'Discord Bot': 'game', Steam: 'game' }
const group = (title: string, items: NavItem[]): NavEntry => ({ type: 'group', id: groupIds[title], title, items })

export const dashboardNavigation: NavEntry[] = [
    item('Servers', '', Home),
]

export function serverNavigation(cellId: string | number, game: string | Record<string, any>, installed: boolean, failed: boolean): NavEntry[] {
    const url = (path = '') => `/cells/${cellId}${path}`

    if (!installed) {
        return [item(failed ? 'Installation Failed' : 'Installation', url(), Clock3)]
    }

    const entries: NavEntry[] = [
        group('Overview & Access', [
            item('Console', url(), Terminal),
            item('Sub Users', url('/users'), Users),
            item('Activity', url('/activity'), Glasses),
        ]),
        group('Management', [
            item('Files', url('/files'), FolderOpen),
            item('Databases', url('/databases'), Database),
            item('Network', url('/network'), Network),
        ]),
        group('Automation & Recovery', [
            item('Backups', url('/backups'), Archive),
            item('Scheduled Tasks', url('/schedules'), Calendar),
            item('Server Importer', url('/importer'), DownloadCloud),
        ]),
        group('Configuration', [
            item('Startup', url('/startup'), Rocket),
            item('Config Editor', url('/config'), SlidersHorizontal),
            item('Settings', url('/settings'), Settings),
        ]),
    ]

    const manifest = typeof game === 'object' ? game as Record<string, any> : { game };
    const normalizedGame = String(manifest.game || manifest.group || '').toLowerCase();
    const gameItems: NavItem[] = [];
    let gameTitle = String(manifest.name ?? (normalizedGame || 'Application'));
    if (normalizedGame === 'minecraft') {
        gameTitle = 'Minecraft';
        gameItems.push(item('Players', url('/players'), Users), item('Plugins', url('/files?path=plugins'), Boxes), item('Mods', url('/files?path=mods'), Boxes));
    } else if (['discord', 'discord-bot', 'discord_bot'].includes(normalizedGame)) {
        gameTitle = 'Discord Bot';
        gameItems.push(item('Environment', url('/startup'), SlidersHorizontal), item('Application Files', url('/files'), FolderOpen));
    } else {
        gameItems.push(item('Game Configuration', url('/config'), SlidersHorizontal), item('Game Files', url('/files'), FolderOpen));
    }
    // Optional Comb shortcuts point to scoped panel pages, never arbitrary URLs.
    for (const shortcut of Array.isArray(manifest.sidebar) ? manifest.sidebar.slice(0, 12) : []) {
        if (typeof shortcut?.title !== 'string') continue;
        if (shortcut.type === 'files' && typeof shortcut.path === 'string' && !shortcut.path.includes('..') && !shortcut.path.startsWith('/')) gameItems.push(item(shortcut.title, url('/files?path=' + encodeURIComponent(shortcut.path)), FolderOpen));
        if (shortcut.type === 'config') gameItems.push(item(shortcut.title, url('/config'), SlidersHorizontal));
    }
    if (gameItems.length) entries.push({ type: 'group', id: 'game', title: gameTitle, items: gameItems });

    return entries
}

const adminPermissions: Record<string, string> = {
    '/admin': 'admin.dashboard.view',
    '/admin/users': 'admin.users.view',
    '/admin/roles': 'admin.roles.view',
    '/admin/themes': 'admin.settings.view',
    '/admin/settings': 'admin.settings.view',
    '/admin/updates': 'admin.updates.view',
    '/admin/nodes': 'admin.nodes.view',
    '/admin/database-hosts': 'admin.database-hosts.view',
    '/admin/cells': 'admin.cells.view',
    '/admin/combs': 'admin.combs.view',
    '/admin/migrations': 'admin.migrations.view',
    '/admin/extensions': 'admin.extensions.view',
}

export function adminNavigation(isSuperAdmin: boolean, permissions: string[]): NavEntry[] {
    const entries: NavEntry[] = [
        group('Overview', [item('Dashboard', '/admin', Home)]),
        group('Infrastructure', [
            item('Nodes', '/admin/nodes', Cpu),
            item('Database Hosts', '/admin/database-hosts', Database),
        ]),
        group('Server Management', [
            item('Cells', '/admin/cells', Server),
            item('Combs', '/admin/combs', Boxes),
            item('Migrations', '/admin/migrations', Rocket),
        ]),
        group('Users & Access', [
            item('Users', '/admin/users', Users),
            item('Roles', '/admin/roles', ShieldCheck),
        ]),
        group('Platform', [
            item('Extensions', '/admin/extensions', Puzzle),
            item('Updates', '/admin/updates', RefreshCw),
            item('Themes', '/admin/themes', SlidersHorizontal),
            item('Settings', '/admin/settings', Settings),
        ]),
    ]

    return entries.flatMap(entry => {
        if (entry.type !== 'group') {
            return [entry]
        }

        const visible = entry.items.filter(link => {
            const permission = adminPermissions[link.href]

            return (
                isSuperAdmin ||
                (permission !== undefined && permissions.includes(permission))
            )
        })

        return visible.length
            ? [{ ...entry, items: visible }]
            : []
    })
}
