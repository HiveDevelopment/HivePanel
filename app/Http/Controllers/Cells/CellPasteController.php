<?php

namespace App\Http\Controllers\Cells;

use App\Services\AuditLogger;
use App\Services\HivePasteService;
use App\Services\Node\CellNodeClient;
use App\Services\Node\FileNodeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CellPasteController extends CellBaseController
{
    public function file(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, HivePasteService $paste, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:2048']]);
        $cell = $this->panelCellOrFail($id);
        $this->abortIfLocked($cell, $cells);
        $path = $data['path'];
        $name = basename(str_replace('\\', '/', $path));
        if ($name === '' || $name === '.' || $name === '..') {
            throw ValidationException::withMessages(['path' => 'Select a file to share.']);
        }
        try {
            $file = $files->readFile($cell, $path);
            $content = $file['content'] ?? null;
            if (! is_string($content)) {
                throw ValidationException::withMessages(['path' => 'The selected file is not readable text.']);
            }
            $result = $paste->create($content, $name, $this->language($name));
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('HivePaste file sharing failed', ['cell_id' => $cell->id, 'exception' => $e::class]);
            return response()->json(['message' => 'Unable to share this file. Check file size and HivePaste availability.'], 422);
        }
        $audit->log('hivepaste.file_shared', $cell, 'Shared a file to HivePaste', ['path' => $path, 'paste_id' => $result['id']]);
        return response()->json($result);
    }

    public function console(string $id, Request $request, HivePasteService $paste, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string'], 'selection' => ['nullable', 'boolean']]);
        $cell = $this->panelCellOrFail($id);
        try {
            $result = $paste->create($data['content'], 'Console output', 'log');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('HivePaste console sharing failed', ['cell_id' => $cell->id, 'exception' => $e::class]);
            return response()->json(['message' => 'Unable to share console output. Check size and HivePaste availability.'], 422);
        }
        $audit->log('hivepaste.console_shared', $cell, 'Shared console output to HivePaste', ['paste_id' => $result['id']]);
        return response()->json($result);
    }

    private function language(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return match ($ext) {
            'js', 'mjs', 'cjs' => 'javascript', 'ts', 'tsx' => 'typescript', 'py' => 'python',
            'php' => 'php', 'json' => 'json', 'yml', 'yaml' => 'yaml', 'html', 'htm' => 'html',
            'css' => 'css', 'sql' => 'sql', 'sh', 'bash' => 'bash', 'java' => 'java',
            'go' => 'go', 'rs' => 'rust', 'xml' => 'xml', 'log' => 'log',
            default => 'text',
        };
    }
}
