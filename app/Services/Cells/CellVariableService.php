<?php

namespace App\Services\Cells;

use App\Models\Cell;

class CellVariableService
{
    public function values(Cell $cell, array $manifest, array $worker = []): array
    {
        $defaults = [];
        foreach ((new CellShutdownService)->variableSchema($manifest) as $definition) {
            $name = $definition['name'] ?? '';
            if ($name !== '') {
                $defaults[$name] = $definition['default'] ?? '';
            }
        }
        $values = array_replace($defaults, $worker, (array) data_get($cell->metadata, 'variables', []));
        foreach ($values as $key => $value) {
            $values[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : '');
        }

        return $values;
    }
}
