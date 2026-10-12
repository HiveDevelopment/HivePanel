<?php

namespace App\Services\Cells;

use App\Enums\CellInstallStatus;
use App\Models\Cell;
use App\Models\User;
use App\Services\Node\CellNodeClient;
use Illuminate\Support\Collection;

class CellDashboardService
{
    public function __construct(private CellNodeClient $cells)
    {
    }

    public function visibleCells(User $user, bool $orderByName = false): Collection
    {
        $query = Cell::query()->visibleTo($user)->with('node');

        if ($orderByName) {
            $query->orderBy('name');
        } else {
            $query->latest();
        }

        return $query->get()->map(fn (Cell $cell) => $this->frontendCell($cell, $this->cells))->values();
    }

    public function frontendCell(
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
            return $this->mergePanelCell(
                $cell,
                [
                    'status' => 'unknown',
                    'error' => true,
                    'message' => $workerCell['message']
                        ?? 'Node unavailable',
                ],
            );
        }

        return $this->mergePanelCell(
            $cell,
            $workerCell,
        );
    }

    public function mergePanelCell(
        Cell $cell,
        array $workerCell,
    ): array {
        return [
            ...$workerCell,

            'id' => $cell->id,
            'daemon_id' => $cell->daemon_id,
            'node_id' => $cell->node_id,

            'name' => $workerCell['name']
                ?? $cell->name,

            'comb_data' => (array) (\App\Models\Comb::find(data_get($cell->metadata, 'comb_id'))?->data ?? $workerCell['comb_data'] ?? data_get($cell->metadata, 'comb_data', [])),

            'comb' => $workerCell['comb']
                ?? $cell->comb,

            'install_status' => $cell->install_status instanceof CellInstallStatus
                ? $cell->install_status->value
                : $cell->install_status,

            'install_status_label' => $cell->install_status instanceof CellInstallStatus
                ? $cell->install_status->label()
                : ucfirst(
                    (string) $cell->install_status,
                ),

            'install_failure_reason' => $cell->install_failure_reason,

            'installed_at' => $cell->installed_at
                ?->toIso8601String(),

            'node' => [
                'id' => $cell->node?->id,
                'name' => $cell->node?->name,
                'location' => $cell->node?->location,
            ],
        ];
    }

}
