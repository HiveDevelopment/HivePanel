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

        $cells = Cell::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'install_status'])
            ->map(fn (Cell $cell) => [
                'id' => $cell->id,
                'name' => $cell->name,
                'installStatus' => $cell->install_status?->value ?? 'unknown',
            ]);

        return response()->json(['cells' => $cells])
            ->header('Cache-Control', 'private, no-store');
    }
}
