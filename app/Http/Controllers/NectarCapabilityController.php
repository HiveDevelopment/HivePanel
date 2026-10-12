<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Services\Nectar\ExtensionManager;
use App\Services\Nectar\ExtensionSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NectarCapabilityController extends Controller
{
    public function settings(string $id, ExtensionManager $manager, ExtensionSettings $settings): JsonResponse
    {
        abort_unless($manager->enabledWithCapability($id, 'settings.read'), 403);
        return response()->json(['settings' => $settings->values($id, true)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function cells(Request $request, string $id, ExtensionManager $manager): JsonResponse
    {
        abort_unless($manager->enabledWithCapability($id, 'cells.read'), 403);

        $manifest = collect($manager->installed())->firstWhere('id', $id)['manifest'];
        $scopes = $manifest['frontend']['combs'] ?? [];

        $cells = Cell::query()
            ->visibleTo($request->user())
            ->with(['allocations'])
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'comb', 'metadata', 'owner_id', 'install_status'])
            ->filter(function (Cell $cell) use ($scopes): bool {
                if (! $scopes) return true;
                $comb = \App\Models\Comb::find(data_get($cell->metadata, 'comb_id'));
                return in_array((string) ($comb?->external_id ?? $cell->comb), $scopes, true) || in_array('game:' . strtolower((string) ($comb?->game ?? data_get($cell->metadata, 'comb_data.game', ''))), $scopes, true);
            })
            ->map(fn (Cell $cell) => [
                'id' => $cell->id,
                'name' => $cell->name,
                'comb' => $cell->comb,
                'allocations' => $manager->enabledWithCapability($id, 'network.tcp') && $cell->userCan($request->user(), \App\Support\CellPermissions::CONSOLE_SEND) ? $cell->allocations->filter(fn ($allocation) => ! is_array(data_get($cell->metadata, 'desired_allocation_ids')) || in_array((string) $allocation->id, array_map('strval', data_get($cell->metadata, 'desired_allocation_ids')), true))->map(fn ($allocation) => ['id' => $allocation->id, 'ip' => $allocation->ip, 'port' => (int) $allocation->port])->values()->all() : [],
                'installStatus' => $cell->install_status?->value ?? 'unknown',
            ])->values();

        return response()->json(['cells' => $cells])
            ->header('Cache-Control', 'private, no-store');
    }
}
