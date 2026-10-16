<?php

namespace App\Console\Commands;

use App\Models\Cell;
use App\Models\CellSplit;
use App\Services\Node\CellNodeClient;
use App\Services\Node\CellToolsNodeClient;
use App\Services\Node\NodeClient;
use Illuminate\Console\Command;

class RepairCellTools extends Command
{
    protected $signature = 'hivepanel:repair-cell-tools {cell : Panel Cell UUID}';

    protected $description = 'Reconcile an interrupted resource operation without granting extra capacity';

    public function handle(CellNodeClient $cells, CellToolsNodeClient $tools, NodeClient $nodes): int
    {
        $cell = Cell::with('node', 'allocation', 'allocations')->findOrFail($this->argument('cell'));
        if (data_get($cell->metadata, 'instance_activation')) {
            $this->error('Finish the pending instance activation from the Instances page instead.');

            return self::FAILURE;
        }
        if (CellSplit::where('parent_id', $cell->id)->where('state', '!=', 'active')->exists()) {
            $this->error('Retry removing the interrupted split from Server Splitter first.');

            return self::FAILURE;
        }
        $response = $nodes->client($cell->node)->get('/cells')->throw()->json();
        $remote = isset($response['cells']) ? $response['cells'] : $response;
        $known = Cell::where('node_id', $cell->node_id)->pluck('daemon_id')->all();
        foreach ($remote as $entry) {
            if (! is_array($entry) || ! in_array($entry['id'] ?? '', $known, true)) {
                $this->error('The Worker has an unknown Cell. Reconcile orphaned Cells before returning resources.');

                return self::FAILURE;
            }
        }
        $worker = $cells->cellForSync($cell);
        $lock = data_get($worker, 'cell.lock', []);
        if (! ($worker['reachable'] ?? false) || ! ($worker['exists'] ?? false) || ($lock['type'] ?? '') !== 'resource_operation' || ! ($lock['operation_token'] ?? '')) {
            $this->error('No recoverable resource operation was found.');

            return self::FAILURE;
        }
        $token = $lock['operation_token'];
        $payload = $cells->definitionPayload($cell);
        $payload['operation_token'] = $token;
        $tools->request($cell, 'PATCH', '/definition', $payload);
        $tools->request($cell, 'POST', '/operations/end', ['token' => $token]);
        $this->info('Definition reconciled with the panel and resource lock released.');

        return self::SUCCESS;
    }
}
