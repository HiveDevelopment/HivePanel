<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Models\Cell;
use App\Models\Node;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellController extends CellBaseController
{
    public function index(\App\Services\Cells\CellDashboardService $dashboard)
    {
        return Inertia::render('Dashboard', [
            'cells' => fn () => $dashboard->visibleCells(request()->user()),
            'combs' => [],
        ]);
    }

    public function show(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);

        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Show', [
            'cell' => $workerCell,
            'stats' => [
                'cpu' => 0,
                'memory_mb' => 0,
                'disk_bytes' => 0,
                'uptime_sec' => 0,
                'network_rx_bytes' => 0,
                'network_tx_bytes' => 0,
            ],
        ]);
    }
}