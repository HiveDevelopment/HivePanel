<?php

namespace App\Services\Cells;

use App\Jobs\InstallCellJob;
use App\Models\Cell;
use App\Models\CellDomain;
use App\Models\CellSplit;
use App\Models\CellSubdomain;
use App\Models\Comb;
use App\Models\NodeAllocation;
use App\Models\User;
use App\Services\Node\CellNodeClient;
use App\Services\Node\CellToolsNodeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CellSplitterService
{
    public function __construct(private CellNodeClient $nodes, private CellToolsNodeClient $tools, private CellProvisioningService $provision, private CellToolsPolicy $policy) {}

    public static function remaining(array $current, array $requested): array
    {
        $next = $current;
        foreach (['memory_mb', 'disk_mb', 'cpu_percent', 'overhead_memory_mb', 'swap_mb'] as $key) {
            $total = (int) ($current[$key] ?? 0);
            $amount = (int) ($requested[$key] ?? 0);
            if (in_array($key, ['memory_mb', 'disk_mb', 'cpu_percent'], true) && ($total <= 0 || $amount <= 0 || $amount >= $total)) {
                throw ValidationException::withMessages([$key => __('Splitting requires finite limits and must leave resources for the parent.')]);
            }if ($total < 0 || $amount < 0 || $amount > $total) {
                throw ValidationException::withMessages([$key => __('Choose resources within the parent’s allowance.')]);
            }$next[$key] = $total - $amount;
        }

        return $next;
    }

    public function create(Cell $parent, User $owner, array $data): Cell
    {
        $this->policy->owner($parent, $owner);
        $comb = Comb::findOrFail($data['comb_id']);
        $this->policy->assertComb($parent, $comb, $owner);
        $token = (string) Str::uuid();
        $old = null;
        $workerChanged = false;
        $createdChild = null;
        $this->tools->request($parent, 'POST', '/operations/begin', ['token' => $token]);
        try {
            $child = DB::transaction(function () use ($parent, $owner, $data, $token, &$old, &$workerChanged, &$createdChild) {
                $current = Cell::whereKey($parent->id)->lockForUpdate()->firstOrFail();
                $current->load('node', 'allocation', 'allocations');
                $old = $this->nodes->definitionPayload($current);
                if (CellSplit::where('child_id', $current->id)->exists()) {
                    throw ValidationException::withMessages(['split' => __('Nested splits are not allowed.')]);
                }
                if (CellSplit::where('parent_id', $current->id)->count() >= $this->policy->limit($current, 'split_limit')) {
                    throw ValidationException::withMessages(['split' => __('Split limit reached.')]);
                }
                $allocation = NodeAllocation::whereKey($data['allocation_id'])->where('cell_id', $current->id)->where('node_id', $current->node_id)->lockForUpdate()->firstOrFail();
                if ((string) $allocation->id === (string) $current->primary_allocation_id) {
                    throw ValidationException::withMessages(['allocation_id' => __('Choose one of the parent’s additional allocations.')]);
                }
                if (CellDomain::where('cell_id', $current->id)->where('allocation_id', $allocation->id)->exists() || CellSubdomain::where('cell_id', $current->id)->where('allocation_id', $allocation->id)->exists()) {
                    throw ValidationException::withMessages(['allocation_id' => __('Remove domain routes using this allocation before transferring it.')]);
                }
                $resources = array_intersect_key($data, array_flip(['memory_mb', 'disk_mb', 'cpu_percent', 'overhead_memory_mb', 'swap_mb']));
                $metadata = (array) $current->metadata;
                $metadata['limits'] = self::remaining((array) data_get($metadata, 'limits', []), $resources);
                $metadata['variables']['memory'] = (string) $metadata['limits']['memory_mb'];
                $stats = $this->nodes->stats($current);
                if (! ($stats['disk_ready'] ?? false)) {
                    throw ValidationException::withMessages(['disk_mb' => __('Disk usage is still being measured. Refresh and try again.')]);
                }if ((int) ($stats['disk_bytes'] ?? 0) > (int) $metadata['limits']['disk_mb'] * 1024 * 1024) {
                    throw ValidationException::withMessages(['disk_mb' => __('The parent needs enough disk space for all active and saved instance files.')]);
                }
                $desired = array_values(array_filter(data_get($metadata, 'desired_allocation_ids', $current->allocations->pluck('id')->all()), fn ($id) => (string) $id !== (string) $allocation->id));
                $metadata['desired_allocation_ids'] = $desired;
                $current->forceFill(['metadata' => $metadata])->save();
                $payload = $this->nodes->definitionPayload($current);
                $payload['operation_token'] = $token;
                $this->tools->request($current, 'PATCH', '/definition', $payload);
                $workerChanged = true;
                $allocation->update(['cell_id' => null]);
                $current->unsetRelation('allocations');
                $child = $this->provision->provision([...$data, 'node_id' => $current->node_id, 'owner_email' => $owner->email, 'allocation_id' => $allocation->id, 'additional_allocation_ids' => [], 'start_on_completion' => false, 'skip_install_script' => true, 'allocation_limit' => 1, 'database_limit' => 0, 'backup_limit' => 0, 'backup_storage_mb' => 0, 'io_weight' => (int) data_get($metadata, 'limits.io_weight', 500), 'oom_killer' => (bool) data_get($metadata, 'limits.oom_killer', true), 'cpu_pinning' => data_get($metadata, 'limits.cpu_pinning'), 'exclude_from_resource_calculation' => false], $owner);
                $createdChild = $child;
                $childMetadata = (array) $child->metadata;
                $childMetadata['feature_limits']['split_limit'] = 0;
                $child->forceFill(['metadata' => $childMetadata, 'install_status' => 'pending'])->save();
                CellSplit::create(['parent_id' => $current->id, 'child_id' => $child->id, 'resources' => $resources]);

                return $child;
            });
        } catch (Throwable $e) {
            $restored = ! $workerChanged;
            if ($createdChild) {
                try {
                    $this->nodes->deleteCell($createdChild);
                } catch (Throwable $cleanup) {
                    report($cleanup);
                    throw ValidationException::withMessages(['split' => __('Split provisioning was interrupted. The parent remains locked until an administrator reconciles the node.')]);
                }
            }
            if ($workerChanged && $old) {
                try {
                    $old['operation_token'] = $token;
                    $this->tools->request($parent, 'PATCH', '/definition', $old);
                    $restored = true;
                } catch (Throwable $restore) {
                    report($restore);
                }
            }
            if ($restored) {
                try {
                    $this->tools->request($parent, 'POST', '/operations/end', ['token' => $token]);
                } catch (Throwable $unlock) {
                    report($unlock);
                }
            }
            throw $e;
        }
        // Both database rows are committed before unlocking the reduced parent.
        InstallCellJob::dispatch($child->id, false)->afterCommit();
        $this->tools->request($parent, 'POST', '/operations/end', ['token' => $token]);

        return $child;
    }

    public function remove(Cell $parent, CellSplit $split, User $owner): void
    {
        $this->policy->owner($parent, $owner);
        abort_unless((string) $split->parent_id === (string) $parent->id, 404);
        $child = Cell::with('node', 'allocation', 'allocations')->findOrFail($split->child_id);
        $recovery = $split->recovery ?? ['parent_token' => (string) Str::uuid(), 'child_token' => (string) Str::uuid(), 'child_deleted' => false];
        $parentToken = $recovery['parent_token'];
        $childToken = $recovery['child_token'];
        $split->update(['state' => 'merging', 'recovery' => $recovery]);
        $this->tools->request($parent, 'POST', '/operations/begin', ['token' => $parentToken]);
        try {
            if (! $recovery['child_deleted']) {
                $this->tools->request($child, 'POST', '/operations/begin', ['token' => $childToken]);
            }
        } catch (Throwable $e) {
            $this->tools->request($parent, 'POST', '/operations/end', ['token' => $parentToken]);
            throw $e;
        }
        $deleted = (bool) $recovery['child_deleted'];
        try {
            DB::transaction(function () use ($parent, $child, $split, $parentToken, $childToken, &$deleted) {
                $current = Cell::whereKey($parent->id)->lockForUpdate()->firstOrFail();
                $current->load('node', 'allocation', 'allocations');
                $locked = CellSplit::whereKey($split->id)->lockForUpdate()->firstOrFail();
                if (CellDomain::where('cell_id', $child->id)->exists() || CellSubdomain::where('cell_id', $child->id)->exists()) {
                    throw ValidationException::withMessages(['split' => __('Remove this split’s domains and generated subdomains before deleting it.')]);
                }
                // The child is stopped and locked. Delete it before returning its resource budget.
                $deleted = true;
                $this->nodes->deleteCell($child, $childToken);
                $metadata = (array) $current->metadata;
                foreach ($locked->resources as $key => $value) {
                    $metadata['limits'][$key] = (int) data_get($metadata, 'limits.'.$key, 0) + (int) $value;
                }$metadata['variables']['memory'] = (string) $metadata['limits']['memory_mb'];
                $allocations = $child->allocations->pluck('id')->all();
                NodeAllocation::whereIn('id', $allocations)->update(['cell_id' => $current->id]);
                $metadata['desired_allocation_ids'] = array_values(array_unique([...data_get($metadata, 'desired_allocation_ids', $current->allocations->pluck('id')->all()), ...$allocations]));
                $current->forceFill(['metadata' => $metadata])->save();
                $current->load('allocations');
                $payload = $this->nodes->definitionPayload($current);
                $payload['operation_token'] = $parentToken;
                $this->tools->request($current, 'PATCH', '/definition', $payload);
                $locked->delete();
                $child->forceFill(['primary_allocation_id' => null])->save();
                $child->delete();
            });
        } catch (Throwable $e) {
            // If deletion already committed on the Worker, leave both sides locked for recovery.
            // Returning capacity twice would be worse than a visible repair requirement.
            if ($deleted) {
                CellSplit::whereKey($split->id)->update(['state' => 'recovery_required', 'recovery' => [...$recovery, 'child_deleted' => true], 'error' => 'The child was removed but its budget has not been returned. Retry removal to finish.']);
            } else {
                $split->update(['state' => 'active', 'recovery' => null]);
                $this->tools->request($parent, 'POST', '/operations/end', ['token' => $parentToken]);
                $this->tools->request($child, 'POST', '/operations/end', ['token' => $childToken]);
            }
            throw $e;
        }
        $this->tools->request($parent, 'POST', '/operations/end', ['token' => $parentToken]);
    }
}
