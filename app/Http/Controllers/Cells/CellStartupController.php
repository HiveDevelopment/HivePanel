<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Models\Cell;
use App\Services\AuditLogger;
use App\Services\Cells\CellShutdownService;
use App\Services\Cells\CellSyncService;
use App\Services\Cells\CombImageService;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CellStartupController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, CellShutdownService $shutdown)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        $frontend = $this->frontendCell($cell, $cells);
        $schema = $shutdown->variableSchema((array) ($frontend['comb_data'] ?? []));

        return Inertia::render('Cells/Startup', ['cell' => $frontend, 'schema' => $schema, 'variables' => (array) data_get($cell->metadata, 'variables', []), 'images' => app(CombImageService::class)->options((array) ($frontend['comb_data'] ?? [])), 'selectedImage' => (string) data_get($cell->metadata, 'docker.image', data_get($frontend, 'comb_data.image', '')), 'canEdit' => $cell->userCan(request()->user(), 'startup.update')]);
    }

    public function update(string $id, Request $request, CellNodeClient $cells, CellShutdownService $shutdown, CellSyncService $sync)
    {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);
        $this->abortIfLocked($cell, $cells);
        $manifest = $cells->definitionPayload($cell)['comb_data'];
        $schema = $shutdown->variableSchema($manifest);
        $rules = ['image' => ['sometimes', 'string', 'max:251'], 'variables' => ['required', 'array']];
        $editable = [];
        foreach ($schema as $variable) {
            $name = (string) ($variable['name'] ?? '');
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) || in_array($name, ['server_ip', 'server_port'], true) || ($variable['user_editable'] ?? true) === false) {
                continue;
            }
            $editable[] = $name;
            $rules['variables.'.$name] = [! empty($variable['required']) ? 'required' : 'nullable', 'string', 'max:4096'];
            if (($variable['type'] ?? '') === 'number') {
                $rules['variables.'.$name][] = 'numeric';
            }
            if (($variable['type'] ?? '') === 'integer') {
                $rules['variables.'.$name][] = 'regex:/^-?[0-9]+$/';
            }
            if (($variable['type'] ?? '') === 'boolean') {
                $rules['variables.'.$name][] = Rule::in(['true', 'false', '0', '1']);
            }
        }
        if (is_array($request->input('variables')) && array_diff(array_keys($request->input('variables')), $editable)) {
            throw ValidationException::withMessages(['variables' => 'Only editable Comb variables may be changed.']);
        }
        $data = $request->validate($rules);
        $image = app(CombImageService::class)->choose($manifest, $data['image'] ?? null, data_get($cell->metadata, 'docker.image'));
        DB::transaction(function () use ($cell, $data, $image) {
            $current = Cell::query()->whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $metadata = (array) $current->metadata;
            $metadata['docker']['image'] = $image;
            foreach ($data['variables'] as $name => $value) {
                $metadata['variables'][$name] = (string) ($value ?? '');
            }
            $current->forceFill(['metadata' => $metadata])->save();
            $current->invalidateWorkerSync();
        });
        app(AuditLogger::class)->log(AuditEvent::SETTINGS_UPDATED, $cell, 'Startup variables updated.', ['fields' => array_keys($data['variables'])]);
        try {
            $sync->repair($cell->fresh(['node', 'allocation', 'allocations']));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['variables' => 'Saved in the panel, but the Worker could not apply the change. Retry Save when the node is reachable.']);
        }

        return back()->with('success', 'Startup variables saved. Running Cells apply them on their next start.');
    }
}
