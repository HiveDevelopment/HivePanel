<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DatabaseHost;
use App\Models\Node;
use App\Models\NodeDatabaseHost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminNodeDatabaseHostController extends Controller
{
    public function index(Node $node)
    {
        $node->load('databaseHostAssignments.databaseHost');

        return Inertia::render('Admin/Nodes/DatabaseHosts', [
            'node' => [
                'id' => $node->id,
                'name' => $node->name,
                'location' => $node->location,
            ],
            'assignments' => $node->databaseHostAssignments->sortBy(fn (NodeDatabaseHost $assignment) => [$assignment->is_primary ? 0 : 1, $assignment->priority])->values()->map(fn (NodeDatabaseHost $assignment) => $this->assignmentPayload($assignment)),
            'hosts' => DatabaseHost::query()->where('enabled', true)->orderBy('name')->get()->map(fn (DatabaseHost $host) => [
                'id' => $host->id,
                'name' => $host->name,
                'driver' => $host->driver,
                'host' => $host->displayHost(),
                'port' => $host->displayPort(),
                'max_databases' => $host->max_databases,
                'databases_count' => $host->databases()->count(),
            ]),
        ]);
    }

    public function store(Node $node, Request $request)
    {
        $data = $request->validate([
            'database_host_id' => ['required', 'uuid', 'exists:database_hosts,id'],
            'priority' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_primary' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($node, $data) {
            if ($data['is_primary']) {
                $node->databaseHostAssignments()->update(['is_primary' => false]);
            }

            NodeDatabaseHost::updateOrCreate([
                'node_id' => $node->id,
                'database_host_id' => $data['database_host_id'],
            ], [
                'priority' => $data['priority'],
                'is_primary' => $data['is_primary'],
                'enabled' => $data['enabled'],
            ]);
        });

        return back()->with('success', 'Database host assigned to Node.');
    }

    public function update(Node $node, NodeDatabaseHost $assignment, Request $request)
    {
        abort_unless((string) $assignment->node_id === (string) $node->id, 404);

        $data = $request->validate([
            'priority' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_primary' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($node, $assignment, $data) {
            if ($data['is_primary']) {
                $node->databaseHostAssignments()->whereKeyNot($assignment->id)->update(['is_primary' => false]);
            }

            $assignment->update($data);
        });

        return back()->with('success', 'Node database host updated.');
    }

    public function destroy(Node $node, NodeDatabaseHost $assignment)
    {
        abort_unless((string) $assignment->node_id === (string) $node->id, 404);

        $assignment->delete();

        return back()->with('success', 'Database host removed from Node.');
    }

    private function assignmentPayload(NodeDatabaseHost $assignment): array
    {
        $host = $assignment->databaseHost;

        return [
            'id' => $assignment->id,
            'database_host_id' => $assignment->database_host_id,
            'priority' => $assignment->priority,
            'is_primary' => $assignment->is_primary,
            'enabled' => $assignment->enabled,
            'host' => $host ? [
                'id' => $host->id,
                'name' => $host->name,
                'driver' => $host->driver,
                'host' => $host->displayHost(),
                'port' => $host->displayPort(),
                'max_databases' => $host->max_databases,
                'databases_count' => $host->databases()->count(),
            ] : null,
        ];
    }
}