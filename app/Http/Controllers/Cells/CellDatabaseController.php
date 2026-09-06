<?php

namespace App\Http\Controllers\Cells;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\CellDatabase;
use App\Services\Databases\DatabaseProvisioningService;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class CellDatabaseController extends Controller
{
    public function index(
        string $id,
        CellNodeClient $cells,
    ) {
        $cell = $this->panelCellOrFail(
            $id
        );

        $workerCell = $cells->cell($cell);

        if (
            ($workerCell['error'] ?? false)
            === true
        ) {
            $workerCell = [
                'status' => 'offline',
            ];
        }

        return Inertia::render(
            'Cells/Databases',
            [
                'cell' => [
                    ...$workerCell,
                    'id' => $cell->id,
                    'daemon_id' =>
                        $cell->daemon_id,
                    'name' => $cell->name,
                    'database_limit' =>
                        (int) $cell->database_limit,
                ],
                'databases' => $cell
                    ->databases()
                    ->with('host')
                    ->latest()
                    ->get()
                    ->map(
                        fn (CellDatabase $database) =>
                            $this->payload($database)
                    ),
            ]
        );
    }

    public function json(
        string $id,
    ) {
        $cell = $this->panelCellOrFail(
            $id
        );

        return response()->json([
            'databases' => $cell
                ->databases()
                ->with('host')
                ->latest()
                ->get()
                ->map(
                    fn (CellDatabase $database) =>
                        $this->payload($database)
                ),
            'limit' => (int) (
                $cell->database_limit
                ?? 0
            ),
        ]);
    }

    public function store(
        string $id,
        Request $request,
        DatabaseProvisioningService $databases,
    ) {
        $cell = $this->panelCellOrFail(
            $id
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:32',
            ],
            'allowed_host' => [
                'required',
                'string',
                'max:255',
            ],
        ]);


        try {
            $database = $databases->create($cell, $data['name'], $data['allowed_host']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' =>
                    $exception->getMessage()
                    ?: 'Unable to create database.',
            ], 422);
        }

        $database->load('host');

        return response()->json(
            $this->payload(
                $database,
                revealPassword: true,
            ),
            201
        );
    }

    public function credentials(
        string $id,
        CellDatabase $database,
    ) {
        $cell = $this->panelCellOrFail(
            $id
        );

        $this->ensureDatabaseBelongsToCell(
            $cell,
            $database
        );

        $database->load('host');

        return response()->json(
            $this->payload(
                $database,
                revealPassword: true,
            )
        );
    }

    public function resetPassword(
        string $id,
        CellDatabase $database,
        DatabaseProvisioningService $databases,
    ) {
        $cell = $this->panelCellOrFail(
            $id
        );

        $this->ensureDatabaseBelongsToCell(
            $cell,
            $database
        );

        try {
            $password =
                $databases->resetPassword(
                    $database
                );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' =>
                    $exception->getMessage()
                    ?: 'Unable to reset database password.',
            ], 422);
        }

        return response()->json([
            'password' => $password,
            'message' =>
                'Database password reset.',
        ]);
    }

    public function destroy(
        string $id,
        CellDatabase $database,
        DatabaseProvisioningService $databases,
    ) {
        $cell = $this->panelCellOrFail(
            $id
        );

        $this->ensureDatabaseBelongsToCell(
            $cell,
            $database
        );

        try {
            $databases->delete($database);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' =>
                    $exception->getMessage()
                    ?: 'Unable to delete database.',
            ], 422);
        }

        return response()->json([
            'message' =>
                'Database deleted.',
        ]);
    }

    private function panelCellOrFail(
        string $id,
    ): Cell {
        return Cell::query()
            ->with('node')
            ->whereKey($id)
            ->where(function ($query) {
                $query->where(
                    'owner_id',
                    auth()->id()
                )->orWhereHas(
                    'users',
                    fn ($query) =>
                        $query->where(
                            'user_id',
                            auth()->id()
                        )
                );
            })
            ->firstOrFail();
    }

    private function ensureDatabaseBelongsToCell(
        Cell $cell,
        CellDatabase $database,
    ): void {
        abort_unless(
            (string) $database->cell_id
                === (string) $cell->id,
            404
        );
    }

    private function payload(
        CellDatabase $database,
        bool $revealPassword = false,
    ): array {
        $host = $database->host;

        return [
            'id' => $database->id,
            'database_name' =>
                $database->database_name,
            'username' =>
                $database->username,
            'password' =>
                $revealPassword
                    ? $database->password
                    : null,
            'allowed_host' =>
                $database->allowed_host,
            'charset' =>
                $database->charset,
            'collation' =>
                $database->collation,
            'managed' =>
                $database->managed,
            'source' =>
                $database->source,
            'host' => $host ? [
                'id' => $host->id,
                'name' => $host->name,
                'driver' => $host->driver,
                'host' =>
                    $host->displayHost(),
                'port' =>
                    $host->displayPort(),
            ] : null,
            'created_at' =>
                $database->created_at
                    ?->toISOString(),
        ];
    }
}