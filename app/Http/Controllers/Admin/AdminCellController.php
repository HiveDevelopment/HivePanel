<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\CellDomain;
use App\Models\CellSplit;
use App\Models\CellSubdomain;
use App\Models\Comb;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Cells\CellProvisioningService;
use App\Services\Cells\CellShutdownService;
use App\Services\Cells\CellSyncService;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class AdminCellController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Cells/Index', [
            'cells' => Cell::query()
                ->with([
                    'owner:id,name,email',
                    'node:id,name,location',
                    'allocation:id,cell_id,ip,port,alias',
                    'allocations:id,cell_id,ip,port,alias',
                ])
                ->latest()
                ->get()
                ->map(fn (Cell $cell) => $this->cellPayload($cell)),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Cells/Create', [
            'nodes' => Node::query()
                ->where('is_active', true)
                ->withCount([
                    'allocations as available_allocations_count' => fn ($query) => $query
                        ->whereNull('cell_id')
                        ->where('is_reserved', false),
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (Node $node) => [
                    'id' => $node->id,
                    'name' => $node->name,
                    'location' => $node->location,
                    'public_fqdn' => $node->public_fqdn,
                    'available_allocations_count' => $node->available_allocations_count,
                ]),

            'combs' => Comb::query()
                ->orderBy('game')
                ->orderBy('name')
                ->get()
                ->map(fn (Comb $comb) => [
                    'id' => $comb->id,
                    'external_id' => $comb->external_id,
                    'name' => $comb->name,
                    'game' => $comb->game,
                    'category' => $comb->category(),
                    'group' => $comb->group(),
                    'source' => $comb->source,
                    'data' => [...(array) $comb->data, 'variables_schema' => app(CellShutdownService::class)->variableSchema((array) $comb->data)],
                ]),

            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function allocations(Node $node)
    {
        return response()->json([
            'allocations' => $node->allocations()
                ->whereNull('cell_id')
                ->where('is_reserved', false)
                ->orderBy('ip')
                ->orderBy('port')
                ->get()
                ->map(fn (NodeAllocation $allocation) => [
                    'id' => $allocation->id,
                    'ip' => $allocation->ip,
                    'port' => $allocation->port,
                    'alias' => $allocation->alias,
                    'label' => "{$allocation->ip}:{$allocation->port}".($allocation->alias ? " ({$allocation->alias})" : ''),
                ]),
        ]);
    }

    public function store(
        Request $request,
        CellProvisioningService $provisioning,
    ) {
        $data = $request->validate([
            'node_id' => ['required', 'exists:nodes,id'],
            'allocation_id' => ['required', 'exists:node_allocations,id'],
            'additional_allocation_ids' => ['nullable', 'array'],
            'additional_allocation_ids.*' => ['string', 'exists:node_allocations,id'],

            'name' => ['required', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_on_completion' => ['boolean'],

            'comb_id' => ['required', 'exists:combs,id'],
            'version' => ['nullable', 'string', 'max:255'],
            'skip_install_script' => ['boolean'],

            'memory_mb' => ['required', 'integer', 'min:0'],
            'overhead_memory_mb' => ['required', 'integer', 'min:0'],
            'swap_mb' => ['required', 'integer', 'min:-1'],
            'disk_mb' => ['required', 'integer', 'min:0'],
            'cpu_percent' => ['required', 'integer', 'min:0', 'max:1000'],
            'cpu_pinning' => ['nullable', 'string', 'max:255'],
            'io_weight' => ['required', 'integer', 'min:10', 'max:1000'],
            'oom_killer' => ['boolean'],
            'exclude_from_resource_calculation' => ['boolean'],

            'database_limit' => ['nullable', 'integer', 'min:0'],
            'allocation_limit' => ['nullable', 'integer', 'min:0'],
            'backup_limit' => ['nullable', 'integer', 'min:0'],
            'backup_storage_mb' => ['nullable', 'integer', 'min:0'],

            'docker_image' => ['nullable', 'string', 'max:500'],
            'startup_command' => ['nullable', 'string', 'max:1000'],

            'variables' => ['nullable', 'array'],
            'variables.shutdown_command' => ['nullable', 'string', 'max:1000', 'not_regex:/[\r\n\x00]/'],
        ]);

        $owner = ! empty($data['owner_email'])
            ? User::where('email', $data['owner_email'])->firstOrFail()
            : $request->user();

        $provisioning->provision(
            $data,
            $owner,
        );

        return redirect()
            ->route('admin.cells.index')
            ->with('success', __('Cell created successfully.'));
    }

    public function show(Cell $cell)
    {
        $cell->load([
            'owner:id,name,email',
            'node:id,name,location,public_fqdn',
            'allocation:id,cell_id,ip,port,alias,is_reserved',
            'allocations:id,cell_id,ip,port,alias,is_reserved',
        ]);

        return Inertia::render('Admin/Cells/Show', [
            'cell' => $this->cellPayload($cell),
        ]);
    }

    public function edit(Cell $cell, CellNodeClient $cells)
    {
        $cell->load([
            'owner:id,name,email',
            'node',
            'allocation:id,cell_id,node_id,ip,port,alias,is_reserved',
            'allocations:id,cell_id,node_id,ip,port,alias,is_reserved',
        ]);

        $editState = [
            'status' => 'ready',
            'editable' => true,
            'message' => 'The Cell is offline and its definition can be updated.',
        ];

        if (! $cell->node) {
            $editState = [
                'status' => 'error',
                'editable' => false,
                'message' => 'This Cell is not assigned to a node.',
            ];
        } elseif (! $cell->daemon_id) {
            $editState = [
                'status' => 'error',
                'editable' => false,
                'message' => 'This Cell does not have a daemon ID.',
            ];
        } else {
            try {
                $worker = $cells->cellForSync($cell);

                if (! $worker['reachable']) {
                    $editState = [
                        'status' => 'unreachable',
                        'editable' => false,
                        'message' => 'The assigned Worker is currently unreachable.',
                    ];
                } elseif (! $worker['exists']) {
                    $editState = [
                        'status' => 'missing',
                        'editable' => false,
                        'message' => 'This Cell is missing from the assigned Worker.',
                    ];
                } elseif ($this->workerCellIsRunning($worker['cell'] ?? [])) {
                    $editState = [
                        'status' => 'running',
                        'editable' => true,
                        'message' => 'The Cell is running. Saved changes are queued and apply on the next start; restart to publish new allocations.',
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);

                $editState = [
                    'status' => 'error',
                    'editable' => false,
                    'message' => $exception->getMessage()
                        ?: 'The Worker state could not be determined.',
                ];
            }
        }

        return Inertia::render('Admin/Cells/Edit', [
            'cell' => $this->cellPayload($cell),
            'editState' => $editState,
            'variableSchema' => collect(app(CellShutdownService::class)->variableSchema((array) (Comb::query()->find(data_get($cell->metadata, 'comb_id'))?->data ?? [])))
                ->filter(fn ($variable) => is_array($variable) && ! empty($variable['name']))
                ->values()
                ->all(),
            'allocations' => $cell->node
                ? $cell->node->allocations()
                    ->where('is_reserved', false)
                    ->where(function ($query) use ($cell) {
                        $query->whereNull('cell_id')
                            ->orWhere('cell_id', $cell->id);
                    })
                    ->orderBy('ip')
                    ->orderBy('port')
                    ->get()
                    ->map(fn (NodeAllocation $allocation) => [
                        'id' => $allocation->id,
                        'ip' => $allocation->ip,
                        'port' => $allocation->port,
                        'alias' => $allocation->alias,
                        'assigned_to_cell' => (string) $allocation->cell_id === (string) $cell->id,
                        'primary' => (string) $allocation->id === (string) $cell->primary_allocation_id,
                        'label' => "{$allocation->ip}:{$allocation->port}".($allocation->alias ? " ({$allocation->alias})" : ''),
                    ])
                    ->values()
                : [],
        ]);
    }

    public function update(Request $request, Cell $cell, CellNodeClient $cells, CellSyncService $sync, AuditLogger $audit)
    {
        $cell->loadMissing([
            'node',
            'allocation',
            'allocations',
        ]);

        abort_unless($cell->node, 422, 'This Cell is not assigned to a node.');
        abort_unless($cell->daemon_id, 422, 'This Cell does not have a daemon ID.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'memory_mb' => ['required', 'integer', 'min:0'],
            'cpu_percent' => ['required', 'integer', 'min:0', 'max:1000'],
            'disk_mb' => ['required', 'integer', 'min:0'],
            'allocation_id' => ['required', 'exists:node_allocations,id'],
            'additional_allocation_ids' => ['nullable', 'array'],
            'additional_allocation_ids.*' => ['string', 'exists:node_allocations,id'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_scalar($value)) {
                    $fail('The :attribute field must be text, a number or a boolean.');
                }
            }],
        ]);

        $request->validate([
            'variables.shutdown_command' => ['nullable', 'string', 'max:1000', 'not_regex:/[\r\n\x00]/'],
        ]);

        $comb = Comb::query()->find(data_get($cell->metadata, 'comb_id'));
        $schema = collect($comb?->data['variables_schema'] ?? [])->filter(fn ($variable) => is_array($variable) && ! empty($variable['name']));
        $editableNames = $schema->pluck('name')->push('shutdown_command')->reject(fn ($name) => in_array($name, ['memory', 'server_ip', 'server_port'], true))->all();
        $submittedVariables = collect($data['variables'] ?? [])->only($editableNames)->map(fn ($value) => (string) ($value ?? ''))->all();
        foreach ($schema as $variable) {
            $name = $variable['name'];
            if (! in_array($name, $editableNames, true)) {
                continue;
            }
            if (! empty($variable['required']) && trim($submittedVariables[$name] ?? '') === '') {
                return back()->withErrors(["variables.{$name}" => __('This variable is required.')]);
            }
            if (in_array($variable['type'] ?? '', ['number', 'integer'], true) && isset($submittedVariables[$name]) && $submittedVariables[$name] !== '' && ! is_numeric($submittedVariables[$name])) {
                return back()->withErrors(["variables.{$name}" => __('Enter a valid number.')]);
            }
        }

        $worker = $cells->cellForSync($cell);

        if (! $worker['reachable']) {
            return back()->withErrors([
                'worker' => __('The assigned Worker is currently unreachable.'),
            ]);
        }

        if (! $worker['exists']) {
            return back()->withErrors([
                'worker' => __('This Cell is missing from the assigned Worker. Recover the Worker Cell before editing its definition.'),
            ]);
        }

        $running = $this->workerCellIsRunning($worker['cell'] ?? []);

        $additionalIds = collect($data['additional_allocation_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => $id !== (string) $data['allocation_id'])
            ->unique()
            ->values();

        $oldMetadata = $cell->metadata ?? [];
        $oldAllocationIds = $cell->allocations
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values()
            ->all();

        $old = [
            'name' => $cell->name,
            'memory_mb' => (int) data_get($oldMetadata, 'limits.memory_mb', 1024),
            'cpu_percent' => (int) data_get($oldMetadata, 'limits.cpu_percent', 100),
            'disk_mb' => (int) data_get($oldMetadata, 'limits.disk_mb', 0),
            'primary_allocation_id' => (string) $cell->primary_allocation_id,
            'allocation_ids' => $oldAllocationIds,
            'variables' => (array) data_get($oldMetadata, 'variables', []),
        ];

        try {
            DB::transaction(function () use ($cell, $data, $additionalIds, $oldMetadata, $submittedVariables): void {
                $lockedCell = Cell::query()->whereKey($cell->getKey())->lockForUpdate()->firstOrFail();
                $oldMetadata = (array) $lockedCell->metadata;
                $requestedIds = collect([
                    (string) $data['allocation_id'],
                    ...$additionalIds->all(),
                ])->unique()->values();

                $requestedAllocations = NodeAllocation::query()
                    ->whereIn('id', $requestedIds)
                    ->where('node_id', $cell->node_id)
                    ->where('is_reserved', false)
                    ->where(function ($query) use ($cell) {
                        $query->whereNull('cell_id')
                            ->orWhere('cell_id', $cell->id);
                    })
                    ->lockForUpdate()
                    ->get();

                abort_if(
                    $requestedAllocations->count() !== $requestedIds->count(),
                    422,
                    'One or more selected allocations are not available on this node.'
                );

                $primaryAllocation = $requestedAllocations
                    ->first(fn (NodeAllocation $allocation) => (string) $allocation->id === (string) $data['allocation_id']);

                abort_unless($primaryAllocation, 422, 'The selected primary allocation is not available.');

                if (CellDomain::where('cell_id', $cell->id)->whereNotIn('allocation_id', $requestedIds)->exists()) {
                    throw ValidationException::withMessages(['allocation_id' => __('Remove or retarget domain and proxy routes before releasing their allocation.')]);
                }

                // Retain previous ownership until the Worker acknowledges the
                // new definition; a concurrent start can still use those ports.

                NodeAllocation::query()
                    ->whereIn('id', $requestedIds)
                    ->update([
                        'cell_id' => $cell->id,
                    ]);

                $additionalAllocations = $requestedAllocations
                    ->reject(fn (NodeAllocation $allocation) => (string) $allocation->id === (string) $primaryAllocation->id)
                    ->sortBy([
                        ['ip', 'asc'],
                        ['port', 'asc'],
                    ])
                    ->values();

                $metadata = $oldMetadata;
                $metadata['desired_allocation_ids'] = $requestedIds->all();

                $metadata['limits'] = [
                    ...(array) data_get($metadata, 'limits', []),
                    'memory_mb' => (int) $data['memory_mb'],
                    'cpu_percent' => (int) $data['cpu_percent'],
                    'disk_mb' => (int) $data['disk_mb'],
                ];

                $metadata['variables'] = [
                    ...(array) data_get($metadata, 'variables', []),
                    ...$submittedVariables,
                    'memory' => (string) $data['memory_mb'],
                    'server_ip' => $primaryAllocation->ip,
                    'server_port' => (string) $primaryAllocation->port,
                ];

                $metadata['allocation'] = [
                    'id' => $primaryAllocation->id,
                    'ip' => $primaryAllocation->ip,
                    'port' => $primaryAllocation->port,
                    'alias' => $primaryAllocation->alias,
                    'primary' => true,
                ];

                $metadata['additional_allocations'] = $additionalAllocations
                    ->map(fn (NodeAllocation $allocation) => [
                        'id' => $allocation->id,
                        'ip' => $allocation->ip,
                        'port' => $allocation->port,
                        'alias' => $allocation->alias,
                    ])
                    ->all();

                $cell->forceFill([
                    'name' => $data['name'],
                    'primary_allocation_id' => $primaryAllocation->id,
                    'metadata' => $metadata,
                ])->save();
            });

            $cell->unsetRelation('allocation');
            $cell->unsetRelation('allocations');
            $cell->load([
                'node',
                'allocation',
                'allocations',
            ]);

            $cell->invalidateWorkerSync();

            $newAllocationIds = $cell->allocations
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->sort()
                ->values()
                ->all();

            $changedFields = collect([
                'name' => [$old['name'], $data['name']],
                'memory_mb' => [$old['memory_mb'], (int) $data['memory_mb']],
                'cpu_percent' => [$old['cpu_percent'], (int) $data['cpu_percent']],
                'disk_mb' => [$old['disk_mb'], (int) $data['disk_mb']],
                'primary_allocation_id' => [$old['primary_allocation_id'], (string) $cell->primary_allocation_id],
                'allocations' => [$old['allocation_ids'], $newAllocationIds],
                'variables' => [$old['variables'], (array) data_get($cell->metadata, 'variables', [])],
            ])->filter(fn (array $values) => $values[0] !== $values[1])
                ->keys()
                ->values()
                ->all();

            $cells->updateCellDefinition($cell);

            $syncResult = $sync->inspect($cell->fresh([
                'node',
                'allocation',
                'allocations',
            ]));

            if (! $syncResult['synced']) {
                $audit->log(
                    AuditEvent::SERVER_UPDATED,
                    $cell,
                    "Server \"{$cell->name}\" was updated, but the Worker remains out of sync.",
                    [
                        'cell_id' => $cell->id,
                        'changed_fields' => $changedFields,
                        'worker_sync_status' => $syncResult['status'],
                        'differences' => $syncResult['differences'] ?? [],
                    ]
                );

                return redirect()->route('admin.cells.show', $cell)->withErrors([
                    'worker' => __('HivePanel saved the changes, but the Worker definition is still out of sync.'),
                ]);
            }

            $audit->log(
                AuditEvent::SERVER_UPDATED,
                $cell,
                "Server \"{$cell->name}\" was updated.",
                [
                    'cell_id' => $cell->id,
                    'changed_fields' => $changedFields,
                    'worker_sync_status' => 'synced',
                    'primary_allocation_id' => $cell->primary_allocation_id,
                    'allocation_ids' => $newAllocationIds,
                ]
            );

            return redirect()->route('admin.cells.show', $cell)->with('success', $running ? 'Cell changes saved. Restart the Cell to apply them, including new TCP/UDP allocations.' : 'Cell updated successfully.');
        } catch (Throwable $exception) {
            report($exception);

            try {
                $sync->inspect($cell->fresh([
                    'node',
                    'allocation',
                    'allocations',
                ]));
            } catch (Throwable $inspectionException) {
                report($inspectionException);
            }

            return back()->withErrors([
                'worker' => $exception->getMessage() ?: 'The Cell could not be updated.',
            ]);
        }
    }

    public function destroy(Cell $cell, CellNodeClient $cells, AuditLogger $audit)
    {
        abort_if(CellSplit::where('parent_id', $cell->id)->orWhere('child_id', $cell->id)->exists(), 409, 'Delete or merge this Cell through Server Splitter before deleting it directly.');
        abort_if(CellSubdomain::where('cell_id', $cell->id)->exists(), 409, 'Remove generated subdomains before deleting this Cell.');
        $cell->loadMissing([
            'node',
            'allocations',
        ]);

        if ($cell->daemon_id && $cell->node) {
            try {
                $cells->deleteCell($cell);
            } catch (RequestException $exception) {
                $message = 'The worker could not delete this Cell. Please try again.';
                if ($exception->response->status() === 400 && str_contains(strtolower($exception->response->body()), 'cell must be stopped')) {
                    $message = 'Stop the Cell before deleting it.';
                } else {
                    report($exception);
                }

                return back()->withErrors(['worker' => $message]);
            } catch (ConnectionException $exception) {
                report($exception);

                return back()->withErrors(['worker' => __('The worker could not be reached. The Cell has not been deleted. Check the node connection and try again.')]);
            } catch (Throwable $exception) {
                report($exception);

                return back()->withErrors(['worker' => __('The Cell could not be deleted. Please try again.')]);
            }
        }

        DB::transaction(function () use ($cell, $audit) {
            $name = $cell->name;
            $id = $cell->id;

            $cell->forceFill([
                'primary_allocation_id' => null,
            ])->save();

            $cell->allocations()->update([
                'cell_id' => null,
            ]);

            $cell->delete();

            $audit->log(
                AuditEvent::SERVER_DELETED,
                null,
                "Server \"{$name}\" was deleted.",
                [
                    'cell_id' => $id,
                ]
            );
        });

        return redirect()->route('admin.cells.index');
    }

    private function workerCellIsRunning(array $workerCell): bool
    {
        if (($workerCell['running'] ?? false) === true) {
            return true;
        }

        return strtolower((string) ($workerCell['status'] ?? '')) === 'running';
    }

    private function cellPayload(Cell $cell): array
    {
        return [
            'id' => $cell->getRouteKey(),
            'name' => $cell->name,
            'comb' => $cell->comb,
            'daemon_id' => $cell->daemon_id,

            'install_status' => $cell->install_status->value,
            'install_status_label' => $cell->install_status->label(),
            'install_failure_reason' => $cell->install_failure_reason,
            'installed_at' => $cell->installed_at?->toISOString(),

            'worker_sync' => [
                'status' => $cell->worker_sync_status,
                'message' => $cell->worker_sync_message,
                'differences' => $cell->worker_sync_differences ?? [],
                'checked_at' => $cell->worker_sync_checked_at?->toISOString(),
            ],

            'worker_recovery' => [
                'required' => $cell->worker_recovery_required,
                'recreated_at' => $cell->worker_recreated_at?->toISOString(),
            ],

            'owner' => $cell->owner ? [
                'id' => $cell->owner->id,
                'name' => $cell->owner->name,
                'email' => $cell->owner->email,
            ] : null,

            'node' => $cell->node ? [
                'id' => $cell->node->id,
                'name' => $cell->node->name,
                'location' => $cell->node->location,
                'public_fqdn' => $cell->node->public_fqdn ?? null,
            ] : null,

            'allocation' => $cell->allocation ? [
                'id' => $cell->allocation->id,
                'ip' => $cell->allocation->ip,
                'port' => $cell->allocation->port,
                'alias' => $cell->allocation->alias,
            ] : null,

            'additional_allocations' => $cell->allocations
                ->filter(fn (NodeAllocation $allocation) => (string) $allocation->id !== (string) $cell->primary_allocation_id)
                ->filter(fn (NodeAllocation $allocation) => ! is_array(data_get($cell->metadata, 'desired_allocation_ids')) || in_array((string) $allocation->id, array_map('strval', data_get($cell->metadata, 'desired_allocation_ids')), true))
                ->sortBy([
                    ['ip', 'asc'],
                    ['port', 'asc'],
                ])
                ->map(fn (NodeAllocation $allocation) => [
                    'id' => $allocation->id,
                    'ip' => $allocation->ip,
                    'port' => $allocation->port,
                    'alias' => $allocation->alias,
                ])
                ->values()
                ->all(),

            'limits' => $cell->limits,
            'variables' => $cell->variables,
            'metadata' => $cell->metadata,

            'created_at' => $cell->created_at?->toISOString(),
            'updated_at' => $cell->updated_at?->toISOString(),
        ];
    }
}
