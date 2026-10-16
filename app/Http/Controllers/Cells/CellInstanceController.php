<?php

namespace App\Http\Controllers\Cells;

use App\Jobs\InstallCellJob;
use App\Models\Cell;
use App\Models\Comb;
use App\Services\Cells\CellToolsPolicy;
use App\Services\Node\CellNodeClient;
use App\Services\Node\CellToolsNodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CellInstanceController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, CellToolsNodeClient $tools, CellToolsPolicy $policy)
    {
        $cell = $this->panelCellOrFail($id);
        $state = null;
        $error = '';
        try {
            $state = $tools->request($cell, 'GET', '/instances');
        } catch (ValidationException $e) {
            $error = $e->errors()['tools'][0];
        }

        return Inertia::render('Cells/Instances', ['cell' => $this->frontendCell($cell, $cells), 'state' => $state, 'connectionError' => $error, 'limit' => $policy->limit($cell, 'instance_limit'), 'activationPending' => data_get($cell->metadata, 'instance_activation.instance'), 'canEdit' => $cell->userCan(request()->user(), 'instances.update')]);
    }

    public function status(string $id, CellToolsNodeClient $tools)
    {
        return response()->json($tools->request($this->panelCellOrFail($id), 'GET', '/instances'));
    }

    public function store(string $id, Request $request, CellToolsNodeClient $tools, CellToolsPolicy $policy, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'empty' => ['required', 'boolean']]);
        $this->prepareLimit($cell, $policy, $cells);
        $state = $tools->request($cell, 'GET', '/instances');
        if (count($state['instances'] ?? []) >= $policy->limit($cell, 'instance_limit')) {
            throw ValidationException::withMessages(['name' => __('Instance limit reached.')]);
        }$tools->request($cell, 'POST', '/instances', $data);

        return back()->with('success', __('Saving instance. Progress is shown below; the Cell stays locked until the copy finishes.'));
    }

    public function activate(string $id, string $instance, CellToolsNodeClient $tools, Request $request)
    {
        $cell = $this->panelCellOrFail($id);
        $state = $tools->request($cell, 'GET', '/instances');
        abort_unless(collect($state['instances'] ?? [])->contains('id', $instance), 404);
        $token = DB::transaction(function () use ($cell, $instance) {
            $current = Cell::whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $m = (array) $current->metadata;
            $pending = $m['instance_activation'] ?? null;
            if ($pending && $pending['instance'] !== $instance) {
                throw ValidationException::withMessages(['tools' => __('Finish activating the previously selected instance first.')]);
            }$pending ??= ['instance' => $instance, 'token' => (string) Str::uuid()];
            $m['instance_activation'] = $pending;
            $current->forceFill(['metadata' => $m])->save();

            return $pending['token'];
        });
        $worker = $tools->request($cell, 'POST', '/instances/'.rawurlencode($instance).'/activate', [], ['X-Hive-Operation' => $token]);
        DB::transaction(function () use ($cell, $worker) {
            $current = Cell::whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $m = (array) $current->metadata;
            foreach (['comb_data', 'variables', 'docker', 'startup'] as $key) {
                $m[$key] = $worker[$key] ?? [];
            }$comb = Comb::where('external_id', $worker['comb'])->first();
            $m['comb_id'] = $comb?->id;
            unset($m['minecraft_content'],$m['content_install']);
            $current->forceFill(['comb' => $worker['comb'], 'metadata' => $m, 'install_status' => $worker['install_status'], 'install_failure_reason' => null])->save();
            $current->invalidateWorkerSync();
        });
        $tools->request($cell, 'POST', '/operations/end', ['token' => $token]);
        $current = $cell->fresh();
        $m = (array) $current->metadata;
        unset($m['instance_activation']);
        $current->forceFill(['metadata' => $m])->save();
        if (($worker['install_status'] ?? '') === 'pending') {
            InstallCellJob::dispatch($cell->id, false);
        }

        return redirect('/cells/'.$cell->id.($request->query('return_to') === 'files' ? '/files' : '/instances'))->with('success', __('Instance activated. Your allocations and resource limits are unchanged.'));
    }

    public function destroy(string $id, string $instance, CellToolsNodeClient $tools)
    {
        $cell = $this->panelCellOrFail($id);
        $tools->request($cell, 'DELETE', '/instances/'.rawurlencode($instance));

        return back()->with('success', __('Saved instance deleted.'));
    }

    private function prepareLimit(Cell $cell, CellToolsPolicy $policy, CellNodeClient $cells): void
    {
        $metadata = (array) $cell->metadata;
        $metadata['feature_limits']['instance_limit'] = $policy->limit($cell, 'instance_limit');
        $cell->forceFill(['metadata' => $metadata])->save();
        $cells->updateCellDefinition($cell);
    }
}
