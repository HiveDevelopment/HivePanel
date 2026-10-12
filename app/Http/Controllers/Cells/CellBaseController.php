<?php

namespace App\Http\Controllers\Cells;

use App\Enums\CellInstallStatus;
use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Services\Node\CellNodeClient;
use Inertia\Inertia;

abstract class CellBaseController extends Controller
{
    protected function panelCellOrFail(string $id): Cell
    {
        return Cell::query()
            ->with('node')
            ->whereKey($id)
            ->where(function ($query) {
                $query
                    ->where('owner_id', auth()->id())
                    ->orWhereHas(
                        'users',
                        fn ($query) => $query->where(
                            'user_id',
                            auth()->id(),
                        ),
                    );
            })
            ->firstOrFail();
    }

    protected function getCellOrFail(
        Cell $cell,
        CellNodeClient $cells,
    ): array {
        try {
            $workerCell = $cells->cell($cell);
        } catch (\Throwable $exception) {
            report($exception);
            $workerCell = ['error' => true, 'message' => 'Node status is unavailable.'];
        }

        if (($workerCell['error'] ?? false) === true) {
            abort(
                502,
                $workerCell['message']
                    ?? 'Node unavailable',
            );
        }

        return $this->mergePanelCell(
            $cell,
            $workerCell,
        );
    }

    protected function frontendCell(
        Cell $cell,
        CellNodeClient $cells,
    ): array {
        return app(\App\Services\Cells\CellDashboardService::class)->frontendCell($cell, $cells);
    }

    protected function mergePanelCell(
        Cell $cell,
        array $workerCell,
    ): array {
        return app(\App\Services\Cells\CellDashboardService::class)->mergePanelCell($cell, $workerCell);
    }

    protected function installationPageIfNeeded(
        Cell $cell,
    ) {
        $status = $cell->install_status;

        if (is_string($status)) {
            $status = CellInstallStatus::tryFrom($status);
        }

        if ($status === CellInstallStatus::INSTALLED) {
            return null;
        }

        return Inertia::render('Cells/Installing', [
            'cell' => $this->installationCellData($cell),
        ])->toResponse(request())->setStatusCode(
            $status === CellInstallStatus::FAILED
                ? 500
                : 202,
        );
    }

    protected function abortUnlessInstalled(
        Cell $cell,
    ): void {
        $status = $cell->install_status;

        if (is_string($status)) {
            $status = CellInstallStatus::tryFrom($status);
        }

        abort_unless(
            $status === CellInstallStatus::INSTALLED,
            409,
            match ($status) {
                CellInstallStatus::PENDING =>
                    'This cell is waiting to be installed.',

                CellInstallStatus::INSTALLING =>
                    'This cell is currently being installed.',

                CellInstallStatus::FAILED =>
                    $cell->install_failure_reason
                        ?: 'This cell failed to install.',

                default =>
                    'This cell is not installed.',
            },
        );
    }

    protected function installationCellData(
        Cell $cell,
    ): array {
        $status = $cell->install_status;

        if (is_string($status)) {
            $status = CellInstallStatus::tryFrom($status);
        }

        return [
            'id' => $cell->id,
            'name' => $cell->name,
            'daemon_id' => $cell->daemon_id,
            'node_id' => $cell->node_id,

            'install_status' => $status?->value
                ?? 'pending',

            'install_status_label' => $status?->label()
                ?? 'Pending',

            'install_failure_reason' =>
                $cell->install_failure_reason,

            'installed_at' => $cell->installed_at
                ?->toIso8601String(),

            'created_at' => $cell->created_at
                ?->toIso8601String(),

            'node' => [
                'id' => $cell->node?->id,
                'name' => $cell->node?->name,
                'location' => $cell->node?->location,
            ],
        ];
    }

    protected function isLocked(array $cell): bool
    {
        return (
            $cell['lock']['locked']
            ?? false
        ) === true;
    }

    protected function lockedPage(array $cell)
    {
        return Inertia::render(
            'Cells/Locked',
            [
                'cell' => $cell,
            ],
        )
            ->toResponse(request())
            ->setStatusCode(423);
    }

    protected function abortIfLocked(
        Cell $cell,
        CellNodeClient $cells,
    ): void {
        $workerCell = $this->getCellOrFail(
            $cell,
            $cells,
        );

        if ($this->isLocked($workerCell)) {
            abort(
                423,
                $workerCell['lock']['message']
                    ?? 'Server is locked.',
            );
        }
    }
}