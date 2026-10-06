<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HivePanelUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AdminUpdateController extends Controller
{
    public function index(Request $request, HivePanelUpdateService $updates): Response
    {
        $latest = null;
        $error = null;

        try {
            $latest = $updates->latestRelease();
        } catch (Throwable) {
            $error = 'HivePanel could not check for updates right now.';
        }

        return Inertia::render('Admin/Updates/Index', [
            'currentVersion' => $updates->currentVersion(),
            'latestRelease' => $latest,
            'updateStatus' => $updates->status(),
            'canInstallUpdates' => (bool) $request->user()?->can_update_panel,
            'checkError' => $error,
        ]);
    }

    public function status(HivePanelUpdateService $updates): JsonResponse
    {
        return response()->json($updates->status());
    }

    public function install(Request $request, HivePanelUpdateService $updates): RedirectResponse
    {
        abort_unless($request->user()?->can_update_panel, 403);

        $data = $request->validate([
            'version' => ['required', 'string', 'max:64'],
        ]);

        try {
            $updates->request($data['version'], $request->user()->id);
        } catch (Throwable $exception) {
            return back()->withErrors(['update' => $exception->getMessage()]);
        }

        return back()->with('success', 'HivePanel update has been queued.');
    }
}
