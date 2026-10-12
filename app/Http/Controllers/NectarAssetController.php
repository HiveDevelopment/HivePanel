<?php

namespace App\Http\Controllers;

use App\Services\Nectar\ExtensionManager;
use Illuminate\Http\Request;

class NectarAssetController extends Controller
{
    // Must match ExtensionManager::ALLOWED.
    private const MIME_TYPES = [
        'html' => 'text/html; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'json' => 'application/json',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'woff2' => 'font/woff2',
    ];

    public function slots(Request $request, ExtensionManager $manager)
    {
        $slot = (string) $request->query('slot', '');
        $cellId = $request->query('cell');
        $cell = null;
        $comb = null;

        // Cell slots require an actual Cell that the logged-in user may view.
        if (str_starts_with($slot, 'server.')) {
            abort_unless(is_string($cellId) && $cellId !== '', 422);
            $cell = \App\Models\Cell::query()
                ->visibleTo($request->user())
                ->where('id', $cellId)
                ->firstOrFail();
            $combId = data_get($cell->metadata, 'comb_id');
            $comb = $combId ? \App\Models\Comb::query()->find($combId) : null;
        }

        $extensions = array_values(array_filter($manager->activeForSlot($slot), function ($extension) use ($comb, $cell) {
            $targets = $extension['manifest']['frontend']['combs'] ?? [];
            if (! $targets) return true;
            if (! $cell || ! $comb) return false;
            // Stable comb identifier or game category, never user-provided labels.
            return in_array((string) $comb->external_id, $targets, true)
                || in_array('game:'.strtolower((string) $comb->game), $targets, true);
        }));

        return response()->json([
            'extensions' => array_map(fn ($extension) => [
                'id' => $extension['id'],
                'name' => $extension['name'],
                'url' => route('nectar.asset', [
                    'id' => $extension['id'],
                    'path' => 'dist/index.html',
                ]),
            ], $extensions),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(string $id, string $path, ExtensionManager $manager)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! isset(self::MIME_TYPES[$extension])) {
            abort(404);
        }

        // This manager method checks enabled state and canonical file containment.
        $file = $manager->asset($id, $path);
        if (! is_file($file) || ! is_readable($file)) {
            abort(404);
        }

        $response = response()->file($file, [
            'Content-Type' => self::MIME_TYPES[$extension],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);

        if ($extension === 'html') {
            $origin = request()->getSchemeAndHttpHost();
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'none'",
                'sandbox allow-scripts',
                "script-src {$origin}",
                "style-src {$origin} 'unsafe-inline'",
                "img-src {$origin} data:",
                "font-src {$origin}",
                "connect-src 'none'",
                "object-src 'none'",
                "frame-src 'none'",
                "form-action 'none'",
                "base-uri 'none'",
                "worker-src 'none'",
                "manifest-src 'none'",
            ]));
        }

        return $response;
    }
}
