<?php

namespace App\Http\Controllers\Cells;

use App\Models\Cell;
use App\Models\CellDomain;
use App\Services\Node\CellNodeClient;
use App\Services\Node\WebProxyNodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CellDomainController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, WebProxyNodeClient $proxy)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        $settings = null;
        $error = '';
        try {
            $settings = $this->state($cell, $proxy);
        } catch (ValidationException $e) {
            $error = $e->errors()['domains'][0];
        }
        $cell->load('allocation', 'allocations');
        $desired = data_get($cell->metadata, 'desired_allocation_ids', $cell->allocations->pluck('id')->all());

        return Inertia::render('Cells/Domains', ['cell' => $this->frontendCell($cell, $cells), 'proxy' => $settings, 'connectionError' => $error, 'allocations' => $cell->allocations->whereIn('id', $desired)->map(fn ($a) => ['id' => $a->id, 'port' => $a->port, 'alias' => $a->alias])->values(), 'canEdit' => $cell->userCan(request()->user(), 'domains.update')]);
    }

    public function status(string $id, WebProxyNodeClient $proxy)
    {
        $cell = $this->panelCellOrFail($id);
        try {
            return response()->json($this->state($cell, $proxy));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->errors()['domains'][0]], 503);
        }
    }

    private function state(Cell $cell, WebProxyNodeClient $proxy): array
    {
        $state = $proxy->request($cell->node, 'GET', '/cells/'.$cell->daemon_id.'/domains');
        $records = CellDomain::where('cell_id', $cell->id)->get()->keyBy('id');
        $routes = collect($state['routes'] ?? [])->keyBy('id');
        $state['routes'] = $records->map(function ($record) use ($routes) {
            $route = $routes->get($record->id);

            return array_merge($record->toArray(), $route ?? ['verified' => false, 'certificate_state' => 'none', 'verification_token' => '', 'error' => 'This route is missing on the Worker. Save it again to restore it.']);
        })->values()->all();

        return $state;
    }

    public function save(string $id, Request $request, WebProxyNodeClient $proxy)
    {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);
        $domain = strtolower(rtrim(trim((string) $request->input('domain')), '.'));
        $request->merge(['domain' => $domain]);
        $data = $request->validate(['id' => ['nullable', 'uuid'], 'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', Rule::unique('cell_domains', 'domain')->ignore($request->input('id'))], 'allocation_id' => ['required', 'uuid'], 'protocol' => ['required', Rule::in(['http', 'tcp', 'udp'])], 'public_port' => ['required', 'integer', 'min:0', 'max:65535'], 'https' => ['required', 'boolean'], 'redirect' => ['required', 'boolean'], 'max_body_mb' => ['required', 'integer', 'min:1', 'max:128']]);
        DB::transaction(function () use ($cell, $data, $proxy) {
            $current = Cell::whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $allocation = $current->allocations()->whereKey($data['allocation_id'])->firstOrFail();
            $desired = data_get($current->metadata, 'desired_allocation_ids', $current->allocations()->pluck('id')->all());
            if (! in_array($allocation->id, $desired)) {
                throw ValidationException::withMessages(['allocation_id' => 'Choose an allocation currently assigned to this Cell.']);
            }
            $record = empty($data['id']) ? new CellDomain(['id' => (string) Str::uuid(), 'cell_id' => $cell->id, 'node_id' => $cell->node_id]) : CellDomain::where('cell_id', $cell->id)->whereKey($data['id'])->lockForUpdate()->firstOrFail();
            if ($record->exists && $record->domain !== $data['domain']) {
                throw ValidationException::withMessages(['domain' => 'Remove the old domain and create a new route to change its hostname.']);
            }
            if (! $record->exists && CellDomain::where('cell_id', $cell->id)->count() >= 50) {
                throw ValidationException::withMessages(['domain' => 'This Cell already has 50 domain routes.']);
            }
            $data['id'] = $record->id;
            $record->fill($data);
            $record->save();
            $proxy->request($cell->node, 'POST', '/cells/'.$cell->daemon_id.'/domains', ['id' => $record->id, 'domain' => $record->domain, 'port' => $allocation->port, 'protocol' => $record->protocol, 'public_port' => $record->public_port, 'https' => $record->https, 'redirect' => $record->redirect, 'max_body_mb' => $record->max_body_mb]);
        });

        return back()->with('success', 'Domain route saved. Add its DNS records, then verify ownership.');
    }

    private function record(Cell $cell, string $domain): CellDomain
    {
        return CellDomain::where('cell_id', $cell->id)->whereKey($domain)->firstOrFail();
    }

    public function verify(string $id, string $domain, WebProxyNodeClient $proxy)
    {
        $cell = $this->panelCellOrFail($id);
        $record = $this->record($cell, $domain);
        $proxy->request($cell->node, 'POST', '/cells/'.$cell->daemon_id.'/domains/'.$record->id.'/verify');

        return back()->with('success', 'Domain verified. Requested HTTPS certificates are issued in the background.');
    }

    public function certificate(string $id, string $domain, WebProxyNodeClient $proxy)
    {
        $cell = $this->panelCellOrFail($id);
        $record = $this->record($cell, $domain);
        $proxy->request($cell->node, 'POST', '/cells/'.$cell->daemon_id.'/domains/'.$record->id.'/certificate');

        return back()->with('success', 'Certificate request started.');
    }

    public function destroy(string $id, string $domain, WebProxyNodeClient $proxy)
    {
        $cell = $this->panelCellOrFail($id);
        $record = $this->record($cell, $domain);
        $proxy->request($cell->node, 'DELETE', '/cells/'.$cell->daemon_id.'/domains/'.$record->id);
        $record->delete();

        return back()->with('success', 'Domain route removed.');
    }

    public function website(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        abort_unless(($cell->comb_data['category'] ?? '') === 'web', 404);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        return Inertia::render('Cells/Website', ['cell' => $this->frontendCell($cell, $cells)]);
    }
}
