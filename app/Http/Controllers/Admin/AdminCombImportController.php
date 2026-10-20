<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comb;
use App\Services\RegistryService;

class AdminCombImportController extends Controller
{
    public function import($id, RegistryService $registry)
    {
        return app(AdminCombController::class)->importFromRegistry((string) $id, $registry);
    }

    public function sync(RegistryService $registry)
    {
        $combs = $registry->getCombs();

        $imported = 0;
        foreach ($combs as $summary) {
            $comb = $registry->getComb($summary['id']);
            if (! $comb) {
                continue;
            }
            if (Comb::where('external_id', $comb['id'])->whereIn('source', ['manual', 'registry_modified'])->exists()) {
                continue;
            }
            Comb::updateOrCreate(
                ['external_id' => $comb['id']],
                [
                    'name' => $comb['name'],
                    'game' => $comb['game'] ?? null,
                    'source' => 'registry',
                    'data' => $comb,
                ]
            );
            $imported++;
        }

        return response()->json([
            'imported' => $imported,
        ]);
    }
}
