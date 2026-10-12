<?php

namespace App\Http\Controllers;

use App\Services\Nectar\ExtensionManager;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class NectarPageController extends Controller
{
    public function navigation(\Illuminate\Http\Request $request, ExtensionManager $manager): JsonResponse
    {
        $cell = null;
        if ($request->filled('cell')) {
            $cell = \App\Models\Cell::query()->visibleTo($request->user())->findOrFail($request->query('cell'));
            abort_unless($cell->userCan($request->user(), 'console.view'), 403);
        }
        return response()->json([
            'pages' => array_values(array_filter(
                $manager->pages($cell),
                fn (array $page) => $page['navigation'],
            )),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function showCell(\Illuminate\Http\Request $request, \App\Models\Cell $cell, string $id, string $slug, ExtensionManager $manager, \App\Services\Cells\CellDashboardService $dashboard, \App\Services\Node\CellNodeClient $nodes): Response
    {
        abort_unless($cell->userCan($request->user(), 'console.view'), 403);
        $page = collect($manager->pages($cell))->first(fn ($page) => $page['extensionId'] === $id && $page['slug'] === $slug);
        abort_unless($page, 404);
        $entry = "dist/pages/{$slug}.html";
        $manager->asset($id, $entry);

        return Inertia::render('Extensions/Show', [
            'extensionPage' => $page,
            'cell' => $dashboard->frontendCell($cell->loadMissing('node'), $nodes),
            'frameUrl' => route('nectar.asset', ['id' => $id, 'path' => $entry]),
        ]);
    }

    public function show(string $id, string $slug, ExtensionManager $manager): Response
    {
        $page = collect($manager->pages())->first(
            fn (array $page) => $page['extensionId'] === $id && $page['slug'] === $slug,
        );

        abort_unless($page, 404);

        // Verify the entry exists and is served by the enabled-only asset handler.
        $entry = "dist/pages/{$slug}.html";
        $manager->asset($id, $entry);

        return Inertia::render('Extensions/Show', [
            'extensionPage' => $page,
            'frameUrl' => route('nectar.asset', ['id' => $id, 'path' => $entry]),
        ]);
    }
}
