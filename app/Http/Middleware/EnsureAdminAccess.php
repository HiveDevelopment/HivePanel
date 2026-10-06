<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        abort_unless($user?->hasAdminAccess(), 403);

        if ($user->is_admin) {
            return $next($request);
        }

        $permission = $this->permissionFor($request);

        abort_unless($permission && $user->hasAdminPermission($permission), 403);

        return $next($request);
    }

    private function permissionFor(Request $request): ?string
    {
        $name = (string) $request->route()?->getName();
        $method = $request->method();

        if ($name === 'admin.dashboard') {
            return AdminPermissions::DASHBOARD_VIEW;
        }

        if (str_starts_with($name, 'admin.users.')) {
            return in_array($name, ['admin.users.index', 'admin.users.show'], true)
                ? AdminPermissions::USERS_VIEW
                : AdminPermissions::USERS_UPDATE;
        }

        if (str_starts_with($name, 'admin.roles.')) {
            return match ($name) {
                'admin.roles.index' => AdminPermissions::ROLES_VIEW,
                'admin.roles.create', 'admin.roles.store' => AdminPermissions::ROLES_CREATE,
                'admin.roles.edit', 'admin.roles.update' => AdminPermissions::ROLES_UPDATE,
                'admin.roles.destroy' => AdminPermissions::ROLES_DELETE,
                default => null,
            };
        }

        if (str_starts_with($name, 'admin.updates.')) {
            return $name === 'admin.updates.install'
                ? AdminPermissions::UPDATES_INSTALL
                : AdminPermissions::UPDATES_VIEW;
        }

        if (str_starts_with($name, 'admin.settings.')) {
            return $name === 'admin.settings.index'
                ? AdminPermissions::SETTINGS_VIEW
                : AdminPermissions::SETTINGS_UPDATE;
        }

        if (str_starts_with($name, 'admin.database-hosts.')) {
            return match ($method) {
                'GET', 'HEAD' => AdminPermissions::DATABASE_HOSTS_VIEW,
                'POST' => str_ends_with($name, '.test') ? AdminPermissions::DATABASE_HOSTS_UPDATE : AdminPermissions::DATABASE_HOSTS_CREATE,
                'PATCH', 'PUT' => AdminPermissions::DATABASE_HOSTS_UPDATE,
                'DELETE' => AdminPermissions::DATABASE_HOSTS_DELETE,
                default => null,
            };
        }

        if (str_starts_with($name, 'admin.nodes.')) {
            if ($method === 'GET' || $method === 'HEAD') {
                return AdminPermissions::NODES_VIEW;
            }

            if ($name === 'admin.nodes.store') {
                return AdminPermissions::NODES_CREATE;
            }

            if ($name === 'admin.nodes.destroy') {
                return AdminPermissions::NODES_DELETE;
            }

            return AdminPermissions::NODES_UPDATE;
        }

        if (str_starts_with($name, 'admin.cells.')) {
            if ($method === 'GET' || $method === 'HEAD') {
                return AdminPermissions::CELLS_VIEW;
            }

            if ($name === 'admin.cells.store') {
                return AdminPermissions::CELLS_CREATE;
            }

            if ($name === 'admin.cells.destroy') {
                return AdminPermissions::CELLS_DELETE;
            }

            return AdminPermissions::CELLS_UPDATE;
        }

        if (str_starts_with($name, 'admin.combs.')) {
            if ($method === 'GET' || $method === 'HEAD') {
                return AdminPermissions::COMBS_VIEW;
            }

            if (in_array($name, ['admin.combs.store', 'admin.combs.registry.import'], true)) {
                return AdminPermissions::COMBS_CREATE;
            }

            if ($name === 'admin.combs.destroy') {
                return AdminPermissions::COMBS_DELETE;
            }

            return AdminPermissions::COMBS_UPDATE;
        }

        if (str_starts_with($name, 'admin.migrations.')) {
            return in_array($method, ['GET', 'HEAD'], true)
                ? AdminPermissions::MIGRATIONS_VIEW
                : AdminPermissions::MIGRATIONS_MANAGE;
        }

        return null;
    }
}
