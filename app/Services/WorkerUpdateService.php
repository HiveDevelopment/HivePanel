<?php

namespace App\Services;

use App\Jobs\UpdateWorker;
use App\Models\Node;
use App\Models\WorkerUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkerUpdateService
{
    public function __construct(
        private readonly WorkerReleaseService $releases,
    ) {
    }

    public function queue(
        Node $node,
        ?string $requestedBy = null,
    ): WorkerUpdate {
        $latestVersion = $this->releases->latestVersion();

        if ($latestVersion === null) {
            throw new RuntimeException(
                'The latest HiveWorker version could not be determined.'
            );
        }

        if (! $this->isOnline($node)) {
            throw new RuntimeException(
                "{$node->name} is currently offline."
            );
        }

        if (! $node->worker_version) {
            throw new RuntimeException(
                "{$node->name} has not reported a Worker version."
            );
        }

        if (! $this->releases->isOutdated(
            $node->worker_version,
            $latestVersion,
        )) {
            throw new RuntimeException(
                "{$node->name} is already up to date."
            );
        }

        $existing = WorkerUpdate::query()
            ->where('node_id', $node->id)
            ->whereIn('status', [
                WorkerUpdate::STATUS_QUEUED,
                WorkerUpdate::STATUS_DISPATCHING,
                WorkerUpdate::STATUS_RESTARTING,
            ])
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $update = DB::transaction(function () use (
            $node,
            $requestedBy,
            $latestVersion,
        ): WorkerUpdate {
            return WorkerUpdate::query()->create([
                'node_id' => $node->id,
                'requested_by' => $requestedBy,
                'from_version' => $this->releases
                    ->normaliseVersion($node->worker_version),
                'target_version' => $latestVersion,
                'status' => WorkerUpdate::STATUS_QUEUED,
            ]);
        });

        UpdateWorker::dispatch($update->id)
            ->onQueue('worker-updates');

        return $update;
    }

    public function queueMany(
        Collection $nodes,
        ?string $requestedBy = null,
    ): Collection {
        return $nodes
            ->map(function (Node $node) use ($requestedBy) {
                try {
                    return $this->queue($node, $requestedBy);
                } catch (RuntimeException) {
                    return null;
                }
            })
            ->filter()
            ->values();
    }

    public function isOnline(Node $node): bool
    {
        if (! $node->last_seen_at) {
            return false;
        }

        return $node->last_seen_at->gte(
            now()->subMinutes(2)
        );
    }
}