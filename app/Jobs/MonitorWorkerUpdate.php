<?php

namespace App\Jobs;

use App\Models\WorkerUpdate;
use App\Services\Node\NodeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MonitorWorkerUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    private const POLL_SECONDS = 5;

    private const MAX_MONITOR_SECONDS = 300;

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

        try {
            $workerStatus = $nodes->workerUpdateStatus($node);
        } catch (Throwable $exception) {
            /*
             * Once the Worker has entered its restart phase it is expected
             * to be temporarily unreachable. Heartbeat reconciliation owns
             * completion/rollback detection from this point onward.
             */
            if ($update->status === WorkerUpdate::STATUS_RESTARTING) {
                return;
            }

            if ($this->monitorExpired($update)) {
                $this->markFailed(
                    $update,
                    'Unable to confirm Worker update status: '.$exception->getMessage(),
                );

                return;
            }

            $this->pollAgain($update);

            return;
        }

        $state = strtolower(trim((string) ($workerStatus['state'] ?? '')));
        $target = $this->normaliseVersion($workerStatus['target_version'] ?? null);
        $expected = $this->normaliseVersion($update->target_version);

        /* Ignore stale status left over from a different update. */
        if ($target !== null && $expected !== null && $target !== $expected) {
            if ($this->monitorExpired($update)) {
                $this->markFailed(
                    $update,
                    sprintf(
                        'Worker reported update state for v%s while HivePanel was waiting for v%s.',
                        $target,
                        $expected,
                    ),
                );

                return;
            }

            $this->pollAgain($update);

            return;
        }

        switch ($state) {
            case 'failed':
                $message = trim((string) ($workerStatus['message'] ?? 'Worker update failed.'));
                $error = trim((string) ($workerStatus['error'] ?? ''));

                $this->markFailed(
                    $update,
                    $error !== '' ? $message.' '.$error : $message,
                );

                return;

            case 'restarting':
                $update->forceFill([
                    'status' => WorkerUpdate::STATUS_RESTARTING,
                    'error' => null,
                    'failed_at' => null,
                ])->save();

                return;

            case 'complete':
                /*
                 * The old Worker can report complete when no restart was
                 * required. Normally the new process is confirmed by its
                 * heartbeat, so only complete here if versions already match.
                 */
                if ($this->versionsMatch(
                    $workerStatus['current_version'] ?? null,
                    $update->target_version,
                )) {
                    $this->markComplete($update);

                    return;
                }
                break;

            case 'queued':
            case 'checking':
            case 'downloading':
            case 'verifying':
            case 'staging':
                $update->forceFill([
                    'status' => WorkerUpdate::STATUS_DISPATCHING,
                    'error' => null,
                    'failed_at' => null,
                ])->save();
                break;

            case 'idle':
                /*
                 * Idle with the old version means the requested update is no
                 * longer running. This is safe to fail once the short initial
                 * race window has passed.
                 */
                if ($update->dispatched_at?->lte(now()->subSeconds(15))) {
                    $this->markFailed(
                        $update,
                        'The Worker is no longer running the requested update and is still reporting the previous version.',
                    );

                    return;
                }
                break;
        }

        if ($this->monitorExpired($update)) {
            $this->markFailed(
                $update,
                'The Worker update did not reach the restart stage within five minutes.',
            );

            return;
        }

        $this->pollAgain($update);
    }

    private function pollAgain(WorkerUpdate $update): void
    {
        self::dispatch($update->id)
            ->delay(now()->addSeconds(self::POLL_SECONDS))
            ->onQueue('worker-updates');
    }

    private function monitorExpired(WorkerUpdate $update): bool
    {
        $startedAt = $update->started_at ?? $update->created_at;

        return $startedAt !== null
            && $startedAt->lte(now()->subSeconds(self::MAX_MONITOR_SECONDS));
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
