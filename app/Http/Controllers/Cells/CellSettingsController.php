<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellSettingsController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);

        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Settings', [
            'cell' => $workerCell,
            'shutdownCommandOverride' => (string) data_get($cell->metadata, 'variables.shutdown_command', ''),
        ]);
    }

    public function update(string $id, Request $request, CellNodeClient $cells, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);

        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'jarfile' => ['required', 'string', 'max:255'],
            'world_name' => ['required', 'string', 'max:255'],
        ]);

        $result = $cells->updateCellSettings($cell, $data);

        if ($cell->name !== $data['name']) {
            $cell->forceFill([
                'name' => $data['name'],
            ])->save();
        }

        $cell->invalidateWorkerSync();

        $audit->log(
            AuditEvent::SETTINGS_UPDATED,
            $cell,
            'Server settings were updated.',
            [
                'fields' => array_keys($data),
                'worker_sync_invalidated' => true,
            ]
        );

        return response()->json($result);
    }

    public function updateShutdown(string $id, Request $request, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        $data = $request->validate([
            'shutdown_command' => ['nullable', 'string', 'max:1000', 'not_regex:/[\r\n\x00]/'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($cell, $data): void {
            $locked = \App\Models\Cell::query()->whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $metadata = (array) $locked->metadata;
            $metadata['variables']['shutdown_command'] = trim((string) ($data['shutdown_command'] ?? ''));
            $locked->forceFill(['metadata' => $metadata])->save();
            $locked->invalidateWorkerSync();
        });

        $audit->log(AuditEvent::SETTINGS_UPDATED, $cell, 'Cell shutdown command override was updated.', ['fields' => ['shutdown_command']]);

        return response()->json(['message' => 'Shutdown command saved. It will be used for the next Stop or Restart.']);
    }

    public function utility(string $id, string $utility, CellNodeClient $cells, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);

        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        abort_unless(in_array($utility, [
            'reset-files',
            'reset-world',
            'repair-permissions',
            'clear-logs',
        ], true), 404);

        $result = $cells->runUtility($cell, $utility);

        $audit->log(
            AuditEvent::SETTINGS_UPDATED,
            $cell,
            "Utility \"{$utility}\" was run.",
            [
                'utility' => $utility,
            ]
        );

        return response()->json($result);
    }
}