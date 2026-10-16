<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\PanelThemes;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminThemeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAdminPermission('admin.settings.view'), 403);

        return Inertia::render('Admin/Themes/Index', ['catalog' => PanelThemes::catalog(), 'canEdit' => $request->user()->hasAdminPermission('admin.settings.update')]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->hasAdminPermission('admin.settings.update'), 403);
        $defaults = json_decode(file_get_contents(resource_path('themes/hive.json')), true);
        $rules = ['default' => ['required', 'string'], 'themes' => ['required', 'array', 'min:1', 'max:30'], 'themes.*' => ['array:id,name,enabled,tokens'], 'themes.*.id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'distinct'], 'themes.*.name' => ['required', 'string', 'max:80'], 'themes.*.enabled' => ['required', 'boolean'], 'themes.*.tokens' => ['required', 'array:'.implode(',', array_keys($defaults))]];
        foreach ($defaults as $key => $value) {
            $rules['themes.*.tokens.'.$key] = is_int($value) ? ['required', 'numeric', 'min:0', 'max:24'] : ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        $data = $request->validate($rules);
        $offered = array_filter($data['themes'], fn ($theme) => $theme['enabled'] && $theme['id'] === $data['default']);
        if (! $offered) {
            throw ValidationException::withMessages(['default' => __('Choose an offered theme as the default.')]);
        }
        AppSetting::updateOrCreate(['key' => 'themes'], ['value' => $data]);

        return back()->with('success', __('Themes saved.'));
    }
}
