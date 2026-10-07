<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\WorkerUpdate;
use App\Services\WorkerReleaseService;
use App\Services\WorkerUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminWorkerUpdateController extends Controller
{
    public function __construct(
        private readonly WorkerUpdateService $updates,
        private readonly WorkerReleaseService $releases,
    ) {
    }

    public function update(
        Request $request,
        Node $node,
    ): RedirectResponse {
        try {
            $this->updates->queue(
                $node,
                $request->user()?->id,
            );
        } catch (RuntimeException $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }

        return back()->with(
            'success',
            "HiveWorker update queued for {$node->name}."
        );
    }

    public function updateSelected(
        Request $request,
    ): RedirectResponse {
        $validated = $request->validate([
            'nodes' => ['required', 'array', 'min:1', 'max:250'],
            'nodes.*' => [
                'required',
                'uuid',
                'distinct',
                'exists:nodes,id',
            ],
        ]);

        $nodes = Node::query()
            ->whereIn('id', $validated['nodes'])
            ->get();

        $queued = $this->updates->queueMany(
            $nodes,
            $request->user()?->id,
        );

        if ($queued->isEmpty()) {
            return back()->with(
                'error',
                'None of the selected Workers are eligible for an update.'
            );
        }

        return back()->with(
            'success',
            sprintf(
                '%d Worker update%s queued.',
                $queued->count(),
                $queued->count() === 1 ? '' : 's',
            )
        );
    }

    public function updateOutdated(
        Request $request,
    ): RedirectResponse {
        $latestVersion = $this->releases->latestVersion();

        if ($latestVersion === null) {
            return back()->with(
                'error',
                'The latest HiveWorker version could not be determined.'
            );
        }

        /*
         * Version comparison is intentionally done in PHP.
         * SQL string comparison is unsafe for semantic versions
         * such as 1.10.0 vs 1.9.0.
         */
        $nodes = Node::query()
            ->whereNotNull('worker_version')
            ->whereNotNull('last_seen_at')
            ->where(
                'last_seen_at',
                '>=',
                now()->subMinutes(2),
            )
            ->get()
            ->filter(
                fn (Node $node) => $this->releases->isOutdated(
                    $node->worker_version,
                    $latestVersion,
                )
            )
            ->values();

        if ($nodes->isEmpty()) {
            return back()->with(
                'success',
                'All online Workers are already up to date.'
            );
        }

        $queued = $this->updates->queueMany(
            $nodes,
            $request->user()?->id,
        );

        return back()->with(
            'success',
            sprintf(
                '%d Worker update%s queued.',
                $queued->count(),
                $queued->count() === 1 ? '' : 's',
            )
        );
    }

    public function status(): JsonResponse
    {
        /*
        * Only consider the most recent update for each Worker.
        *
        * Historical failures remain in the database for audit/history,
        * but must not continue appearing as the Worker's current update
        * state after a newer update has completed successfully.
        */
        $latestUpdateIds = WorkerUpdate::query()
            ->selectRaw('MAX(id)')
            ->groupBy('node_id');

        $updates = WorkerUpdate::query()
            ->with('node:id,name,worker_version,last_seen_at')
            ->whereIn('id', $latestUpdateIds)
            ->whereIn('status', [
                WorkerUpdate::STATUS_QUEUED,
                WorkerUpdate::STATUS_DISPATCHING,
                WorkerUpdate::STATUS_RESTARTING,
                WorkerUpdate::STATUS_FAILED,
            ])
            ->latest('id')
            ->limit(250)
            ->get()
            ->map(fn (WorkerUpdate $update) => [
                'id' => $update->id,
                'node_id' => $update->node_id,
                'node_name' => $update->node?->name,
                'from_version' => $update->from_version,
                'target_version' => $update->target_version,
                'current_version' => $update->node?->worker_version,
                'status' => $update->status,
                'error' => $update->error,
                'started_at' => $update->started_at?->toISOString(),
                'dispatched_at' => $update->dispatched_at?->toISOString(),
                'completed_at' => $update->completed_at?->toISOString(),
                'failed_at' => $update->failed_at?->toISOString(),
            ])
            ->values();

        return response()->json([
            'updates' => $updates,
        ]);
    }
}