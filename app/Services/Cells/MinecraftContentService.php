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
            return $this->userFacingJob($job);
        }
        DB::transaction(function () use ($cell, $job) {
            $current = Cell::query()->whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $metadata = (array) $current->metadata;
            if (($job['plan']['kind'] ?? '') === 'preinstall') {
                if (data_get($metadata, 'content_install.job_id') === $job['id']) {
                    return;
                }
                if (data_get($metadata, 'content_install.pending_job') && data_get($metadata, 'content_install.pending_job') !== $job['id']) {
                    return;
                }
                $runtime = $job['plan']['runtime'];
                $runtime['image'] = $job['result']['image'] ?? $runtime['image'];
                $metadata['startup']['command'] = $job['result']['startup'];
                $metadata['docker']['image'] = $runtime['image'];
                foreach ($runtime['variables_schema'] as $variable) {
                    $metadata['variables'][$variable['name']] ??= (string) ($variable['default'] ?? '');
                }
                $metadata['content_install'] = ['job_id' => $job['id'], 'preset' => $job['plan']['preset'], 'version' => $job['plan']['version'], 'name' => $job['plan']['name'], 'runtime' => $runtime];
                $current->forceFill(['metadata' => $metadata])->save();
                $cell->metadata = $metadata;

                return;
            }
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

        return $this->userFacingJob($job);
    }

    public function manifest(Cell $cell, array $base): array
    {
        $runtime = data_get($cell->metadata, 'content_install.runtime');
        if (! is_array($runtime)) {
            return $base;
        }
        foreach (['image', 'startup', 'environment', 'variables_schema'] as $key) {
            if (array_key_exists($key, $runtime)) {
                $base[$key] = $runtime[$key];
            }
        }
        $base['working_dir'] = '/home/container';

        return $base;
    }

    public function userFacingJob(array $job): array
    {
        $backup = (string) data_get($job, 'result.backup', '');
        $marker = '/.hivepanel/content-backups/';
        $normalized = str_replace('\\', '/', $backup);
        $position = strpos($normalized, $marker);
        if ($position !== false && $position > 0) {
            $root = substr($normalized, 0, $position);
            $clean = fn ($text) => str_replace([$backup, $root.'/'], ['this Cell’s installation backup', ''], (string) $text);
            $job['message'] = $clean($job['message'] ?? '');
            $job['logs'] = array_map($clean, $job['logs'] ?? []);
            $job['result']['backup'] = '.hivepanel/content-backups/'.basename($normalized);
        }

        return $job;
    }
}
