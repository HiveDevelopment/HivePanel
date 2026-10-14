<?php

namespace App\Http\Controllers\Cells;

use App\Models\Cell;
use App\Services\Cells\MinecraftContentService;
use App\Services\Node\CellNodeClient;
use App\Services\Node\NodeClient;
use App\Services\PreinstallRegistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CellPreinstallController extends CellBaseController
{
    private function recipes(Cell $cell): array
    {
        return array_values(array_filter((array) data_get(app(CellNodeClient::class)->definitionPayload($cell), 'comb_data.preinstall_recipes', []), fn ($recipe) => is_array($recipe) && is_string($recipe['id'] ?? null)));
    }

    private function offered(Cell $cell): array
    {
        return array_values(array_filter(array_merge(array_column($this->recipes($cell), 'id'), (array) data_get(app(CellNodeClient::class)->definitionPayload($cell), 'comb_data.preinstalls', [])), fn ($id) => is_string($id) && preg_match('/^[a-z0-9-]{1,80}$/', $id)));
    }

    private function available(string $id): Cell
    {
        $cell = $this->panelCellOrFail($id);
        abort_unless(count($this->offered($cell)), 404);

        return $cell;
    }

    private function requireInstallPermissions(Cell $cell, Request $request): void
    {
        foreach (['files.upload', 'files.write', 'files.delete', 'startup.update'] as $permission) {
            abort_unless($cell->userCan($request->user(), $permission), 403);
        }
    }

    public function index(string $id, CellNodeClient $cells)
    {
        $cell = $this->available($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        $canInstall = collect(['files.upload', 'files.write', 'files.delete', 'startup.update'])->every(fn ($permission) => $cell->userCan(request()->user(), $permission));

        return Inertia::render('Cells/Preinstalls', ['cell' => $this->frontendCell($cell, $cells), 'canInstall' => $canInstall]);
    }

    public function catalogue(string $id, PreinstallRegistryService $registry)
    {
        $cell = $this->available($id);
        $allowed = $this->offered($cell);
        $inline = $this->recipes($cell);
        $published = array_diff($allowed, array_column($inline, 'id')) ? ($registry->json('get', '/')['presets'] ?? []) : [];
        $offered = array_values(array_filter($published, fn ($preset) => in_array($preset['id'], $allowed, true)));
        foreach ($inline as $recipe) {
            $recipe['variables'] = $recipe['runtime']['variables_schema'] ?? [];
            unset($recipe['runtime'], $recipe['files'], $recipe['steps']);
            $offered[] = $recipe;
        }

        return response()->json(['presets' => $offered]);
    }

    public function preview(string $id, Request $request, PreinstallRegistryService $registry)
    {
        $cell = $this->available($id);
        $this->abortUnlessInstalled($cell);
        $this->requireInstallPermissions($cell, $request);
        $data = $request->validate(['preset' => 'required|string|regex:/^[a-z0-9-]{1,80}$/', 'version' => 'required|string|regex:/^[A-Za-z0-9_.-]{1,80}$/', 'variables' => 'nullable|array|max:50', 'variables.*' => 'nullable|string|max:4096']);
        abort_unless(in_array($data['preset'], $this->offered($cell), true), 403);
        $inline = collect($this->recipes($cell))->firstWhere('id', $data['preset']);
        $selection = ['preset' => $data['preset'], 'version' => $data['version']];
        if ($inline) {
            $selection['recipe'] = $inline;
        }
        $plan = $registry->json('post', '/resolve', $selection);
        $parameters = [];
        $allowed = collect($plan['runtime']['variables_schema'] ?? [])->filter(fn ($variable) => ! empty($variable['install']) && empty($variable['secret']))->pluck('name')->all();
        foreach ($data['variables'] ?? [] as $name => $value) {
            abort_unless(in_array($name, $allowed, true), 422, 'Only declared installation variables may be customized.');
            $parameters[$name] = (string) ($value ?? '');
        }
        $plan['runtime']['parameters'] = $parameters;
        abort_unless(($plan['kind'] ?? '') === 'preinstall' && ($plan['preset'] ?? '') === $data['preset'], 422, 'Registry returned an invalid preinstall.');
        $ticket = (string) Str::uuid();
        Cache::put('preinstall-preview:'.$ticket, ['user' => auth()->id(), 'cell' => $id, 'plan' => $plan], now()->addMinutes(10));

        return response()->json(['ticket' => $ticket, 'plan' => $plan]);
    }

    public function install(string $id, Request $request, CellNodeClient $cells, NodeClient $nodes)
    {
        $cell = $this->available($id);
        $this->abortUnlessInstalled($cell);
        $this->requireInstallPermissions($cell, $request);
        $data = $request->validate(['ticket' => 'required|uuid', 'confirm_wipe' => 'required|accepted']);
        $preview = Cache::get('preinstall-preview:'.$data['ticket']);
        abort_unless($preview && $preview['user'] === auth()->id() && $preview['cell'] === $id, 422, 'Installation preview expired. Review the selection again.');
        abort_unless(in_array($preview['plan']['preset'], $this->offered($cell), true), 403);
        $this->abortIfLocked($cell, $cells);

        return DB::transaction(function () use ($cell, $nodes, $preview, $data) {
            $current = Cell::query()->whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $response = $nodes->client($cell->node)->post('/cells/'.rawurlencode($cell->daemon_id).'/content/install', $preview['plan']);
            if (! $response->successful()) {
                return response()->json(['message' => $response->json('message') ?: 'Worker could not start the installation.'], $response->status());
            }
            $job = $response->json();
            $metadata = (array) $current->metadata;
            $metadata['content_install']['pending_job'] = $job['id'];
            $current->forceFill(['metadata' => $metadata])->save();
            Cache::forget('preinstall-preview:'.$data['ticket']);

            return response()->json($job, 202);
        });
    }

    public function status(string $id, MinecraftContentService $content)
    {
        return response()->json($content->status($this->available($id)));
    }
}
