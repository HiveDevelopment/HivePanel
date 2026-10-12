<?php

namespace Tests\Unit;

use App\Http\Controllers\NectarNetworkController;
use App\Models\User;
use App\Services\Nectar\ExtensionManager;
use App\Services\Nectar\PublicHttpGateway;
use App\Services\Node\NodeClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NectarNetworkControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('nectar_extensions', function (Blueprint $t): void {
            $t->string('extension_id'); $t->string('name'); $t->string('version'); $t->boolean('enabled'); $t->boolean('network_approved'); $t->text('manifest');
        });
        Schema::create('nectar_network_audits', function (Blueprint $t): void {
            $t->id(); foreach (['extension_id', 'user_id', 'cell_id', 'operation', 'destination', 'outcome'] as $key) $t->string($key)->nullable(); $t->integer('response_status')->nullable(); $t->timestamp('created_at');
        });
        Schema::create('cells', function (Blueprint $t): void {
            foreach (['id', 'name', 'comb', 'owner_id', 'node_id', 'daemon_id'] as $key) $t->string($key); $t->text('metadata');
        });
        Schema::create('nodes', function (Blueprint $t): void {
            foreach (['id', 'public_fqdn', 'fqdn', 'scheme', 'api_token'] as $key) $t->string($key); $t->integer('port'); $t->integer('daemon_port');
        });
        Schema::create('node_allocations', function (Blueprint $t): void {
            foreach (['id', 'node_id', 'cell_id', 'ip'] as $key) $t->string($key); $t->integer('port');
        });
        Schema::create('cell_users', function (Blueprint $t): void {
            $t->string('cell_id'); $t->string('user_id'); $t->text('permissions');
        });
        Schema::create('combs', function (Blueprint $t): void { $t->string('id'); $t->text('data')->nullable(); });
        DB::table('nectar_extensions')->insert(['extension_id' => 'test.network', 'name' => 'Test', 'version' => '0.9.0', 'enabled' => true, 'network_approved' => true, 'manifest' => json_encode(['permissions' => ['network.tcp'], 'network' => ['tcp' => ['scope' => 'cell-allocations']]])]);
        DB::table('nodes')->insert(['id' => 'node', 'public_fqdn' => 'worker.example.test', 'fqdn' => 'worker.example.test', 'scheme' => 'http', 'api_token' => 'worker-token', 'port' => 8080, 'daemon_port' => 8080]);
        DB::table('cells')->insert(['id' => 'cell', 'name' => 'Cell', 'comb' => 'custom', 'owner_id' => 'owner', 'node_id' => 'node', 'daemon_id' => 'daemon', 'metadata' => '{}']);
        DB::table('node_allocations')->insert(['id' => 'allocation', 'node_id' => 'node', 'cell_id' => 'cell', 'ip' => '0.0.0.0', 'port' => 8192]);
    }

    private function invokeGateway(array $input, string $userId = 'owner')
    {
        $user = new User(); $user->id = $userId;
        $request = Request::create('/nectar/network', 'POST', $input); $request->setUserResolver(fn () => $user);
        return (new NectarNetworkController())->handle($request, 'test.network', 'tcp', new ExtensionManager(), new PublicHttpGateway(), new NodeClient());
    }

    public function test_authorized_request_uses_database_allocation_and_is_audited_without_payload(): void
    {
        Http::fake(['worker.example.test:8080/*' => Http::response(['session_id' => str_repeat('a', 64), 'data_base64' => ''], 200)]);
        $response = $this->invokeGateway(['cell_id' => 'cell', 'allocation_id' => 'allocation', 'action' => 'open', 'ip' => '169.254.169.254', 'port' => 80, 'data_base64' => base64_encode('secret-token')]);
        $this->assertSame(200, $response->getStatusCode());
        Http::assertSent(fn ($request) => $request['ip'] === '0.0.0.0' && $request['port'] === 8192 && strlen($request['scope']) === 64);
        $audit = DB::table('nectar_network_audits')->first();
        $this->assertSame('success', $audit->outcome);
        $this->assertSame('0.0.0.0:8192', $audit->destination);
        $this->assertStringNotContainsString('secret-token', json_encode($audit));
    }

    public function test_unassigned_allocation_is_denied_and_audited(): void
    {
        Http::fake();
        try { $this->invokeGateway(['cell_id' => 'cell', 'allocation_id' => 'other', 'action' => 'open']); $this->fail('Allocation allowed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        Http::assertNothingSent();
        $this->assertSame('denied', DB::table('nectar_network_audits')->first()->outcome);
    }

    public function test_subuser_without_console_send_permission_is_denied(): void
    {
        DB::table('cell_users')->insert(['cell_id' => 'cell', 'user_id' => 'viewer', 'permissions' => '["cell.view"]']);
        Http::fake();
        try { $this->invokeGateway(['cell_id' => 'cell', 'allocation_id' => 'allocation', 'action' => 'open'], 'viewer'); $this->fail('Viewer allowed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        Http::assertNothingSent();
    }

    public function test_pending_removal_is_denied(): void
    {
        DB::table('cells')->update(['metadata' => '{"desired_allocation_ids":[]}']);
        Http::fake();
        try { $this->invokeGateway(['cell_id' => 'cell', 'allocation_id' => 'allocation', 'action' => 'open']); $this->fail('Removed allocation allowed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        Http::assertNothingSent();
    }
}
