<?php

namespace App\Support;

class AdminPermissions
{
    public const DASHBOARD_VIEW = 'admin.dashboard.view';

    public const USERS_VIEW = 'admin.users.view';
    public const USERS_CREATE = 'admin.users.create';
    public const USERS_UPDATE = 'admin.users.update';
    public const USERS_DELETE = 'admin.users.delete';
    public const USERS_ROLES = 'admin.users.roles';

    public const ROLES_VIEW = 'admin.roles.view';
    public const ROLES_CREATE = 'admin.roles.create';
    public const ROLES_UPDATE = 'admin.roles.update';
    public const ROLES_DELETE = 'admin.roles.delete';

    public const CELLS_VIEW = 'admin.cells.view';
    public const CELLS_CREATE = 'admin.cells.create';
    public const CELLS_UPDATE = 'admin.cells.update';
    public const CELLS_DELETE = 'admin.cells.delete';

    public const NODES_VIEW = 'admin.nodes.view';
    public const NODES_CREATE = 'admin.nodes.create';
    public const NODES_UPDATE = 'admin.nodes.update';
    public const NODES_DELETE = 'admin.nodes.delete';

    public const COMBS_VIEW = 'admin.combs.view';
    public const COMBS_CREATE = 'admin.combs.create';
    public const COMBS_UPDATE = 'admin.combs.update';
    public const COMBS_DELETE = 'admin.combs.delete';

    public const DATABASE_HOSTS_VIEW = 'admin.database-hosts.view';
    public const DATABASE_HOSTS_CREATE = 'admin.database-hosts.create';
    public const DATABASE_HOSTS_UPDATE = 'admin.database-hosts.update';
    public const DATABASE_HOSTS_DELETE = 'admin.database-hosts.delete';

    public const MIGRATIONS_VIEW = 'admin.migrations.view';
    public const MIGRATIONS_MANAGE = 'admin.migrations.manage';

    public const SETTINGS_VIEW = 'admin.settings.view';
    public const SETTINGS_UPDATE = 'admin.settings.update';

    public const UPDATES_VIEW = 'admin.updates.view';
    public const UPDATES_INSTALL = 'admin.updates.install';

    public static function groups(): array
    {
        return [
            'General' => [
                self::DASHBOARD_VIEW => 'View admin dashboard',
            ],
            'Users' => [
                self::USERS_VIEW => 'View users',
                self::USERS_CREATE => 'Create users',
                self::USERS_UPDATE => 'Edit users',
                self::USERS_DELETE => 'Delete users',
                self::USERS_ROLES => 'Assign administrative roles',
            ],
            'Roles' => [
                self::ROLES_VIEW => 'View roles',
                self::ROLES_CREATE => 'Create roles',
                self::ROLES_UPDATE => 'Edit roles',
                self::ROLES_DELETE => 'Delete roles',
            ],
            'Cells' => [
                self::CELLS_VIEW => 'View cells',
                self::CELLS_CREATE => 'Create cells',
                self::CELLS_UPDATE => 'Edit and manage cells',
                self::CELLS_DELETE => 'Delete cells',
            ],
            'Nodes' => [
                self::NODES_VIEW => 'View nodes',
                self::NODES_CREATE => 'Create nodes',
                self::NODES_UPDATE => 'Edit and manage nodes',
                self::NODES_DELETE => 'Delete nodes',
            ],
            'Combs' => [
                self::COMBS_VIEW => 'View combs',
                self::COMBS_CREATE => 'Create and import combs',
                self::COMBS_UPDATE => 'Edit combs',
                self::COMBS_DELETE => 'Delete combs',
            ],
            'Database Hosts' => [
                self::DATABASE_HOSTS_VIEW => 'View database hosts',
                self::DATABASE_HOSTS_CREATE => 'Create database hosts',
                self::DATABASE_HOSTS_UPDATE => 'Edit and test database hosts',
                self::DATABASE_HOSTS_DELETE => 'Delete database hosts',
            ],
            'Migrations' => [
                self::MIGRATIONS_VIEW => 'View migrations',
                self::MIGRATIONS_MANAGE => 'Create and manage migrations',
            ],
            'Settings' => [
                self::SETTINGS_VIEW => 'View panel settings',
                self::SETTINGS_UPDATE => 'Change panel settings',
            ],
            'Updates' => [
                self::UPDATES_VIEW => 'View HivePanel updates',
                self::UPDATES_INSTALL => 'Install HivePanel updates',
            ],
        ];
    }

    public static function all(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group) => array_keys($group))
            ->values()
            ->all();
    }
}
