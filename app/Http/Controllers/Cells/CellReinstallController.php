<?php

namespace App\Http\Controllers\Cells;

use App\Enums\CellInstallStatus;
use App\Models\Comb;
use App\Services\Cells\CellReinstallService;
use App\Services\Cells\CellToolsPolicy;
use App\Services\Node\CellNodeClient;
use App\Services\Node\CellToolsNodeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CellReinstallController extends CellBaseController
{
    public function show(
        string $id,
        CellNodeClient $cells,
    ): Response {
        $cell = $this->panelCellOrFail($id);

        $workerCell = $this->frontendCell(
            $cell,
            $cells,
        );

        return Inertia::render('Cells/Reinstall', [
            'cell' => $workerCell,
            'switching' => request()->routeIs('cells.switch-comb.*'),
            'saveInstanceAllowed' => $cell->userCan(request()->user(), 'instances.update'),

            'combs' => app(CellToolsPolicy::class)->offered($cell)
                ->map(fn (Comb $comb) => [
                    'id' => $comb->id,
                    'external_id' => $comb->external_id,
                    'name' => $comb->name,
                    'game' => $comb->game,
                    'source' => $comb->source,
                    'data' => $comb->data,
                ])
                ->values(),
        ]);
    }

    public function store(
        Request $request,
        string $id,
        CellNodeClient $cells,
        CellReinstallService $reinstall,
    ): RedirectResponse {
        $cell = $this->panelCellOrFail($id);

        $workerCell = $cells->cell($cell);

        if (($workerCell['error'] ?? false) === true) {
            return back()->withErrors([
                'reinstall' => $workerCell['message']
                    ?? 'The node could not be contacted.',
            ]);
        }

        if ($this->workerCellIsRunning($workerCell)) {
            return back()->withErrors([
                'reinstall' => __('Stop the cell before reinstalling.'),
            ]);
        }

        $validated = $request->validate([
            'comb_id' => [
                'required',
                'exists:combs,id',
            ],

            'variables' => [
                'nullable',
                'array',
            ],

            'variables.*' => ['nullable', 'string', 'max:4096'],
            'image' => ['nullable', 'string', 'max:251'],

            'start_on_completion' => [
                'sometimes',
                'boolean',
            ],

            'save_instance' => ['sometimes', 'boolean'],
            'confirmation' => [
                'required',
                'string',
            ],
        ]);

        if (
            trim($validated['confirmation'])
            !== $cell->name
        ) {
            return back()
                ->withErrors([
                    'confirmation' => __('The confirmation does not match the cell name.'),
                ])
                ->withInput();
        }

        $comb = Comb::query()
            ->findOrFail(
                $validated['comb_id'],
            );

        app(CellToolsPolicy::class)->assertComb($cell, $comb, $request->user());
        if ($request->boolean('save_instance')) {
            abort_unless($cell->userCan($request->user(), 'instances.update'), 403);
            app(CellToolsNodeClient::class)->request($cell, 'POST', '/instances?wait=1', ['name' => 'Before switch '.now()->format('Y-m-d H:i:s'), 'empty' => false]);
        }
        try {
            $reinstall->reinstall(
                cell: $cell,
                comb: $comb,
                variables: $validated['variables'] ?? [],
                image: $validated['image'] ?? null,
                startAfterInstall: (bool) (
                    $validated['start_on_completion']
                    ?? false
                ),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withErrors([
                    'reinstall' => $exception->getMessage()
                        ?: 'The cell could not be reinstalled.',
                ])
                ->withInput();
        }

        return back()->with(
            'success',
            __('The cell reinstall has been queued.'),
        );
    }

    public function retry(
        Request $request,
        string $id,
        CellNodeClient $cells,
        CellReinstallService $reinstall,
    ): RedirectResponse {
        $cell = $this->panelCellOrFail($id);

        if (
            $cell->install_status
            !== CellInstallStatus::FAILED
        ) {
            return back()->withErrors([
                'retry' => __('Only failed installations can be retried.'),
            ]);
        }

        $workerCell = $cells->cell($cell);

        if (($workerCell['error'] ?? false) === true) {
            return back()->withErrors([
                'retry' => $workerCell['message']
                    ?? 'The node could not be contacted.',
            ]);
        }

        if ($this->workerCellIsRunning($workerCell)) {
            return back()->withErrors([
                'retry' => __('Stop the cell before retrying installation.'),
            ]);
        }

        $validated = $request->validate([
            'start_on_completion' => [
                'sometimes',
                'boolean',
            ],
        ]);

        try {
            $reinstall->retry(
                cell: $cell,
                startAfterInstall: (bool) (
                    $validated['start_on_completion']
                    ?? false
                ),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'retry' => $exception->getMessage()
                    ?: 'The installation could not be retried.',
            ]);
        }

        return back()->with(
            'success',
            __('Installation has been queued again.'),
        );
    }

    public function installationStatus(string $id)
    {
        $cell = $this->panelCellOrFail($id);

        return response()->json([
            'install_status' => $cell->install_status?->value
                ?? (string) $cell->install_status,

            'install_status_label' => $cell->install_status?->label()
                ?? ucfirst((string) $cell->install_status),

            'install_failure_reason' => $cell->install_failure_reason,

            'installed_at' => $cell->installed_at?->toIso8601String(),
        ]);
    }

    private function workerCellIsRunning(
        array $workerCell,
    ): bool {
        if (($workerCell['running'] ?? false) === true) {
            return true;
        }

        return strtolower(
            (string) ($workerCell['status'] ?? ''),
        ) === 'running';
    }
}
