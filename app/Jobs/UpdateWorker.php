<?php

namespace App\Jobs;

use App\Models\WorkerUpdate;
use App\Services\Node\NodeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class UpdateWorker implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $workerUpdateId,
    ) {
    }

    public function handle(NodeClient $nodes): void
    {
        $update = WorkerUpdate::query()
            ->with('node')
            ->find($this->workerUpdateId);

        if (! $update) {
            return;
        }

        if (! $update->isPending()) {
            return;
        }

        $node = $update->node;

        if (! $node) {
            $this->markFailed(
                $update,
                'The Worker node no longer exists.'
            );

            return;
        }

        /*
         * If a retry runs after the Worker has already completed the
         * update, don't send another update request.
         */
        if (
            $this->normaliseVersion($node->worker_version)
            === $this->normaliseVersion($update->target_version)
        ) {
            $this->markComplete($update);

            return;
        }

        $update->forceFill([
            'status' => WorkerUpdate::STATUS_DISPATCHING,
            'started_at' => $update->started_at ?? now(),
            'error' => null,
            'failed_at' => null,
        ])->save();

        /*
         * Record the dispatch time before making the HTTP request.
         *
         * The Worker may accept the request and restart before the
         * HTTP response reaches HivePanel. In that case cURL can report
         * an empty reply even though the update is actually underway.
         */
        $update->forceFill([
            'dispatched_at' => now(),
        ])->save();

        try {
            $nodes->requestWorkerUpdate(
                $node,
                'v'.$this->normaliseVersion(
                    $update->target_version
                ),
            );

            $update->forceFill([
                'status' => WorkerUpdate::STATUS_RESTARTING,
                'error' => null,
                'failed_at' => null,
            ])->save();
        } catch (Throwable $exception) {
            /*
             * Once the request has reached the dispatch stage, a
             * transport error is ambiguous.
             *
             * The Worker may have accepted the update and restarted
             * before it could finish the HTTP response. Leave the
             * update in RESTARTING and let the heartbeat reconcile the
             * actual result.
             */
            $update->forceFill([
                'status' => WorkerUpdate::STATUS_RESTARTING,
                'error' => null,
                'failed_at' => null,
            ])->save();

            return;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $update = WorkerUpdate::query()
            ->with('node')
            ->find($this->workerUpdateId);

        if (! $update) {
            return;
        }

        /*
         * A heartbeat may already have confirmed the target version
         * by the time Laravel calls failed().
         */
        if (
            $update->node
            && $this->normaliseVersion(
                $update->node->worker_version
            ) === $this->normaliseVersion(
                $update->target_version
            )
        ) {
            $this->markComplete($update);

            return;
        }

        /*
         * If the update reached the Worker, don't overwrite
         * RESTARTING with FAILED just because the HTTP connection
         * disappeared. Heartbeat reconciliation owns the final result.
         */
        if (
            $update->status
            === WorkerUpdate::STATUS_RESTARTING
        ) {
            return;
        }

        $this->markFailed(
            $update,
            $exception?->getMessage()
                ?? 'The Worker update request failed.'
        );
    }

    private function markComplete(
        WorkerUpdate $update,
    ): void {
        $update->forceFill([
            'status' => WorkerUpdate::STATUS_COMPLETE,
            'error' => null,
            'completed_at' => now(),
            'failed_at' => null,
        ])->save();
    }

    private function markFailed(
        WorkerUpdate $update,
        string $message,
    ): void {
        $update->forceFill([
            'status' => WorkerUpdate::STATUS_FAILED,
            'error' => mb_substr(
                $message,
                0,
                10000
            ),
            'failed_at' => now(),
        ])->save();
    }

    private function normaliseVersion(
        ?string $version,
    ): ?string {
        if (! is_string($version)) {
            return null;
        }

        $version = trim($version);

        if ($version === '') {
            return null;
        }

        return ltrim($version, 'vV');
    }
}