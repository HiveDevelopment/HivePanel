<?php

namespace App\Services\Cells;

use App\Enums\CellInstallStatus;
use App\Jobs\InstallCellJob;
use App\Models\Cell;
use App\Models\Comb;
use App\Services\Node\CellNodeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CellReinstallService
{
    public function __construct(private readonly CellNodeClient $cells) {}

    public function reinstall(Cell $cell, Comb $comb, array $variables, bool $startAfterInstall = false, ?string $image = null): Cell
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

        if (data_get($cell->metadata, 'minecraft_content') || data_get($cell->metadata, 'content_install')) {
            $job = app(MinecraftContentService::class)->status($cell);
            if (($job['state'] ?? '') === 'installing') {
                throw new RuntimeException('Wait for the content installation to finish before reinstalling.');
            }
        }
        $metadata = (array) $cell->metadata;
        // Reinstall chooses a new Comb; retain only the completed-job acknowledgement.
        if (isset($metadata['minecraft_content'])) {
            $acknowledgement = data_get($metadata, 'minecraft_content.job_id');
            $metadata['minecraft_content'] = $acknowledgement ? ['job_id' => $acknowledgement] : [];
        }

        if (isset($metadata['content_install'])) {
            $acknowledgement = data_get($metadata, 'content_install.job_id');
            $metadata['content_install'] = $acknowledgement ? ['job_id' => $acknowledgement] : [];
        }

        $variables = $this->variablesForReinstall($cell, $comb, $variables);
        $variables = [
            ...$variables,
            'memory' => (string) data_get($metadata, 'limits.memory_mb', 1024),
            'server_port' => (string) $cell->allocation->port,
            'server_ip' => $cell->allocation->ip,
        ];

        $combData = (array) ($comb->data ?? []);

        $dockerImage = app(CombImageService::class)->choose($combData, $image, $cell->comb === $comb->external_id ? data_get($metadata, 'docker.image') : null);

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

    public function variablesForReinstall(Cell $cell, Comb $comb, array $provided): array
    {
        $schema = app(CellShutdownService::class)->variableSchema((array) $comb->data);
        $existing = $cell->comb === $comb->external_id ? (array) data_get($cell->metadata, 'variables', []) : [];
        $result = [];
        $editable = [];
        $rules = [];
        foreach ($schema as $variable) {
            $name = (string) ($variable['name'] ?? '');
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) || in_array($name, ['memory', 'server_ip', 'server_port'], true)) {
                continue;
            }
            $result[$name] = (string) ($existing[$name] ?? $variable['default'] ?? '');
            if (($variable['user_editable'] ?? true) !== false && empty($variable['hidden']) && empty($variable['internal'])) {
                $editable[] = $name;
                if (array_key_exists($name, $provided)) {
                    $result[$name] = (string) ($provided[$name] ?? '');
                }
            }
            $rules[$name] = [! empty($variable['required']) ? 'required' : 'nullable', 'string', 'max:4096'];
            if (in_array($variable['type'] ?? '', ['number', 'integer'], true)) {
                $rules[$name][] = 'numeric';
            }
            if (($variable['type'] ?? '') === 'boolean') {
                $rules[$name][] = Rule::in(['true', 'false', '0', '1']);
            }
        }
        if (array_diff(array_keys($provided), $editable)) {
            throw ValidationException::withMessages(['variables' => __('Only editable Comb variables may be changed.')]);
        }
        Validator::make($result, $rules)->validate();

        return $result;
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
