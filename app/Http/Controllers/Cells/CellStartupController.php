<?php

namespace App\Http\Controllers\Cells;

use App\Services\Node\CellNodeClient;
use App\Services\Cells\CellShutdownService;
use App\Services\Cells\CellSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CellStartupController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, CellShutdownService $shutdown)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) return $response;
        $frontend = $this->frontendCell($cell, $cells);
        $schema = $shutdown->variableSchema((array) ($frontend['comb_data'] ?? []));
        return Inertia::render('Cells/Startup', ['cell' => $frontend, 'schema' => $schema, 'variables' => (array) data_get($cell->metadata, 'variables', []), 'canEdit' => $cell->userCan(request()->user(), 'startup.update')]);
    }

    public function update(string $id, Request $request, CellNodeClient $cells, CellShutdownService $shutdown, CellSyncService $sync)
    {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);
        $this->abortIfLocked($cell, $cells);
        $manifest = $cells->definitionPayload($cell)['comb_data'];
        $schema = $shutdown->variableSchema($manifest);
        $rules = ['variables' => ['required', 'array']];
        $editable = [];
        foreach ($schema as $variable) {
            $name = (string) ($variable['name'] ?? '');
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) || in_array($name, ['server_ip', 'server_port'], true) || ($variable['user_editable'] ?? true) === false) continue;
            $editable[] = $name;
            $rules['variables.'.$name] = [!empty($variable['required']) ? 'required' : 'nullable', 'string', 'max:4096'];
            if (($variable['type'] ?? '') === 'number') $rules['variables.'.$name][] = 'numeric';
            if (($variable['type'] ?? '') === 'integer') $rules['variables.'.$name][] = 'regex:/^-?[0-9]+$/';
            if (($variable['type'] ?? '') === 'boolean') $rules['variables.'.$name][] = \Illuminate\Validation\Rule::in(['true', 'false', '0', '1']);
        }
        if (is_array($request->input('variables')) && array_diff(array_keys($request->input('variables')), $editable)) throw ValidationException::withMessages(['variables' => 'Only editable Comb variables may be changed.']);
        $data = $request->validate($rules);
        DB::transaction(function () use ($cell, $data, $editable) {
            $current = \App\Models\Cell::query()->whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $metadata = (array) $current->metadata;
            foreach ($data['variables'] as $name => $value) $metadata['variables'][$name] = (string) ($value ?? '');
            $current->forceFill(['metadata' => $metadata])->save();
            $current->invalidateWorkerSync();
        });
        app(\App\Services\AuditLogger::class)->log(\App\Enums\AuditEvent::SETTINGS_UPDATED, $cell, 'Startup variables updated.', ['fields' => array_keys($data['variables'])]);
        try { $sync->repair($cell->fresh(['node', 'allocation', 'allocations'])); }
        catch (\Throwable $exception) { report($exception); return back()->withErrors(['variables' => 'Saved in the panel, but the Worker could not apply the change. Retry Save when the node is reachable.']); }
        return back()->with('success', 'Startup variables saved. Running Cells apply them on their next start.');
    }
}
