<?php

namespace App\Services\Cells;

use App\Models\Cell;
use App\Services\Node\NodeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MinecraftContentService
{
    public function status(Cell $cell): array
    {
        $job = app(NodeClient::class)->client($cell->node)->get('/cells/'.rawurlencode($cell->daemon_id).'/content/status')->throw()->json();
        if (($job['state'] ?? '') !== 'complete') {
            return $job;
        }
        DB::transaction(function () use ($cell, $job) {
            $current = Cell::query()->whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $metadata = (array) $current->metadata;
            if (data_get($metadata, 'minecraft_content.job_id') === $job['id']) {
                return;
            }
            // Ignore jobs from before this panel's current installation request.
            if (data_get($metadata, 'minecraft_content.pending_job') && data_get($metadata, 'minecraft_content.pending_job') !== $job['id']) {
                return;
            }
            if (! empty($job['result']['startup'])) {
                $metadata['startup']['command'] = $job['result']['startup'];
                $metadata['variables']['version'] = $job['plan']['minecraft'];
                $metadata['variables']['loader_version'] = $job['plan']['loaderVersion'];
                $loader = $job['plan']['loader'];
                if (in_array($loader, ['forge', 'fabric', 'neoforge'], true)) {
                    $metadata['variables'][$loader.'_version'] = $loader === 'forge' ? Str::after($job['plan']['loaderVersion'], $job['plan']['minecraft'].'-') : $job['plan']['loaderVersion'];
                }
            }
            if (! empty($job['result']['startup'])) {
                $metadata['minecraft_content'] = ['job_id' => $job['id'], 'minecraft' => $job['plan']['minecraft'], 'loader' => $job['plan']['loader'], 'loader_version' => $job['plan']['loaderVersion'], 'name' => $job['plan']['name']];
            } else {
                unset($metadata['minecraft_content']['pending_job']);
                $metadata['minecraft_content']['job_id'] = $job['id'];
            }
            $current->forceFill(['metadata' => $metadata])->save();
            $cell->metadata = $metadata;
        });
        $cell->refresh()->load('node');

        return $job;
    }
}
