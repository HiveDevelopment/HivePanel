<?php

namespace Tests\Unit;

use App\Models\Cell;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Services\Cells\CellSyncService;
use App\Services\Node\CellNodeClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CellAllocationQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('cells', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('node_id');
            $table->string('daemon_id');
            $table->string('name');
            $table->string('comb');
            $table->text('metadata');
            $table->string('worker_sync_status')->nullable();
            $table->text('worker_sync_message')->nullable();
            $table->text('worker_sync_differences')->nullable();
            $table->timestamp('worker_sync_checked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('node_allocations', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('node_id');
            $table->string('cell_id')->nullable();
            $table->string('ip');
            $table->integer('port');
            $table->boolean('is_reserved')->default(false);
            $table->timestamps();
        });
    }

    private function fixture(bool $pending, bool $matches = true): array
    {
        $cell = new Cell();
        $cell->forceFill(['id' => 'cell', 'node_id' => 'node', 'daemon_id' => 'daemon', 'name' => 'Cell', 'comb' => 'generic', 'metadata' => ['desired_allocation_ids' => ['new']]])->save();
        foreach (['old' => 7777, 'new' => 7778] as $id => $port) {
            $allocation = new NodeAllocation();
            $allocation->forceFill(['id' => $id, 'node_id' => 'node', 'cell_id' => 'cell', 'ip' => '127.0.0.1', 'port' => $port])->save();
        }
        $cell->setRelation('node', new Node());
        $cell->setRelation('allocation', NodeAllocation::find('new'));
        $cell->setRelation('allocations', NodeAllocation::all());
        $payload = ['name' => 'Cell', 'comb' => 'generic', 'comb_data' => [], 'variables' => [], 'allocation' => ['ip' => '127.0.0.1', 'port' => 7778], 'additional_allocations' => [], 'limits' => []];
        $client = Mockery::mock(CellNodeClient::class);
        $client->shouldReceive('definitionPayload')->andReturn($payload);
        $client->shouldReceive('cellForSync')->andReturn(['reachable' => true, 'exists' => true, 'cell' => [...$payload, 'name' => $matches ? 'Cell' : 'Old definition', 'status' => 'running', 'configuration_pending' => $pending]]);

        return [$cell, new CellSyncService($client)];
    }

    public function test_live_removed_allocation_stays_owned_while_changes_are_pending(): void
    {
        [$cell, $sync] = $this->fixture(true);
        $result = $sync->inspect($cell);
        $this->assertTrue($result['synced']);
        $this->assertTrue($result['configuration_pending']);
        $this->assertSame('cell', NodeAllocation::find('old')->cell_id);
    }

    public function test_old_allocation_is_released_after_worker_confirms_applied_definition(): void
    {
        [$cell, $sync] = $this->fixture(false);
        $sync->inspect($cell);
        $this->assertNull(NodeAllocation::find('old')->cell_id);
        $this->assertSame('cell', NodeAllocation::find('new')->cell_id);
    }

    public function test_unacknowledged_definition_never_releases_ports(): void
    {
        [$cell, $sync] = $this->fixture(false, false);
        $result = $sync->inspect($cell);
        $this->assertFalse($result['synced']);
        $this->assertSame('cell', NodeAllocation::find('old')->cell_id);
    }

    public function test_stale_inspection_does_not_release_a_newer_edits_allocation(): void
    {
        [$cell, $sync] = $this->fixture(false);
        Cell::query()->whereKey('cell')->update(['metadata' => json_encode(['desired_allocation_ids' => ['old', 'new']])]);
        $sync->inspect($cell);
        $this->assertSame('cell', NodeAllocation::find('old')->cell_id);
    }
}
