<?php

namespace App\Services\Cells;

class CellShutdownService
{
    public function variableSchema(array $combData): array
    {
        $schema = $combData['variables_schema'] ?? $combData['variables'] ?? [];
        if (! array_is_list($schema)) {
            $schema = collect($schema)->map(fn ($variable, $name) => is_array($variable) ? ['name' => $name, ...$variable] : ['name' => $name, 'default' => $variable])->values()->all();
        }
        $schema = array_values(array_filter($schema, fn ($variable) => is_array($variable) && ($variable['name'] ?? '') !== 'shutdown_command'));
        $schema[] = [
            'name' => 'shutdown_command',
            'label' => 'Shutdown command override',
            'type' => 'string',
            'default' => '',
            'required' => false,
            'description' => 'Leave blank to use the Comb shutdown command. Enter one console command to override it for this Cell; the Comb signal and grace period still apply.',
        ];

        return $schema;
    }

    public function combForCell(array $combData, array $variables): array
    {
        $command = trim((string) ($variables['shutdown_command'] ?? ''));
        if ($command !== '') {
            if (strlen($command) > 1000 || str_contains($command, "\n") || str_contains($command, "\r") || str_contains($command, "\0")) {
                throw \Illuminate\Validation\ValidationException::withMessages(['variables.shutdown_command' => 'Enter a single shutdown command of at most 1000 bytes.']);
            }
            $combData['stop'] = array_replace(['timeout' => 30, 'signal' => 'SIGTERM'], $combData['stop'] ?? [], ['command' => $command]);
        }

        return $combData;
    }
}
