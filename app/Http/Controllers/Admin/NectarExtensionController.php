<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Nectar\ExtensionManager;
use App\Services\Nectar\ExtensionSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            throw ValidationException::withMessages(['package' => __('Upload a .nectar package.')]);
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

        return Inertia::render('Admin/Extensions/Show', ['extension' => $extension, 'networkAudits' => DB::table('nectar_network_audits')->where('extension_id', $id)->orderByDesc('id')->limit(30)->get()]);
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

        return back()->with('success', __('Extension settings saved.'));
    }

    public function update(Request $request, string $id, ExtensionManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'], 'approve_network' => ['sometimes', 'boolean'],
            'navigation_overrides' => ['sometimes', 'array', 'max:10'],
            'navigation_overrides.*' => ['array:group'],
            'navigation_overrides.*.group' => ['required', 'in:extensions,overview,management,automation,configuration,game,tools'],
        ]);
        if (isset($data['navigation_overrides'])) {
            $extension = collect($manager->installed())->firstWhere('id', $id);
            abort_unless($extension, 404);
            $slugs = array_column($extension['manifest']['frontend']['pages'] ?? [], 'slug');
            foreach (array_keys($data['navigation_overrides']) as $slug) {
                if (! in_array($slug, $slugs, true)) {
                    throw ValidationException::withMessages(['navigation_overrides' => __('Unknown extension page.')]);
                }
            }
            DB::table('nectar_extensions')->where('extension_id', $id)->update(['navigation_overrides' => json_encode($data['navigation_overrides'])]);
        }
        if ($data['enabled'] && ($data['approve_network'] ?? false)) {
            DB::table('nectar_extensions')->where('extension_id', $id)->update(['network_approved' => true]);
        }
        try {
            $manager->setEnabled($id, $data['enabled']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['enabled' => $e->getMessage()]);
        }

        return back()->with('success', array_key_exists('navigation_overrides', $data) ? 'Sidebar placement saved.' : ($data['enabled'] ? 'Extension enabled.' : 'Extension disabled.'));
    }

    public function destroy(string $id, ExtensionManager $manager): RedirectResponse
    {
        try {
            $manager->uninstall($id);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['extension' => $e->getMessage()]);
        }

        return redirect()->route('admin.extensions.index')->with('success', __('Extension uninstalled.'));
    }
}
