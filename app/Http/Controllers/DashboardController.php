<?php

namespace App\Http\Controllers;

use App\Services\Cells\CellDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CellDashboardService $dashboard)
    {
        return Inertia::render('Dashboard', [
            'cells' => fn () => $dashboard->visibleCells($request->user(), true),
            'combs' => [],
        ]);
    }
}
