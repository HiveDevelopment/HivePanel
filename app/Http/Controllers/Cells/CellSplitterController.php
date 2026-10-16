<?php

namespace App\Http\Controllers\Cells;

use App\Models\Cell;
use App\Models\CellSplit;
use App\Services\Cells\CellSplitterService;
use App\Services\Cells\CellToolsPolicy;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellSplitterController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, CellToolsPolicy $policy)
    {
        $cell = $this->panelCellOrFail($id);
        $policy->owner($cell, request()->user());
        $splits = CellSplit::where('parent_id', $cell->id)->get();
        $children = Cell::whereIn('id', $splits->pluck('child_id'))->get()->keyBy('id');

        return Inertia::render('Cells/Splitter', ['cell' => $this->frontendCell($cell, $cells), 'combs' => $policy->offered($cell)->map(fn ($comb) => ['id' => $comb->id, 'name' => $comb->name, 'data' => $comb->data]), 'limit' => $policy->limit($cell, 'split_limit'), 'splits' => $splits->map(fn ($split) => [...$split->toArray(), 'name' => $children->get($split->child_id)?->name]), 'allocations' => $cell->allocations()->where('id', '!=', $cell->primary_allocation_id)->get(['id', 'port', 'alias'])]);
    }

    public function store(string $id, Request $request, CellSplitterService $service)
    {
        $cell = $this->panelCellOrFail($id);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'comb_id' => ['required', 'uuid', 'exists:combs,id'], 'allocation_id' => ['required', 'uuid'], 'memory_mb' => ['required', 'integer', 'min:128'], 'disk_mb' => ['required', 'integer', 'min:128'], 'cpu_percent' => ['required', 'integer', 'min:1', 'max:1000'], 'overhead_memory_mb' => ['required', 'integer', 'min:0'], 'swap_mb' => ['required', 'integer', 'min:0'], 'variables' => ['nullable', 'array'], 'variables.*' => ['nullable', 'string', 'max:4096']]);
        $child = $service->create($cell, $request->user(), $data);

        return redirect('/cells/'.$child->id)->with('success', __('Split created. Installation is queued.'));
    }

    public function destroy(string $id, string $split, CellSplitterService $service)
    {
        $service->remove($this->panelCellOrFail($id), CellSplit::findOrFail($split), request()->user());

        return back()->with('success', __('Split deleted and its resource budget returned to the parent.'));
    }
}
