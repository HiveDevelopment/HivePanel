<?php

namespace App\Http\Controllers\Cells;

use App\Models\Cell;
use App\Models\MinecraftPack;
use App\Services\Cells\MinecraftContentService;
use App\Services\MinecraftRegistryService;
use App\Services\Node\CellNodeClient;
use App\Services\Node\NodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CellMinecraftController extends CellBaseController
{
    private function minecraft(string $id): Cell
    {
        $cell = $this->panelCellOrFail($id);
        $manifest = app(CellNodeClient::class)->definitionPayload($cell)['comb_data'];
        abort_unless(strtolower((string) ($manifest['game'] ?? '')) === 'minecraft' && ! str_contains(strtolower(($cell->comb ?? '').' '.($manifest['id'] ?? '')), 'bedrock'), 404);

        return $cell;
    }

    public function index(string $id, string $mode, CellNodeClient $cells)
    {
        $cell = $this->minecraft($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        return Inertia::render('Cells/Minecraft', ['cell' => $this->frontendCell($cell, $cells), 'mode' => $mode, 'minecraftSettings' => (array) data_get($cell->metadata, 'minecraft_content', []), 'packs' => MinecraftPack::where('user_id', auth()->id())->latest()->get(['id', 'name', 'spec']), 'canInstall' => $cell->userCan(request()->user(), 'files.upload') && $cell->userCan(request()->user(), 'files.write'), 'canWipe' => $cell->userCan(request()->user(), 'files.delete'), 'canChangeRuntime' => $cell->userCan(request()->user(), 'startup.update')]);
    }

    public function catalogue(string $id, Request $request, MinecraftRegistryService $registry)
    {
        $this->minecraft($id);
        $data = $request->validate(['action' => 'required|in:versions,loaders,search,releases', 'provider' => 'nullable|in:modrinth,curseforge', 'project' => 'nullable|regex:/^[A-Za-z0-9_.-]{1,100}$/', 'minecraft' => 'nullable|string|max:60', 'loader' => 'nullable|string|max:30', 'query' => 'nullable|string|max:100', 'kind' => 'nullable|in:mod,plugin,modpack', 'offset' => 'nullable|integer|min:0|max:10000']);
        $path = match ($data['action']) {
            'releases' => '/projects/'.($data['provider'] ?? 'modrinth').'/'.($data['project'] ?? 'invalid').'/versions', default => '/'.$data['action']
        };
        unset($data['action']);

        return response()->json($registry->json('get', $path, $data));
    }

    private function spec(Request $request): array
    {
        return $request->validate(['kind' => 'required|in:mod,plugin,jar,builder,modpack', 'minecraft' => 'required|regex:/^[A-Za-z0-9_.-]{1,60}$/', 'loader' => 'required|in:vanilla,paper,fabric,forge,neoforge', 'loaderVersion' => 'nullable|string|max:100', 'name' => 'nullable|string|max:100', 'selections' => 'present|array|max:200', 'selections.*.provider' => 'required|in:modrinth,curseforge', 'selections.*.project' => 'required|string|regex:/^[A-Za-z0-9_.-]{1,100}$/', 'selections.*.version' => 'required|string|regex:/^[A-Za-z0-9_.-]{1,100}$/']);
    }

    public function preview(string $id, Request $request, MinecraftRegistryService $registry)
    {
        $cell = $this->minecraft($id);
        $this->abortUnlessInstalled($cell);
        $spec = $this->spec($request);
        $plan = $registry->json('post', '/resolve', $spec + ['side' => 'server']);
        $ticket = (string) Str::uuid();
        Cache::put('minecraft-preview:'.$ticket, ['user' => auth()->id(), 'cell' => $id, 'plan' => $plan], now()->addMinutes(10));

        return response()->json(['ticket' => $ticket, 'plan' => $plan]);
    }

    public function install(string $id, Request $request, CellNodeClient $cells, NodeClient $nodes)
    {
        $cell = $this->minecraft($id);
        $this->abortUnlessInstalled($cell);
        abort_unless($cell->userCan($request->user(), 'files.write'), 403);
        $ticket = $request->validate(['ticket' => 'required|uuid'])['ticket'];
        $preview = Cache::get('minecraft-preview:'.$ticket);
        abort_unless($preview && $preview['user'] === auth()->id() && $preview['cell'] === $id, 422, 'Installation preview expired. Review the selection again.');
        $plan = $preview['plan'];
        $wipe = in_array($plan['kind'], ['builder', 'modpack'], true);
        if ($wipe) {
            $request->validate(['confirm_wipe' => 'required|accepted']);
            abort_unless($cell->userCan($request->user(), 'files.delete'), 403);
        }
        $plan['wipe'] = $wipe;
        if (in_array($plan['kind'], ['jar', 'builder', 'modpack'], true)) {
            abort_unless($cell->userCan($request->user(), 'startup.update'), 403);
        }
        $this->abortIfLocked($cell, $cells);

        // Serialize requests and panel metadata writes; Worker independently guards runtime and file state.
        return DB::transaction(function () use ($cell, $nodes, $plan, $ticket) {
            $current = Cell::query()->whereKey($cell->id)->lockForUpdate()->firstOrFail();
            $response = $nodes->client($cell->node)->post('/cells/'.rawurlencode($cell->daemon_id).'/content/install', $plan);
            if (! $response->successful()) {
                return response()->json(['message' => $response->json('message') ?: 'Worker could not start the installation.'], $response->status());
            }
            $job = $response->json();
            $metadata = (array) $current->metadata;
            $metadata['minecraft_content']['pending_job'] = $job['id'];
            $current->forceFill(['metadata' => $metadata])->save();
            Cache::forget('minecraft-preview:'.$ticket);

            return response()->json($job, 202);
        });
    }

    public function status(string $id, MinecraftContentService $content)
    {
        return response()->json($content->status($this->minecraft($id)));
    }

    public function savePack(string $id, Request $request)
    {
        $this->minecraft($id);
        $spec = $this->spec($request);
        abort_unless($spec['kind'] === 'builder', 422);
        $name = $request->validate(['name' => 'required|string|max:100'])['name'];
        abort_if(MinecraftPack::where('user_id', auth()->id())->count() >= 100, 422, 'You have reached the limit of 100 saved packs.');
        $pack = MinecraftPack::create(['user_id' => auth()->id(), 'name' => $name, 'spec' => $spec]);

        return response()->json($pack, 201);
    }

    public function deletePack(string $id, string $pack)
    {
        $this->minecraft($id);
        MinecraftPack::where('user_id', auth()->id())->findOrFail($pack)->delete();

        return response()->noContent();
    }

    public function export(string $id, string $format, Request $request, MinecraftRegistryService $registry)
    {
        $this->minecraft($id);
        abort_unless(in_array($format, ['modrinth', 'curseforge'], true), 404);
        $spec = $this->spec($request);
        $spec['kind'] = 'builder';
        $spec['side'] = 'client';
        $response = $registry->request('post', '/export/'.$format, $spec);

        return response($response->body())->header('Content-Type','application/zip')->header('Content-Disposition','attachment; filename="custom-pack.'.($format === 'modrinth' ? 'mrpack' : 'zip').'"');
    }
}
