<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NectarEventController extends Controller
{
    public function subscribe(Request $request, string $id)
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'list', 'max:3'],
            'events.*' => ['required', 'string', 'distinct', 'in:cell.created,cell.updated,cell.deleted'],
        ]);
        $extension = DB::table('nectar_extensions')->where('extension_id', $id)->where('enabled', true)->first();
        abort_unless($extension, 403);
        $manifest = json_decode($extension->manifest, true);
        $declared = $manifest['events']['subscribe'] ?? [];
        abort_unless(is_array($declared) && ! array_diff($data['events'], $declared), 403);
        abort_unless(in_array('cells.read', $manifest['permissions'] ?? [], true), 403);
        return response()->json(['ok' => true])->header('Cache-Control', 'private, no-store');
    }
}
