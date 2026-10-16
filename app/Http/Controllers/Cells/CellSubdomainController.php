<?php

namespace App\Http\Controllers\Cells;

use App\Jobs\ProvisionCellSubdomainJob;
use App\Models\CellSubdomain;
use App\Models\SubdomainZone;
use App\Services\Cells\CellToolsPolicy;
use App\Services\Dns\CellSubdomainService;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellSubdomainController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, CellToolsPolicy $policy)
    {
        $cell = $this->panelCellOrFail($id);

        return Inertia::render('Cells/Subdomains', ['cell' => $this->frontendCell($cell, $cells), 'zones' => SubdomainZone::where('enabled', true)->orderBy('domain')->get(['id', 'domain']), 'subdomains' => CellSubdomain::where('cell_id', $cell->id)->get()->makeHidden('records'), 'allocations' => $cell->allocations()->get(['id', 'port', 'alias']), 'limit' => $policy->limit($cell, 'subdomain_limit'), 'canEdit' => $cell->userCan(request()->user(), 'subdomains.update')]);
    }

    public function store(string $id, Request $request, CellSubdomainService $service)
    {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);
        $data = $request->validate(['zone_id' => ['required', 'uuid'], 'allocation_id' => ['required', 'uuid'], 'label' => ['required', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'], 'kind' => ['required', 'in:minecraft,web,service']]);
        $entry = $service->reserve($cell, $data);
        ProvisionCellSubdomainJob::dispatch($entry->id)->afterCommit();

        return back()->with('success', __('Subdomain reserved. DNS setup is running in the background.'));
    }

    public function retry(string $id, string $subdomain, CellSubdomainService $service)
    {
        $cell = $this->panelCellOrFail($id);
        $entry = CellSubdomain::where('cell_id', $cell->id)->whereKey($subdomain)->firstOrFail();
        $entry->update(['state' => 'pending', 'error' => null]);
        ProvisionCellSubdomainJob::dispatch($entry->id);

        return back()->with('success', __('Subdomain setup queued.'));
    }

    public function destroy(string $id, string $subdomain, CellSubdomainService $service)
    {
        $cell = $this->panelCellOrFail($id);
        $entry = CellSubdomain::where('cell_id', $cell->id)->whereKey($subdomain)->firstOrFail();
        $service->remove($entry);

        return back()->with('success', __('Subdomain and its managed DNS records removed.'));
    }
}
