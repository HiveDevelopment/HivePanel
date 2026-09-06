<?php

namespace App\Services\Databases;

use App\Models\Cell;
use App\Models\CellDatabase;
use App\Models\DatabaseHost;
use PDO;
use RuntimeException;
use Throwable;
use Illuminate\Support\Str;

class DatabaseProvisioningService
{
    public function __construct(private readonly DatabaseHostResolver $resolver) {
    }

    private const DATABASE_NAME_MAX_LENGTH = 64;
    private const USERNAME_MAX_LENGTH = 32;

    public function testHost(DatabaseHost $host): void
    {
        $pdo = $this->pdo($host);

        $pdo->query('SELECT 1')->fetchColumn();
    }

    public function create(Cell $cell, string $label, string $allowedHost = '%'): CellDatabase
    {
        $this->enforceCellLimit($cell);

        $host = $this->resolver->forCell($cell);

        $this->enforceHostLimit($host);

        $databaseName = $this->databaseName(
            $cell,
            $label,
        );

        $username = $this->databaseUsername(
            $cell,
            $label,
        );

        $password = Str::password(
            length: 32,
            letters: true,
            numbers: true,
            symbols: false,
            spaces: false,
        );

        $pdo = $this->pdo($host);

        try {
            $this->createDatabaseAndUser(
                pdo: $pdo,
                database: $databaseName,
                username: $username,
                password: $password,
                allowedHost: $allowedHost,
            );
        } catch (Throwable $exception) {
            $this->bestEffortCleanup(
                pdo: $pdo,
                database: $databaseName,
                username: $username,
                allowedHost: $allowedHost,
            );

            throw $exception;
        }

        try {
            return CellDatabase::create([
                'cell_id' => $cell->id,
                'database_host_id' => $host->id,
                'database_name' => $databaseName,
                'username' => $username,
                'password' => $password,
                'allowed_host' => $allowedHost,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'managed' => true,
                'source' => 'panel',
            ]);
        } catch (Throwable $exception) {
            $this->bestEffortCleanup(
                pdo: $pdo,
                database: $databaseName,
                username: $username,
                allowedHost: $allowedHost,
            );

            throw $exception;
        }
    }

    public function delete(CellDatabase $database): void
    {
        $database->loadMissing('host');

        if (! $database->host) {
            throw new RuntimeException(
                'The database host no longer exists.'
            );
        }

        if ($database->managed) {
            $pdo = $this->pdo($database->host);

            $pdo->exec(
                'DROP DATABASE IF EXISTS '
                . $this->quoteIdentifier(
                    $database->database_name
                )
            );

            $pdo->exec(
                'DROP USER IF EXISTS '
                . $this->quoteAccount(
                    $pdo,
                    $database->username,
                    $database->allowed_host,
                )
            );
        }

        $database->delete();
    }

    public function resetPassword(
        CellDatabase $database,
    ): string {
        $database->loadMissing('host');

        if (! $database->host) {
            throw new RuntimeException(
                'The database host no longer exists.'
            );
        }

        if (! $database->managed) {
            throw new RuntimeException(
                'This database is not managed by HivePanel.'
            );
        }

        $password = Str::password(
            length: 32,
            letters: true,
            numbers: true,
            symbols: false,
            spaces: false,
        );

        $pdo = $this->pdo(
            $database->host
        );

        $pdo->exec(
            'ALTER USER '
            . $this->quoteAccount(
                $pdo,
                $database->username,
                $database->allowed_host,
            )
            . ' IDENTIFIED BY '
            . $pdo->quote($password)
        );

        $database->forceFill([
            'password' => $password,
        ])->save();

        return $password;
    }

    public function registerExisting(
        Cell $cell,
        DatabaseHost $host,
        string $databaseName,
        string $username,
        string $password,
        string $allowedHost = '%',
        string $source = 'migration',
        ?string $sourceReference = null,
    ): CellDatabase {
        return CellDatabase::updateOrCreate(
            [
                'database_host_id' => $host->id,
                'database_name' => $databaseName,
            ],
            [
                'cell_id' => $cell->id,
                'username' => $username,
                'password' => $password,
                'allowed_host' => $allowedHost,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'managed' => true,
                'source' => $source,
                'source_reference' => $sourceReference,
            ]
        );
    }

    private function createDatabaseAndUser(
        PDO $pdo,
        string $database,
        string $username,
        string $password,
        string $allowedHost,
    ): void {
        $quotedDatabase = $this->quoteIdentifier(
            $database
        );

        $account = $this->quoteAccount(
            $pdo,
            $username,
            $allowedHost,
        );

        $pdo->exec(
            "CREATE DATABASE {$quotedDatabase} "
            . 'CHARACTER SET utf8mb4 '
            . 'COLLATE utf8mb4_unicode_ci'
        );

        $pdo->exec(
            "CREATE USER {$account} IDENTIFIED BY "
            . $pdo->quote($password)
        );

        $pdo->exec(
            "GRANT ALL PRIVILEGES ON {$quotedDatabase}.* "
            . "TO {$account}"
        );
    }

    private function bestEffortCleanup(
        PDO $pdo,
        string $database,
        string $username,
        string $allowedHost,
    ): void {
        try {
            $pdo->exec(
                'DROP DATABASE IF EXISTS '
                . $this->quoteIdentifier($database)
            );
        } catch (Throwable) {
        }

        try {
            $pdo->exec(
                'DROP USER IF EXISTS '
                . $this->quoteAccount(
                    $pdo,
                    $username,
                    $allowedHost,
                )
            );
        } catch (Throwable) {
        }
    }

    private function enforceCellLimit(
        Cell $cell,
    ): void {
        $limit = (int) (
            $cell->database_limit
            ?? 0
        );

        if ($limit <= 0) {
            throw new RuntimeException(
                'This Cell does not have a database allowance.'
            );
        }

        $count = CellDatabase::query()
            ->where('cell_id', $cell->id)
            ->count();

        if ($count >= $limit) {
            throw new RuntimeException(
                "This Cell has reached its database limit of {$limit}."
            );
        }
    }

    private function enforceHostLimit(
        DatabaseHost $host,
    ): void {
        $limit = $host->max_databases;

        if ($limit === null) {
            return;
        }

        if ($host->databases()->count() >= $limit) {
            throw new RuntimeException(
                'The selected database host is at capacity.'
            );
        }
    }

    private function databaseName(
        Cell $cell,
        string $label,
    ): string {
        $prefix = 'hive_'
            . substr(
                str_replace(
                    '-',
                    '',
                    (string) $cell->id,
                ),
                0,
                8
            )
            . '_';

        $base = $this->normaliseName(
            $label,
            'database',
        );

        $suffix = '_'
            . strtolower(
                Str::random(6)
            );

        return substr(
            $prefix . $base,
            0,
            self::DATABASE_NAME_MAX_LENGTH
            - strlen($suffix),
        ) . $suffix;
    }

    private function databaseUsername(
        Cell $cell,
        string $label,
    ): string {
        $prefix = 'hive_'
            . substr(
                str_replace(
                    '-',
                    '',
                    (string) $cell->id,
                ),
                0,
                6
            )
            . '_';

        $base = $this->normaliseName(
            $label,
            'db',
        );

        $suffix = '_'
            . strtolower(
                Str::random(4)
            );

        return substr(
            $prefix . $base,
            0,
            self::USERNAME_MAX_LENGTH
            - strlen($suffix),
        ) . $suffix;
    }

    private function normaliseName(
        string $value,
        string $fallback,
    ): string {
        $value = strtolower(
            trim($value)
        );

        $value = preg_replace(
            '/[^a-z0-9_]+/',
            '_',
            $value
        ) ?? '';

        $value = trim(
            $value,
            '_'
        );

        return $value !== ''
            ? $value
            : $fallback;
    }

    private function quoteIdentifier(
        string $identifier,
    ): string {
        if (
            $identifier === ''
            || strlen($identifier)
                > self::DATABASE_NAME_MAX_LENGTH
            || preg_match(
                '/^[A-Za-z0-9_]+$/',
                $identifier
            ) !== 1
        ) {
            throw new RuntimeException(
                'The generated database identifier is invalid.'
            );
        }

        return '`'
            . str_replace(
                '`',
                '``',
                $identifier
            )
            . '`';
    }

    private function quoteAccount(
        PDO $pdo,
        string $username,
        string $host,
    ): string {
        return $pdo->quote($username)
            . '@'
            . $pdo->quote($host);
    }

    private function pdo(
        DatabaseHost $host,
    ): PDO {
        if ($host->driver !== 'mysql') {
            throw new RuntimeException(
                "Unsupported database driver: {$host->driver}"
            );
        }

        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;charset=utf8mb4',
                $host->connectionHost(),
                $host->port,
            ),
            $host->username,
            $host->password,
            [
                PDO::ATTR_ERRMODE =>
                    PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE =>
                    PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ],
        );
    }
}