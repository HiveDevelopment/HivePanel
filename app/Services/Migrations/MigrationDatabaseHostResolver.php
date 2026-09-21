<?php

namespace App\Services\Migrations;

use App\Models\PlatformMigrationServer;
use App\Services\Databases\DatabaseHostResolver;
use RuntimeException;

class MigrationDatabaseHostResolver
{
    public function __construct(private readonly DatabaseHostResolver $hosts) {
    }

    public function forServer(PlatformMigrationServer $server): array
    {
        $server->loadMissing('destinationNode');

        if (! $server->destinationNode) {
            throw new RuntimeException('The migration server does not have a destination Node.');
        }

        $host = $this->hosts->forNode($server->destinationNode);

        return [
            'database_host_id' => $host->id,
            'name' => $host->name,
            'host' => $host->host,
            'port' => $host->port,
            'username' => $host->username,
            'password' => $host->password,
            'public_host' => $host->displayHost(),
            'public_port' => $host->displayPort(),
        ];
    }
}