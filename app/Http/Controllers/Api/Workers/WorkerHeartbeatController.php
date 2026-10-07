<?php

namespace App\Http\Controllers\Api\Workers;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\WorkerUpdate;
use Illuminate\Http\Request;

class WorkerHeartbeatController extends Controller
{
    /**
     * Process a heartbeat from an authenticated worker.
     */
    public function __invoke(Request $request)
    {
        /** @var Node|null $node */
        $node = $request->attributes->get('worker_node');

        abort_unless($node, 401, 'Unauthenticated.');

        $data = $request->validate([
            'version' => ['nullable', 'string', 'max:100'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'stats' => ['nullable', 'array'],
        ]);

        $stats = $data['stats'] ?? [];

        /*
         * Update the Worker heartbeat information first.
         *
         * This means the Node record always reflects the version from
         * the heartbeat before we attempt to reconcile Worker updates.
         */
        $node->update([
            'last_seen_at' => now(),
            'worker_version' => $data['version']
                ?? $node->worker_version,
            'worker_hostname' => $data['hostname']
                ?? $node->worker_hostname,
            'worker_platform' => $data['platform']
                ?? $node->worker_platform,
            'worker_ip' => $request->ip(),
        ]);

        $reportedVersion = $this->normaliseVersion(
            $data['version'] ?? $node->worker_version
        );

        /*
         * Complete any pending update where the Worker has returned
         * and is now reporting the requested target version.
         */
        if ($reportedVersion !== null) {
            $pendingUpdates = WorkerUpdate::query()
                ->where('node_id', $node->id)
                ->whereIn('status', [
                    WorkerUpdate::STATUS_QUEUED,
                    WorkerUpdate::STATUS_DISPATCHING,
                    WorkerUpdate::STATUS_RESTARTING,
                    WorkerUpdate::STATUS_FAILED,
                ])
                ->get();

            foreach ($pendingUpdates as $workerUpdate) {
                $targetVersion = $this->normaliseVersion(
                    $workerUpdate->target_version
                );

                if ($targetVersion !== $reportedVersion) {
                    continue;
                }

                $workerUpdate->forceFill([
                    'status' => WorkerUpdate::STATUS_COMPLETE,
                    'error' => null,
                    'completed_at' => now(),
                    'failed_at' => null,
                ])->save();
            }
        }

        /*
         * If an update was dispatched more than three minutes ago and
         * the Worker is heartbeating again but is still reporting a
         * different version, the updater most likely rolled back.
         *
         * We deliberately only consider RESTARTING updates here.
         * Queued/dispatching updates may not have reached the Worker yet.
         */
        WorkerUpdate::query()
            ->where('node_id', $node->id)
            ->where(
                'status',
                WorkerUpdate::STATUS_RESTARTING
            )
            ->whereNotNull('dispatched_at')
            ->where(
                'dispatched_at',
                '<=',
                now()->subMinutes(3)
            )
            ->get()
            ->each(
                function (
                    WorkerUpdate $workerUpdate
                ) use ($reportedVersion): void {
                    $targetVersion = $this->normaliseVersion(
                        $workerUpdate->target_version
                    );

                    /*
                     * The completion block above normally catches this,
                     * but don't mark it failed if the versions match.
                     */
                    if (
                        $reportedVersion !== null
                        && $targetVersion === $reportedVersion
                    ) {
                        return;
                    }

                    $reportedDisplay = $reportedVersion !== null
                        ? 'v'.$reportedVersion
                        : 'unknown';

                    $targetDisplay = $targetVersion !== null
                        ? 'v'.$targetVersion
                        : $workerUpdate->target_version;

                    $workerUpdate->forceFill([
                        'status' => WorkerUpdate::STATUS_FAILED,
                        'error' => sprintf(
                            'Worker returned after the update but is reporting version %s instead of the requested version %s. The update may have been rolled back.',
                            $reportedDisplay,
                            $targetDisplay,
                        ),
                        'failed_at' => now(),
                    ])->save();
                }
            );

        /*
         * Store the latest Worker/Cell statistics.
         */
        $node->liveStat()->updateOrCreate(
            [
                'node_id' => $node->id,
            ],
            [
                'host_cpu_used' => data_get(
                    $stats,
                    'host.cpu.used',
                    0
                ),
                'host_cpu_max' => data_get(
                    $stats,
                    'host.cpu.max',
                    0
                ),

                'host_memory_used_gb' => data_get(
                    $stats,
                    'host.memory.used_gb',
                    0
                ),
                'host_memory_max_gb' => data_get(
                    $stats,
                    'host.memory.max_gb',
                    0
                ),

                'host_disk_used_gb' => data_get(
                    $stats,
                    'host.disk.used_gb',
                    0
                ),
                'host_disk_max_gb' => data_get(
                    $stats,
                    'host.disk.max_gb',
                    0
                ),

                'cells_cpu_used' => data_get(
                    $stats,
                    'cells.cpu_used',
                    0
                ),
                'cells_memory_used_gb' => data_get(
                    $stats,
                    'cells.memory_used_gb',
                    0
                ),
                'cells_disk_used_gb' => data_get(
                    $stats,
                    'cells.disk_used_gb',
                    0
                ),

                'cells_total' => data_get(
                    $stats,
                    'cells.total',
                    0
                ),
                'cells_running' => data_get(
                    $stats,
                    'cells.running',
                    0
                ),

                'raw' => $stats,
            ]
        );

        return response()->json([
            'ok' => true,
            'node_id' => $node->id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Normalise Worker versions so that:
     *
     * v1.1.6
     * V1.1.6
     * 1.1.6
     *
     * all compare as 1.1.6.
     */
    private function normaliseVersion(
        ?string $version
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