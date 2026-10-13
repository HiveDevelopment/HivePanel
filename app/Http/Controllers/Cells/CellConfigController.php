<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use App\Services\Node\FileNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellConfigController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, FileNodeClient $files)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Config', [
            'cell' => $workerCell,
            'configFiles' => $this->configFiles($cell, $files),
        ]);
    }

    public function json(string $id, Request $request, FileNodeClient $files)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $paths = array_column($this->configFiles($cell, $files), 'path');
        $path = $request->query('path', $paths[0] ?? '');
        abort_unless(in_array($path, $paths, true), 422, 'Choose a configuration file declared by the Comb or found in the Cell.');
        return response()->json($files->readFile($cell, $path));
    }

    public function update(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit
    ) {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'path' => ['required', 'string'],
            'content' => ['present', 'string'],
        ]);

        abort_unless(in_array($data['path'], array_column($this->configFiles($cell, $files), 'path'), true), 422, 'Unknown configuration file.');

        $result = $files->writeFile(
            $cell,
            $data['path'],
            $data['content']
        );

        $audit->log(
            AuditEvent::CONFIG_UPDATED,
            $cell,
            "Updated configuration file \"{$data['path']}\".",
            [
                'path' => $data['path'],
            ]
        );

        return response()->json($result);
    }

    private function configFiles(\App\Models\Cell $cell, FileNodeClient $files): array
    {
        $manifest = (array) (\App\Models\Comb::find(data_get($cell->metadata, 'comb_id'))?->data ?? data_get($cell->metadata, 'comb_data', []));
        $catalog = app(\App\Services\Cells\CellConfigFiles::class);
        $declared = $catalog->declared($manifest);
        if ($declared || array_key_exists('config_files', $manifest)) return $declared;
        return $catalog->discovered($files->files($cell)['files'] ?? []);
    }
}
