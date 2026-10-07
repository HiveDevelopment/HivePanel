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

        if (! $update || ! $update->isPending()) {
            return;
        }

        $node = $update->node;

        if (! $node) {
            $this->markFailed($update, 'The Worker node no longer exists.');

            return;
        }

        if ($this->versionsMatch($node->worker_version, $update->target_version)) {
            $this->markComplete($update);

            return;
        }

        $update->forceFill([
            'status' => WorkerUpdate::STATUS_DISPATCHING,
            'started_at' => $update->started_at ?? now(),
            'dispatched_at' => null,
            'error' => null,
            'failed_at' => null,
        ])->save();

        try {
            $status = $nodes->requestWorkerUpdate(
                $node,
                'v'.$this->normaliseVersion($update->target_version),
            );
        } catch (Throwable $exception) {
            /*
             * An empty/reset response can happen after the Worker accepted
             * the request and began shutting down. Keep only those narrowly
             * defined transport failures ambiguous. DNS failures, refused
             * connections, timeouts and HTTP errors are real dispatch
             * failures and must be shown to the administrator.
             */
            if ($this->isAmbiguousTransportFailure($exception)) {
                $update->forceFill([
                    'status' => WorkerUpdate::STATUS_RESTARTING,
                    'dispatched_at' => now(),
                    'error' => null,
                    'failed_at' => null,
                ])->save();

                return;
            }

            $this->markFailed($update, $exception->getMessage());

            return;
        }

        $update->forceFill([
            'status' => $this->panelStatusForWorkerState($status['state'] ?? null),
            'dispatched_at' => now(),
            'error' => null,
            'failed_at' => null,
        ])->save();

        MonitorWorkerUpdate::dispatch($update->id)
            ->delay(now()->addSeconds(5))
            ->onQueue('worker-updates');
    }

    public function failed(?Throwable $exception): void
    {
        $update = WorkerUpdate::query()
            ->with('node')
            ->find($this->workerUpdateId);

        if (! $update) {
            return;
        }

        if (
            $update->node
            && $this->versionsMatch(
                $update->node->worker_version,
                $update->target_version,
            )
        ) {
            $this->markComplete($update);

            return;
        }

        if ($update->status === WorkerUpdate::STATUS_RESTARTING) {
            return;
        }

        $this->markFailed(
            $update,
            $exception?->getMessage() ?? 'The Worker update request failed.',
        );
    }

    private function panelStatusForWorkerState(mixed $state): string
    {
        return strtolower(trim((string) $state)) === 'restarting'
            ? WorkerUpdate::STATUS_RESTARTING
            : WorkerUpdate::STATUS_DISPATCHING;
    }

    private function isAmbiguousTransportFailure(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'curl error 52')
            || str_contains($message, 'empty reply from server')
            || str_contains($message, 'curl error 56')
            || str_contains($message, 'recv failure')
            || str_contains($message, 'connection reset by peer');
    }

    private function versionsMatch(?string $current, ?string $target): bool
    {
        $current = $this->normaliseVersion($current);
        $target = $this->normaliseVersion($target);

        return $current !== null && $target !== null && $current === $target;
    }

    private function markComplete(WorkerUpdate $update): void
    {
        $update->forceFill([
            'status' => WorkerUpdate::STATUS_COMPLETE,
            'error' => null,
            'completed_at' => now(),
            'failed_at' => null,
        ])->save();
    }

    private function markFailed(WorkerUpdate $update, string $message): void
    {
        $update->forceFill([
            'status' => WorkerUpdate::STATUS_FAILED,
            'error' => mb_substr($message, 0, 10000),
            'failed_at' => now(),
        ])->save();
    }

    private function normaliseVersion(?string $version): ?string
    {
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
