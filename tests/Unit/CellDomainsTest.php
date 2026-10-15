<?php

namespace Tests\Unit;

use App\Http\Controllers\Cells\CellDomainController;
use App\Models\Cell;
use App\Models\CellDomain;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Services\Node\WebProxyNodeClient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CellDomainsTest extends TestCase
{
    private Cell $cell;

    private string $allocation;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('cells', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('node_id');
            $t->uuid('daemon_id');
            $t->uuid('primary_allocation_id');
            $t->string('install_status');
            $t->text('metadata');
            $t->timestamps();
        });
        Schema::create('node_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('cell_id')->nullable();
            $t->uuid('node_id');
            $t->string('ip');
            $t->integer('port');
            $t->timestamps();
        });
        Schema::create('cell_domains', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('cell_id');
            $t->uuid('node_id');
            $t->uuid('allocation_id');
            $t->string('domain')->unique();
            $t->string('protocol');
            $t->integer('public_port');
            $t->boolean('https');
            $t->boolean('redirect');
            $t->integer('max_body_mb');
            $t->timestamps();
        });
        $this->allocation = (string) Str::uuid();
        $node = new Node(['name' => 'Node']);
        $node->id = (string) Str::uuid();
        $this->cell = new Cell;
        $this->cell->forceFill(['id' => (string) Str::uuid(), 'node_id' => $node->id, 'daemon_id' => (string) Str::uuid(), 'primary_allocation_id' => $this->allocation, 'install_status' => 'installed', 'metadata' => []])->save();
        $this->cell->setRelation('node', $node);
        (new NodeAllocation)->forceFill(['id' => $this->allocation, 'cell_id' => $this->cell->id, 'node_id' => $node->id, 'ip' => '0.0.0.0', 'port' => 8080])->save();
    }

    private function controller(): CellDomainController
    {
        return new class($this->cell) extends CellDomainController
        {
            public function __construct(private Cell $fixture) {}

            protected function panelCellOrFail(string $id): Cell
            {
                return $this->fixture;
            }
        };
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['domain' => 'www.example.com', 'allocation_id' => $this->allocation, 'protocol' => 'http', 'public_port' => 0, 'https' => true, 'redirect' => true, 'max_body_mb' => 32], $overrides);
    }

    public function test_save_uses_the_cells_daemon_id_and_assigned_port(): void
    {
        $proxy = Mockery::mock(WebProxyNodeClient::class);
        $proxy->shouldReceive('request')->once()->withArgs(fn ($node, $method, $path, $data) => $node->id === $this->cell->node_id && $method === 'POST' && $path === '/cells/'.$this->cell->daemon_id.'/domains' && $data['port'] === 8080 && $data['domain'] === 'www.example.com')->andReturn([]);
        $this->controller()->save($this->cell->id, Request::create('/', 'POST', $this->payload(['domain' => ' WWW.Example.COM. '])), $proxy);
        $record = CellDomain::first();
        $this->assertSame($this->allocation, $record->allocation_id);
        $this->assertSame($this->cell->id, $record->cell_id);
        $this->assertSame('www.example.com', $record->domain);
    }

    public function test_worker_failure_rolls_back_the_panel_record(): void
    {
        $proxy = Mockery::mock(WebProxyNodeClient::class);
        $proxy->shouldReceive('request')->once()->andThrow(ValidationException::withMessages(['domains' => 'Node unavailable']));
        try {
            $this->controller()->save($this->cell->id, Request::create('/', 'POST', $this->payload()), $proxy);
            $this->fail('Worker failure ignored');
        } catch (ValidationException) {
            $this->assertSame(0, CellDomain::count());
        }
    }

    public function test_cannot_target_another_cells_allocation(): void
    {
        $other = (new NodeAllocation)->forceFill(['id' => (string) Str::uuid(), 'cell_id' => (string) Str::uuid(), 'node_id' => $this->cell->node_id, 'ip' => '0.0.0.0', 'port' => 9000]);
        $other->save();
        $proxy = Mockery::mock(WebProxyNodeClient::class);
        $proxy->shouldNotReceive('request');
        $this->expectException(ModelNotFoundException::class);
        $this->controller()->save($this->cell->id, Request::create('/', 'POST', $this->payload(['allocation_id' => $other->id])), $proxy);
    }

    public function test_status_does_not_expose_orphan_or_foreign_worker_routes(): void
    {
        $id = (string) Str::uuid();
        CellDomain::create(array_merge($this->payload(), ['id' => $id, 'cell_id' => $this->cell->id, 'node_id' => $this->cell->node_id]));
        $proxy = Mockery::mock(WebProxyNodeClient::class);
        $proxy->shouldReceive('request')->once()->andReturn(['enabled' => true, 'routes' => [['id' => $id, 'verified' => true, 'verification_token' => 'ours'], ['id' => (string) Str::uuid(), 'verified' => true, 'verification_token' => 'private']]]);
        $data = $this->controller()->status($this->cell->id, $proxy)->getData(true);
        $this->assertCount(1, $data['routes']);
        $this->assertSame('ours', $data['routes'][0]['verification_token']);
    }

    public function test_foreign_route_cannot_be_deleted(): void
    {
        $id = (string) Str::uuid();
        CellDomain::create(array_merge($this->payload(), ['id' => $id, 'cell_id' => (string) Str::uuid(), 'node_id' => $this->cell->node_id]));
        $proxy = Mockery::mock(WebProxyNodeClient::class);
        $proxy->shouldNotReceive('request');
        $this->expectException(ModelNotFoundException::class);
        $this->controller()->destroy($this->cell->id, $id, $proxy);
    }

    public function test_hostnames_are_unique_across_nodes(): void
    {
        CellDomain::create(array_merge($this->payload(), ['cell_id' => (string) Str::uuid(), 'node_id' => (string) Str::uuid()]));
        $proxy = Mockery::mock(WebProxyNodeClient::class);
        $proxy->shouldNotReceive('request');
        $this->expectException(ValidationException::class);
        $this->controller()->save($this->cell->id, Request::create('/', 'POST', $this->payload()), $proxy);
    }
}
