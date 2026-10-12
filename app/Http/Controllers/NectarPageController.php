<?php

namespace App\Http\Controllers;

use App\Services\Nectar\ExtensionManager;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class NectarPageController extends Controller
{
    public function navigation(ExtensionManager $manager): JsonResponse
    {
        return response()->json([
            'pages' => array_values(array_filter(
                $manager->pages(),
                fn (array $page) => $page['navigation'],
            )),
        ])->header('Cache-Control', 'private, no-store');
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
