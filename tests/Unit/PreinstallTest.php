<?php

namespace Tests\Unit;

use App\Http\Controllers\Cells\CellPreinstallController;
use App\Models\Cell;
use App\Models\Node;
use App\Models\User;
use App\Services\Cells\MinecraftContentService;
use App\Services\Node\CellNodeClient;
use App\Services\Node\NodeClient;
use App\Services\PreinstallRegistryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PreinstallTest extends TestCase
{
    private function fixture(): array
    {
        $user = new User;
        $user->id = 'owner';
        auth()->setUser($user);
        $cell = new Cell(['owner_id' => 'owner', 'comb' => 'discord-bot', 'install_status' => 'installed']);
        $cell->id = 'cell';
        $client = Mockery::mock(CellNodeClient::class);
        $client->shouldReceive('definitionPayload')->andReturn(['comb_data' => ['preinstalls' => ['discord-tickets']]]);
        $this->app->instance(CellNodeClient::class, $client);
        $controller = new class($cell) extends CellPreinstallController
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

    public function test_users_cannot_install_presets_not_offered_by_the_comb(): void
    {
        [$controller, $client, $user] = $this->fixture();
        $registry = Mockery::mock(PreinstallRegistryService::class);
        $registry->shouldNotReceive('json');
        try {
            $controller->preview('cell', $this->request(['preset' => 'other-bot', 'version' => '1'], $user), $registry);
            $this->fail('Unoffered preset accepted');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_preview_is_scoped_and_requires_explicit_replacement_confirmation(): void
    {
        [$controller, $client, $user] = $this->fixture();
        $registry = Mockery::mock(PreinstallRegistryService::class);
        $registry->shouldReceive('json')->once()->andReturn(['kind' => 'preinstall', 'preset' => 'discord-tickets', 'version' => '4.0']);
        $response = $controller->preview('cell', $this->request(['preset' => 'discord-tickets', 'version' => '4.0'], $user), $registry);
        $ticket = $response->getData(true)['ticket'];
        $this->assertSame('owner', Cache::get('preinstall-preview:'.$ticket)['user']);
        $nodes = Mockery::mock(NodeClient::class);
        $nodes->shouldNotReceive('client');
        $this->expectException(ValidationException::class);
        $controller->install('cell', $this->request(['ticket' => $ticket], $user), $client, $nodes);
    }

    public function test_a_foreign_preview_cannot_be_installed(): void
    {
        [$controller, $client, $user] = $this->fixture();
        $ticket = (string) Str::uuid();
        Cache::put('preinstall-preview:'.$ticket, ['user' => 'other', 'cell' => 'cell', 'plan' => []], 60);
        $nodes = Mockery::mock(NodeClient::class);
        $nodes->shouldNotReceive('client');
        try {
            $controller->install('cell', $this->request(['ticket' => $ticket, 'confirm_wipe' => true], $user), $client, $nodes);
            $this->fail('Foreign preview accepted');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_runtime_overlay_preserves_admin_offers_and_unrelated_comb_fields(): void
    {
        $cell = new Cell(['metadata' => ['content_install' => ['runtime' => ['image' => 'bot@sha256:pinned', 'startup' => '/app/start', 'environment' => ['TOKEN' => '{{TOKEN}}'], 'variables_schema' => [['name' => 'TOKEN', 'secret' => true]]]]]]);
        $manifest = (new MinecraftContentService)->manifest($cell, ['id' => 'discord-bot', 'preinstalls' => ['discord-tickets'], 'custom' => true]);
        $this->assertSame(['discord-tickets'], $manifest['preinstalls']);
        $this->assertTrue($manifest['custom']);
        $this->assertSame('bot@sha256:pinned', $manifest['image']);
        $this->assertTrue($manifest['variables_schema'][0]['secret']);
    }

    public function test_completed_preinstalls_sync_pinned_runtime_once(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('cells', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('node_id');
            $table->string('name');
            $table->string('daemon_id');
            $table->json('metadata');
            $table->timestamps();
        });
        Node::forceCreate(['id' => 'node', 'name' => 'Node']);
        $cell = Cell::forceCreate(['id' => 'cell', 'node_id' => 'node', 'name' => 'Cell', 'daemon_id' => 'daemon', 'metadata' => ['content_install' => ['pending_job' => 'job'], 'variables' => ['memory' => '2048', 'DISCORD_TOKEN' => 'private']]]);
        $job = ['id' => 'job', 'state' => 'complete', 'plan' => ['kind' => 'preinstall', 'preset' => 'discord-tickets', 'version' => '4.0', 'name' => 'Discord Tickets', 'runtime' => ['image' => 'bot:4.0', 'startup' => '/app/start', 'environment' => ['DISCORD_TOKEN' => '{{DISCORD_TOKEN}}'], 'variables_schema' => [['name' => 'DISCORD_TOKEN', 'default' => '', 'secret' => true]]]], 'result' => ['startup' => '/app/start', 'image' => 'bot@sha256:pinned']];
        Http::fake(['*' => Http::response($job)]);
        $nodes = Mockery::mock(NodeClient::class);
        $nodes->shouldReceive('client')->andReturnUsing(fn () => Http::baseUrl('https://worker.test'));
        $this->app->instance(NodeClient::class, $nodes);
        $service = new MinecraftContentService;
        $service->status($cell);
        $this->assertSame('bot@sha256:pinned', $cell->metadata['docker']['image']);
        $this->assertSame('bot@sha256:pinned', $cell->metadata['content_install']['runtime']['image']);
        $this->assertSame('private', $cell->metadata['variables']['DISCORD_TOKEN']);
        $this->assertArrayNotHasKey('loader_version', $cell->metadata['variables']);
        $metadata = $cell->metadata;
        $metadata['startup']['command'] = 'manual command';
        $cell->metadata = $metadata;
        $cell->save();
        $service->status($cell);
        $this->assertSame('manual command', $cell->metadata['startup']['command']);
    }
}
