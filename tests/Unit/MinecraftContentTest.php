<?php

namespace Tests\Unit;

use App\Http\Controllers\Cells\CellMinecraftController;
use App\Models\Cell;
use App\Models\MinecraftPack;
use App\Models\Node;
use App\Models\User;
use App\Services\Cells\MinecraftContentService;
use App\Services\MinecraftRegistryService;
use App\Services\Node\CellNodeClient;
use App\Services\Node\NodeClient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MinecraftContentTest extends TestCase
{
    private function fixture(): array
    {
        $user = new User;
        $user->id = 'owner';
        auth()->setUser($user);
        $cell = new Cell(['owner_id' => 'owner', 'comb' => 'minecraft-paper', 'install_status' => 'installed']);
        $cell->id = 'cell';
        $client = Mockery::mock(CellNodeClient::class);
        $client->shouldReceive('definitionPayload')->andReturn(['comb_data' => ['game' => 'minecraft']]);
        $this->app->instance(CellNodeClient::class, $client);
        $controller = new class($cell) extends CellMinecraftController
        {
            public function __construct(private Cell $fixture) {}

            protected function panelCellOrFail(string $id): Cell
            {
                return $this->fixture;
            }
        };

        return [$controller, $client, $user];
    }

    private function request(array $data, User $user): Request
    {
        $request = Request::create('/', 'POST', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_install_preview_is_scoped_to_user_and_cell(): void
    {
        [$controller,$client,$user] = $this->fixture();
        $registry = Mockery::mock(MinecraftRegistryService::class);
        $registry->shouldReceive('json')->once()->andReturn(['id' => 'pinned-plan', 'kind' => 'mod', 'files' => []]);
        $request = $this->request(['kind' => 'mod', 'minecraft' => '1.21.1', 'loader' => 'fabric', 'selections' => [['provider' => 'modrinth', 'project' => 'example', 'version' => 'release']]], $user);
        $response = $controller->preview('cell', $request, $registry);
        $data = $response->getData(true);
        $cached = Cache::get('minecraft-preview:'.$data['ticket']);
        $this->assertSame('owner', $cached['user']);
        $this->assertSame('cell', $cached['cell']);
        $this->assertSame('pinned-plan', $cached['plan']['id']);
    }

    public function test_another_users_preview_cannot_be_installed(): void
    {
        [$controller,$client,$user] = $this->fixture();
        $ticket = (string) Str::uuid();
        Cache::put('minecraft-preview:'.$ticket, ['user' => 'other', 'cell' => 'cell', 'plan' => []], 60);
        $nodes = Mockery::mock(NodeClient::class);
        $nodes->shouldNotReceive('client');
        try {
            $controller->install('cell', $this->request(['ticket' => $ticket], $user), $client, $nodes);
            $this->fail('Foreign preview accepted');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_cell_scoping_prevents_reusing_a_preview_on_another_cell(): void
    {
        [$controller,$client,$user] = $this->fixture();
        $ticket = (string) Str::uuid();
        Cache::put('minecraft-preview:'.$ticket, ['user' => 'owner', 'cell' => 'other-cell', 'plan' => []], 60);
        try {
            $controller->install('cell', $this->request(['ticket' => $ticket], $user), $client, Mockery::mock(NodeClient::class));
            $this->fail('Foreign cell accepted');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_provider_keys_are_only_added_to_registry_requests(): void
    {
        config(['hivepanel.registry_url' => 'https://registry.test', 'hivepanel.registry_token' => 'fixture-token']);
        Http::fake(['registry.test/*' => Http::response(['hits' => []])]);
        $data = (new MinecraftRegistryService)->json('get', '/search', ['provider' => 'curseforge']);
        $this->assertSame(['hits' => []], $data);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer fixture-token') && $r->url() === 'https://registry.test/api/v1/minecraft/search?provider=curseforge');
    }

    public function test_registry_distribution_errors_are_reported_as_validation_messages(): void
    {
        config(['hivepanel.registry_url' => 'https://registry.test']);
        Http::fake(['*' => Http::response(['message' => 'Provider does not permit automatic download'], 422)]);
        try {
            (new MinecraftRegistryService)->json('post', '/resolve', []);
            $this->fail('Error swallowed');
        } catch (ValidationException $e) {
            $this->assertSame(['Provider does not permit automatic download'], $e->errors()['catalogue']);
        }
    }

    public function test_minecraft_routes_enforce_read_and_install_permissions(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertContains('cell.permission:files.view', $routes->getByName('cells.minecraft.index')->gatherMiddleware());
        $this->assertContains('cell.permission:files.upload', $routes->getByName('cells.minecraft.install')->gatherMiddleware());
        $this->assertContains('cell.permission:files.view', $routes->getByName('cells.minecraft.preview')->gatherMiddleware());
    }

    public function test_saved_packs_cannot_be_deleted_by_another_user(): void
    {
        [$controller,$client,$user] = $this->fixture();
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
        });
        DB::table('users')->insert([['id' => 'owner'], ['id' => 'other']]);
        $migration = require database_path('migrations/2026_10_13_140000_create_minecraft_packs_table.php');
        $migration->up();
        $foreign = MinecraftPack::create(['user_id' => 'other', 'name' => 'Other pack', 'spec' => []]);
        try {
            $controller->deletePack('cell', $foreign->id);
            $this->fail('Foreign pack deleted');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(MinecraftPack::whereKey($foreign->id)->exists());
        }
        $response = $controller->savePack('cell', $this->request(['kind' => 'builder', 'name' => 'My pack', 'minecraft' => '1.21.1', 'loader' => 'fabric', 'selections' => [['provider' => 'modrinth', 'project' => 'example', 'version' => 'release']]], $user));
        $own = $response->getData(true);
        $this->assertSame('owner', $own['user_id']);
        $this->assertSame('fabric', $own['spec']['loader']);
        $controller->deletePack('cell', $own['id']);
        $this->assertSame(1, MinecraftPack::count());
    }

    public function test_completed_jobs_sync_startup_once_and_preserve_other_metadata(): void
    {
        Schema::create('nodes', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('cells', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('node_id');
            $t->string('name');
            $t->string('daemon_id');
            $t->json('metadata');
            $t->timestamps();
        });
        $node = Node::forceCreate(['id' => 'node', 'name' => 'Node']);
        $cell = Cell::forceCreate(['id' => 'cell', 'node_id' => 'node', 'name' => 'Cell', 'daemon_id' => 'daemon', 'metadata' => ['minecraft_content' => ['pending_job' => 'job'], 'custom' => true, 'variables' => ['memory' => '2048']]]);
        $job = ['id' => 'job', 'state' => 'complete', 'plan' => ['minecraft' => '1.21.1', 'loader' => 'forge', 'loaderVersion' => '1.21.1-52.0.0', 'name' => 'Pack'], 'result' => ['startup' => 'new startup']];
        Http::fake(['*' => Http::response($job)]);
        $nodes = Mockery::mock(NodeClient::class);
        $nodes->shouldReceive('client')->andReturnUsing(fn () => Http::baseUrl('https://worker.test'));
        $this->app->instance(NodeClient::class, $nodes);
        $service = new MinecraftContentService;
        $service->status($cell);
        $this->assertSame('new startup', $cell->metadata['startup']['command']);
        $this->assertSame('52.0.0', $cell->metadata['variables']['forge_version']);
        $this->assertSame('2048', $cell->metadata['variables']['memory']);
        $this->assertTrue($cell->metadata['custom']);
        $metadata = $cell->metadata;
        $metadata['startup']['command'] = 'manual change';
        $cell->metadata = $metadata;
        $cell->save();
        $service->status($cell);
        $this->assertSame('manual change', $cell->metadata['startup']['command']);
        $addon = ['id' => 'plugin-job', 'state' => 'complete', 'plan' => ['minecraft' => '1.21.1', 'loader' => 'paper', 'loaderVersion' => '', 'name' => 'Plugin'], 'result' => ['startup' => '']];
        Http::fake(['*' => Http::response($addon)]);
        $service->status($cell);
        $this->assertSame('forge', $cell->metadata['minecraft_content']['loader']);
        $this->assertSame('Pack', $cell->metadata['minecraft_content']['name']);

    }

    public function test_pack_install_requires_explicit_wipe_confirmation(): void
    {
        [$controller,$client,$user] = $this->fixture();
        $ticket = (string) Str::uuid();
        Cache::put('minecraft-preview:'.$ticket, ['user' => 'owner', 'cell' => 'cell', 'plan' => ['kind' => 'modpack']], 60);
        $nodes = Mockery::mock(NodeClient::class);
        $nodes->shouldNotReceive('client');
        $this->expectException(ValidationException::class);
        $controller->install('cell',$this->request(['ticket' => $ticket],$user),$client,$nodes);
    }
}
