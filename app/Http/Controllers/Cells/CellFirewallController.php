<?php

namespace App\Http\Controllers\Cells;

use App\Services\Cells\CellToolsPolicy;
use App\Services\Node\CellNodeClient;
use App\Services\Node\CellToolsNodeClient;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CellFirewallController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, CellToolsNodeClient $tools, CellToolsPolicy $policy)
    {
        $cell = $this->panelCellOrFail($id);
        $state = null;
        $error = '';
        try {
            $state = $tools->request($cell, 'GET', '/firewall');
        } catch (ValidationException $e) {
            $error = $e->errors()['tools'][0];
        }

        return Inertia::render('Cells/Firewall', ['cell' => $this->frontendCell($cell, $cells), 'state' => $state, 'connectionError' => $error, 'allocations' => $cell->allocations()->get(['id', 'port', 'alias']), 'canEdit' => $cell->userCan(request()->user(), 'firewall.update') && $policy->settings()['firewall_enabled']]);
    }

    public function update(string $id, Request $request, CellToolsNodeClient $tools, CellToolsPolicy $policy)
    {
        $cell = $this->panelCellOrFail($id);
        abort_unless($policy->settings()['firewall_enabled'], 403);
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'default' => ['required', 'in:allow,deny'], 'rules' => ['present', 'array', 'max:100'], 'rules.*.source' => ['required', 'string', 'max:64'], 'rules.*.action' => ['required', 'in:allow,deny'], 'rules.*.port' => ['required', 'integer', 'min:0', 'max:65535'], 'rules.*.note' => ['nullable', 'string', 'max:200']]);
        $tools->request($cell, 'PUT', '/firewall', $data);

        return back()->with('success', __('Firewall rules applied.'));
    }
}
