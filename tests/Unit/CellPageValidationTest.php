<?php

namespace Tests\Unit;

use App\Models\Cell;
use App\Services\Cells\CellShutdownService;
use App\Services\Cells\CellSyncService;
use App\Services\Node\CellNodeClient;
use App\Http\Controllers\Cells\CellStartupController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CellPageValidationTest extends TestCase
{
    public function test_startup_rejects_reserved_and_unknown_variables(): void
    {
        $cell = new Cell(['install_status' => 'installed']); $cell->id = 'cell';
        $controller = new class($cell) extends CellStartupController {
            public function __construct(private Cell $fixture) {}
            protected function panelCellOrFail(string $id): Cell { return $this->fixture; }
            protected function abortIfLocked(Cell $cell, CellNodeClient $cells): void {}
        };
        $client = Mockery::mock(CellNodeClient::class);
        $client->shouldReceive('definitionPayload')->andReturn(['comb_data' => ['variables_schema' => [['name' => 'server_port'], ['name' => 'secret', 'user_editable' => false]]]]);
        $this->expectException(ValidationException::class);
        $controller->update('cell', Request::create('/', 'PATCH', ['variables' => ['server_port' => '22']]), $client, new CellShutdownService(), Mockery::mock(CellSyncService::class));
    }

    public function test_new_cell_routes_have_distinct_read_and_write_permissions(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertContains('cell.permission:startup.update', $routes->getByName('cells.startup.update')->gatherMiddleware());
        $this->assertContains('cell.permission:network.update', $routes->getByName('cells.network.update')->gatherMiddleware());
        $this->assertContains('cell.permission:settings.view', $routes->getByName('cells.startup.index')->gatherMiddleware());
    }
}
