<?php

namespace App\Services\Cells;

use App\Enums\CellInstallStatus;
use App\Models\Cell;
use App\Models\Comb;
use App\Models\User;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class CellDashboardService
{
    public function __construct(private CellNodeClient $cells) {}

    public function visibleCells(User $user, bool $orderByName = false): Collection
    {
        $query = Cell::query()->visibleTo($user)->with('node');

        if ($orderByName) {
            $query->orderBy('name');
        } else {
            $query->latest();
        }

        $visible = $query->get();
        $nodes = $visible->pluck('node')->filter()->unique('id');
        $responses = Http::pool(function ($pool) use ($nodes, $visible) {
            return $nodes->map(fn ($node) => $pool->as((string) $node->id)->withToken($node->api_token)->timeout(8)->get($node->baseUrl().'/cells/overview', ['ids' => $visible->where('node_id', $node->id)->pluck('daemon_id')->implode(',')]))->all();
        }, 8);
        $snapshots = [];
        foreach ($nodes as $node) {
            $response = $responses[(string) $node->id] ?? null;
            if ($response instanceof Response && $response->successful()) {
                $snapshots[(string) $node->id] = collect($response->json('cells', []))->keyBy('id');
            }
        }

        return $visible->map(function (Cell $cell) use ($snapshots) {
            $snapshot = $snapshots[(string) $cell->node_id][$cell->daemon_id] ?? null;
            if (! is_array($snapshot)) {
                return $this->mergePanelCell($cell, ['status' => 'unknown', 'stats' => null, 'error' => true]);
            }

            return $this->mergePanelCell($cell, $snapshot);
        })->values();
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
            'variables' => (array) data_get($cell->metadata, 'variables', $workerCell['variables'] ?? []),
            'docker' => (array) data_get($cell->metadata, 'docker', $workerCell['docker'] ?? []),
            'node_id' => $cell->node_id,

            'name' => $workerCell['name']
                ?? $cell->name,

            'comb_data' => app(MinecraftContentService::class)->manifest($cell, (array) (Comb::find(data_get($cell->metadata, 'comb_id'))?->data ?? $workerCell['comb_data'] ?? data_get($cell->metadata, 'comb_data', []))),

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
