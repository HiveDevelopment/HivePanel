<?php

namespace Tests\Unit;

use App\Http\Controllers\Cells\CellNetworkController;
use App\Models\AuditLog;
use App\Models\Cell;
use App\Models\NodeAllocation;
use App\Services\AuditLogger;
use App\Services\Cells\CellSyncService;
use App\Services\Node\CellNodeClient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CellNetworkValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('cells', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('node_id');
            $table->string('primary_allocation_id');
            $table->string('install_status');
            $table->text('metadata');
            $table->timestamps();
        });
        Schema::create('node_allocations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('node_id');
            $table->string('cell_id')->nullable();
            $table->string('ip');
            $table->integer('port');
            $table->boolean('is_reserved')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    private function assertRejected(array $allocation, array $ids, int $limit = 2): void
    {
        $cell = new Cell;
        $cell->forceFill(['id' => 'cell', 'node_id' => 'node', 'primary_allocation_id' => 'own', 'install_status' => 'installed', 'metadata' => ['feature_limits' => ['allocation_limit' => $limit]]])->save();
        (new NodeAllocation)->forceFill(['id' => 'own', 'node_id' => 'node', 'cell_id' => 'cell', 'ip' => '127.0.0.1', 'port' => 7777])->save();
        (new NodeAllocation)->forceFill(['id' => 'other', 'ip' => '127.0.0.1', 'port' => 7778, ...$allocation])->save();
        $controller = new class($cell) extends CellNetworkController
        {
            public function __construct(private Cell $fixture) {}

            protected function panelCellOrFail(string $id): Cell
            {
                return $this->fixture;
            }

            protected function abortIfLocked(Cell $cell, CellNodeClient $cells): void {}
        };
        $client = Mockery::mock(CellNodeClient::class);
        $sync = Mockery::mock(CellSyncService::class);
        $sync->shouldNotReceive('repair');
        try {
            $controller->update('cell', Request::create('/', 'PATCH', ['allocation_ids' => $ids, 'primary_id' => 'own']), $client, $sync);
            $this->fail('Invalid allocation was accepted.');
        } catch (ValidationException) {
            $this->assertSame('cell', NodeAllocation::find('own')->cell_id);
            $this->assertSame($allocation['cell_id'] ?? null, NodeAllocation::find('other')->cell_id);
        }
    }

    public function test_cannot_claim_another_cells_port(): void
    {
        $this->assertRejected(['node_id' => 'node', 'cell_id' => 'someone-else'], ['own', 'other']);
    }

    public function test_cannot_claim_ports_from_another_node(): void
    {
        $this->assertRejected(['node_id' => 'different-node'], ['own', 'other']);
    }

    public function test_allocation_limit_is_enforced(): void
    {
        $this->assertRejected(['node_id' => 'node'], ['own', 'other'], 1);
    }

    public function test_notes_cannot_be_changed_on_another_cells_allocation(): void
    {
        $cell = new Cell;
        $cell->id = 'cell';
        $cell->node_id = 'node';
        (new NodeAllocation)->forceFill(['id' => 'other', 'node_id' => 'node', 'cell_id' => 'other-cell', 'ip' => '127.0.0.1', 'port' => 1234, 'notes' => 'Keep'])->save();
        $controller = new class($cell) extends CellNetworkController
        {
            public function __construct(private Cell $fixture) {}

            protected function panelCellOrFail(string $id): Cell
            {
                return $this->fixture;
            }
        };
        try {
            $controller->updateNotes('cell', 'other', Request::create('/', 'PATCH', ['notes' => 'Changed']));
            $this->fail('Another Cell notes were editable.');
        } catch (ModelNotFoundException) {
            $this->assertSame('Keep', NodeAllocation::find('other')->notes);
        }
    }

    public function test_notes_save_on_owned_allocation_without_worker_sync(): void
    {
        $cell = new Cell;
        $cell->id = 'cell';
        $cell->node_id = 'node';
        (new NodeAllocation)->forceFill(['id' => 'own', 'node_id' => 'node', 'cell_id' => 'cell', 'ip' => '127.0.0.1', 'port' => 1234])->save();
        $controller = new class($cell) extends CellNetworkController
        {
            public function __construct(private Cell $fixture) {}

            protected function panelCellOrFail(string $id): Cell
            {
                return $this->fixture;
            }
        };
        $logger = Mockery::mock(AuditLogger::class);
        $logger->shouldReceive('log')->once()->andReturn(new AuditLog);
        $this->app->instance(AuditLogger::class, $logger);
        $controller->updateNotes('cell', 'own', Request::create('/', 'PATCH', ['notes' => "Voice chat\nUDP port"]));
        $this->assertSame("Voice chat\nUDP port", NodeAllocation::find('own')->notes);
    }
}
