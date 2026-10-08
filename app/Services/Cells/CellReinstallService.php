<?php

namespace App\Services\Cells;

use App\Enums\CellInstallStatus;
use App\Jobs\InstallCellJob;
use App\Models\Cell;
use App\Models\Comb;
use App\Services\Node\CellNodeClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CellReinstallService
{
    public function __construct(private readonly CellNodeClient $cells)
    {
    }

    public function reinstall(Cell $cell, Comb $comb, array $variables, bool $startAfterInstall = false): Cell
    {
        $cell->loadMissing([
            'node',
            'allocation',
            'allocations',
        ]);

        if (! $cell->node) {
            throw new RuntimeException('This cell is not assigned to a node.');
        }

        if (! $cell->daemon_id) {
            throw new RuntimeException('This cell does not have a daemon ID.');
        }

        if (! $cell->allocation) {
            throw new RuntimeException('This cell does not have a primary allocation.');
        }

        $metadata = (array) $cell->metadata;

        $variables = [
            ...$variables,
            'memory' => (string) data_get($metadata, 'limits.memory_mb', 1024),
            'server_port' => (string) $cell->allocation->port,
            'server_ip' => $cell->allocation->ip,
        ];

        $combData = (array) ($comb->data ?? []);

        $dockerImage = data_get(
            $combData,
            'docker.image',
            data_get($combData, 'image')
        );

        $startupCommand = data_get(
            $combData,
            'startup.command',
            data_get($combData, 'startup')
        );

        try {
            DB::transaction(function () use (
                $cell,
                $comb,
                $combData,
                $metadata,
                $variables,
                $dockerImage,
                $startupCommand,
                $startAfterInstall
            ): void {
                $metadata['comb_id'] = $comb->id;
                $metadata['comb_data'] = $combData;
                $metadata['variables'] = $variables;

                $metadata['docker'] = [
                    'image' => $dockerImage,
                ];

                $metadata['startup'] = [
                    'command' => $startupCommand,
                    'skip_install_script' => false,
                    'start_on_completion' => $startAfterInstall,
                ];

                $cell->forceFill([
                    'comb' => $comb->external_id,
                    'metadata' => $metadata,
                    'install_status' => CellInstallStatus::PENDING,
                    'install_failure_reason' => null,
                    'installed_at' => null,
                ])->save();
            });

            $cell->invalidateWorkerSync();

            $cell->refresh();
            $cell->loadMissing([
                'node',
                'allocation',
                'allocations',
            ]);

            // Push the current Comb definition to the Worker before reinstalling.
            $this->cells->updateCellDefinition($cell);

            // Reset the instance directory now that the Worker has the new definition.
            $this->cells->prepareReinstall($cell);

            InstallCellJob::dispatch($cell->id, $startAfterInstall);

            return $cell->refresh();
        } catch (Throwable $exception) {
            $cell->forceFill([
                'install_status' => CellInstallStatus::FAILED,
                'install_failure_reason' => $this->failureMessage($exception),
                'installed_at' => null,
            ])->save();

            throw $exception;
        }
    }

    public function retry(Cell $cell, bool $startAfterInstall = false): Cell
    {
        $cell->loadMissing([
            'node',
            'allocation',
            'allocations',
        ]);

        if (! $cell->node) {
            throw new RuntimeException('This cell is not assigned to a node.');
        }

        if (! $cell->daemon_id) {
            throw new RuntimeException('This cell does not have a daemon ID.');
        }

        if (! $cell->allocation) {
            throw new RuntimeException('This cell does not have a primary allocation.');
        }

        try {
            $cell->forceFill([
                'install_status' => CellInstallStatus::PENDING,
                'install_failure_reason' => null,
                'installed_at' => null,
            ])->save();

            $cell->invalidateWorkerSync();

            $cell->refresh();
            $cell->loadMissing([
                'node',
                'allocation',
                'allocations',
            ]);

            // Retry using the Cell's currently saved definition.
            $this->cells->updateCellDefinition($cell);
            $this->cells->prepareReinstall($cell);

            InstallCellJob::dispatch($cell->id, $startAfterInstall);

            return $cell->refresh();
        } catch (Throwable $exception) {
            $cell->forceFill([
                'install_status' => CellInstallStatus::FAILED,
                'install_failure_reason' => $this->failureMessage($exception),
                'installed_at' => null,
            ])->save();

            throw $exception;
        }
    }

    private function failureMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            $message = 'The cell reinstall failed unexpectedly.';
        }

        return mb_substr($message, 0, 5000);
    }
}