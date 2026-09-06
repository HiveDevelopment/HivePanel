<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use Illuminate\Http\Request;

class AdminCellDatabaseLimitController extends Controller
{
    public function update(
        Cell $cell,
        Request $request,
    ) {
        $data = $request->validate([
            'database_limit' => [
                'required',
                'integer',
                'min:0',
                'max:65535',
            ],
        ]);

        $cell->forceFill([
            'database_limit' =>
                $data['database_limit'],
        ])->save();

        return back()->with(
            'success',
            'Cell database limit updated.'
        );
    }
}