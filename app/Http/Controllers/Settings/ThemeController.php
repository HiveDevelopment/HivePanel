<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\PanelThemes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['theme_id' => ['nullable', Rule::in(array_column(PanelThemes::offered()['themes'], 'id'))]]);
        $request->user()->forceFill(['theme_id' => $data['theme_id'] ?? null])->save();
        return back()->with('success', 'Theme preference saved.');
    }
}
