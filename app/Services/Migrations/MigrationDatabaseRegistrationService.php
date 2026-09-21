<?php

namespace App\Services\Migrations;

use App\Models\Cell;
use App\Models\DatabaseHost;
use App\Services\Databases\DatabaseProvisioningService;

class MigrationDatabaseRegistrationService
{
    public function __construct(
        private readonly DatabaseProvisioningService $databases,
    ) {
    }

    public function register(
        Cell $cell,
        array $destinationHost,
        array $destinationDatabase,
        string $password,
        ?string $sourceReference = null,
    ): bool {
        $host = DatabaseHost::query()
            ->where('host', (string) (
                $destinationHost['host']
                ?? ''
            ))
            ->where('port', (int) (
                $destinationHost['port']
                ?? 3306
            ))
            ->first();

        if (! $host) {
            return false;
        }

        $databaseName = trim(
            (string) (
                $destinationDatabase[
                    'database'
                ]
                ?? ''
            )
        );

        $username = trim(
            (string) (
                $destinationDatabase[
                    'username'
                ]
                ?? ''
            )
        );

        if (
            $databaseName === ''
            || $username === ''
            || $password === ''
        ) {
            return false;
        }

        $this->databases->registerExisting(
            cell: $cell,
            host: $host,
            databaseName: $databaseName,
            username: $username,
            password: $password,
            allowedHost: (string) (
                $destinationDatabase[
                    'allowed_host'
                ]
                ?? '%'
            ),
            source: 'migration',
            sourceReference:
                $sourceReference,
        );

        return true;
    }
}