<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Nectar\ExtensionManager;
use App\Services\Nectar\ExtensionSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class NectarExtensionController extends Controller
{
    public function index(ExtensionManager $manager): Response
    {
        return Inertia::render('Admin/Extensions/Index', [
            'extensions' => $manager->installed(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Extensions/Install');
    }

    public function store(Request $request, ExtensionManager $manager): RedirectResponse
    {
        $request->validate(['package' => ['required', 'file', 'max:20480']]);
        $file = $request->file('package');
        if (strtolower($file->getClientOriginalExtension()) !== 'nectar') {
            throw ValidationException::withMessages(['package' => 'Upload a .nectar package.']);
        }
        try {
            $manifest = $manager->install($file->getRealPath());
        } catch (ValidationException $exception) {
            // The uploaded manifest is one form field; present all manifest
            // validation failures underneath the package upload input.
            throw ValidationException::withMessages([
                'package' => collect($exception->errors())
                    ->flatten()
                    ->implode(' '),
            ]);
        } catch (RuntimeException|\JsonException $exception) {
            throw ValidationException::withMessages([
                'package' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.extensions.show', ['id' => $manifest['id']])
            ->with('success', "Installed {$manifest['name']}. Review and enable it when ready.");
    }

    public function show(string $id, ExtensionManager $manager): Response
    {
        $extension = collect($manager->installed())->firstWhere('id', $id);
        abort_unless($extension, 404);

        return Inertia::render('Admin/Extensions/Show', ['extension' => $extension]);
    }

    public function settings(string $id, ExtensionSettings $settings, ExtensionManager $manager): Response
    {
        $extension = collect($manager->installed())->firstWhere('id', $id);
        abort_unless($extension, 404);
        return Inertia::render('Admin/Extensions/Settings', [
            'extension' => ['id' => $id, 'name' => $extension['name']],
            'schema' => $settings->schema($id),
            'values' => $settings->values($id),
        ]);
    }

    public function saveSettings(Request $request, string $id, ExtensionSettings $settings): RedirectResponse
    {
        $data = $request->validate(['values' => ['required', 'array']]);
        $settings->save($id, $data['values']);
        return back()->with('success', 'Extension settings saved.');
    }

    public function update(Request $request, string $id, ExtensionManager $manager): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        try {
            $manager->setEnabled($id, $data['enabled']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['enabled' => $e->getMessage()]);
        }

        return back()->with('success', $data['enabled'] ? 'Extension enabled.' : 'Extension disabled.');
    }

    public function destroy(string $id, ExtensionManager $manager): RedirectResponse
    {
        try {
            $manager->uninstall($id);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['extension' => $e->getMessage()]);
        }

        return redirect()->route('admin.extensions.index')->with('success', 'Extension uninstalled.');
    }
}
