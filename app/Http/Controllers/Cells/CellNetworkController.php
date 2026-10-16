<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Models\Cell;
use App\Models\CellDomain;
use App\Models\CellSubdomain;
use App\Models\NodeAllocation;
use App\Services\AuditLogger;
use App\Services\Cells\CellSyncService;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CellNetworkController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, CellSyncService $sync)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        try {
            $sync->inspect($cell);
        } catch (\Throwable $exception) {
            report($exception);
        }
        $cell = $cell->fresh(['node', 'allocations', 'allocation']);

        return Inertia::render('Cells/Network', [
            'cell' => $this->frontendCell($cell, $cells),
            'allocations' => $cell->allocations->map(fn ($allocation) => ['id' => $allocation->id, 'ip' => $allocation->ip, 'port' => $allocation->port, 'alias' => $allocation->alias, 'notes' => $allocation->notes]),
            'primaryId' => $cell->primary_allocation_id,
            'selectedIds' => data_get($cell->metadata, 'desired_allocation_ids', $cell->allocations->pluck('id')->all()),
            'available' => NodeAllocation::where('node_id', $cell->node_id)->whereNull('cell_id')->where('is_reserved', false)->orderBy('port')->get(['id', 'ip', 'port', 'alias']),
            'limit' => (int) (data_get($cell->metadata, 'feature_limits.allocation_limit') ?? max(1, $cell->allocations->count())),
            'canEdit' => $cell->userCan(request()->user(), 'network.update'),
        ]);
    }

    public function updateNotes(string $id, string $allocation, Request $request)
    {
        $cell = $this->panelCellOrFail($id);
        $data = $request->validate(['notes' => ['present', 'nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($cell, $allocation, $data) {
            $record = NodeAllocation::whereKey($allocation)->where('node_id', $cell->node_id)->where('cell_id', $cell->id)->lockForUpdate()->firstOrFail();
            $record->update(['notes' => $data['notes'] ?? '']);
        });
        app(AuditLogger::class)->log(AuditEvent::SETTINGS_UPDATED, $cell, 'Allocation notes updated.', ['allocation_id' => $allocation]);

        return back()->with('success', __('Allocation notes saved.'));
    }

    public function update(string $id, Request $request, CellNodeClient $cells, CellSyncService $sync)
    {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);
        $this->abortIfLocked($cell, $cells);
        $data = $request->validate(['allocation_ids' => ['required', 'array', 'min:1', 'max:100'], 'allocation_ids.*' => ['required', 'string', 'distinct'], 'primary_id' => ['required', 'string']]);
        DB::transaction(function () use ($cell, $data) {
            $current = Cell::whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $current->load('allocations');
            $limit = (int) (data_get($current->metadata, 'feature_limits.allocation_limit') ?? max(1, $current->allocations->count()));
            if (count($data['allocation_ids']) > max(1, $limit) || ! in_array($data['primary_id'], $data['allocation_ids'], true)) {
                throw ValidationException::withMessages(['allocation_ids' => __('Choose a primary allocation and stay within the allocation limit.')]);
            }
            $allocations = NodeAllocation::where('node_id', $current->node_id)->whereIn('id', $data['allocation_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($allocations->count() !== count($data['allocation_ids']) || $allocations->contains(fn ($allocation) => ($allocation->cell_id && $allocation->cell_id !== $current->id) || ($allocation->is_reserved && $allocation->cell_id !== $current->id))) {
                throw ValidationException::withMessages(['allocation_ids' => __('One or more allocations are unavailable on this node.')]);
            }
            if (CellDomain::where('cell_id', $current->id)->whereNotIn('allocation_id', $data['allocation_ids'])->exists() || CellSubdomain::where('cell_id', $current->id)->whereNotIn('allocation_id', $data['allocation_ids'])->exists()) {
                throw ValidationException::withMessages(['allocation_ids' => __('Remove or retarget domain and proxy routes before releasing their allocation.')]);
            }
            foreach ($allocations as $allocation) {
                $allocation->update(['cell_id' => $current->id]);
            }
            $primary = $allocations->firstWhere('id', $data['primary_id']);
            $metadata = (array) $current->metadata;
            $metadata['desired_allocation_ids'] = $data['allocation_ids'];
            $metadata['variables']['server_ip'] = $primary->ip;
            $metadata['variables']['server_port'] = (string) $primary->port;
            $current->forceFill(['primary_allocation_id' => $primary->id, 'metadata' => $metadata])->save();
            $current->invalidateWorkerSync();
            // Old reservations remain held until the stopped runtime or Worker confirms the new definition.
        });
        app(AuditLogger::class)->log(AuditEvent::SETTINGS_UPDATED, $cell, 'Network allocations updated.', ['allocation_ids' => $data['allocation_ids'], 'primary_id' => $data['primary_id']]);
        try {
            $sync->repair($cell->fresh(['node', 'allocation', 'allocations']));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['allocation_ids' => __('Saved in the panel, but the Worker could not apply the change. Retry Save when the node is reachable.')]);
        }

        return back()->with('success', __('Network settings saved. Running Cells apply the ports on their next start.'));
    }
}
