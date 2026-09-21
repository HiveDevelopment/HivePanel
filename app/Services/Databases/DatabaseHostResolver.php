<?php

namespace App\Services\Databases;

use App\Models\Cell;
use App\Models\DatabaseHost;
use App\Models\Node;
use RuntimeException;

class DatabaseHostResolver
{
    public function forCell(Cell $cell): DatabaseHost
    {
        $cell->loadMissing('node');

        if (! $cell->node) {
            throw new RuntimeException('This Cell is not assigned to a Node.');
        }

        return $this->forNode($cell->node);
    }

    public function forNode(Node $node): DatabaseHost
    {
        $assignments = $node->databaseHostAssignments()->where('enabled', true)->with('databaseHost')->orderByDesc('is_primary')->orderBy('priority')->get();

        foreach ($assignments as $assignment) {
            $host = $assignment->databaseHost;

            if (! $host || ! $host->enabled) {
                continue;
            }

            if ($host->max_databases !== null && $host->databases()->count() >= $host->max_databases) {
                continue;
            }

            return $host;
        }

        throw new RuntimeException('No database host is available for this Cell\'s Node. Contact an administrator.');
    }
}