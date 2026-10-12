<?php

namespace Tests\Unit;

use App\Services\Nectar\ExtensionManager;
use App\Services\Nectar\PublicHttpGateway;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NectarNetworkTest extends TestCase
{
    private function manifest(): array
    {
        return ['id' => 'test.network', 'name' => 'Test', 'version' => '0.9.0', 'panelCompatibility' => 'dev', 'frontend' => ['entry' => 'dist/index.html', 'slots' => ['dashboard.widgets']], 'permissions' => ['network.http', 'network.tcp'], 'network' => ['http' => ['hosts' => ['api.example.com']], 'tcp' => ['scope' => 'cell-allocations']]];
    }

    public function test_manifest_accepts_explicit_network_scopes(): void
    {
        config(['nectar.panel_version' => 'dev']);
        $this->assertSame($this->manifest(), (new ExtensionManager())->validate($this->manifest()));
    }

    public function test_capabilities_alias_is_normalized_and_enforced(): void
    {
        $manifest = $this->manifest();
        $manifest['capabilities'] = $manifest['permissions'];
        unset($manifest['permissions']);
        $this->assertEquals($this->manifest(), (new ExtensionManager())->validate($manifest));
    }

    public function test_manifest_rejects_network_permission_without_scope(): void
    {
        $manifest = $this->manifest(); unset($manifest['network']['tcp']);
        $this->expectException(ValidationException::class);
        (new ExtensionManager())->validate($manifest);
    }

    public function test_manifest_rejects_wildcard_hosts(): void
    {
        $manifest = $this->manifest(); $manifest['network']['http']['hosts'] = ['*.example.com'];
        $this->expectException(ValidationException::class);
        (new ExtensionManager())->validate($manifest);
    }

    public function test_gateway_denies_private_reserved_and_transition_addresses(): void
    {
        $gateway = new PublicHttpGateway();
        foreach (['127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.0.1', '169.254.169.254', '100.64.0.1', '224.0.0.1', '198.18.0.1', '::1', '::ffff:127.0.0.1', 'fc00::1', 'fe80::1', 'ff02::1', '2002:7f00:1::', '2001:db8::1'] as $ip) {
            $this->assertFalse($gateway->publicAddress($ip), $ip);
        }
        $this->assertTrue($gateway->publicAddress('8.8.8.8'));
        $this->assertTrue($gateway->publicAddress('2606:4700:4700::1111'));
    }

    public function test_url_enforces_exact_host_https_and_port(): void
    {
        $gateway = new PublicHttpGateway();
        $this->assertSame('api.example.com', $gateway->destination('https://api.example.com/path?q=value', ['api.example.com']));
        foreach (['http://api.example.com', 'https://api.example.com:8443', 'https://api.example.com.evil.test', 'https://user:password@api.example.com', 'https://127.0.0.1', 'https://other.example.com', 'https://api.example.com/#fragment'] as $url) {
            try { $gateway->destination($url, ['api.example.com']); $this->fail('Accepted ' . $url); } catch (ValidationException) { $this->assertTrue(true); }
        }
    }

    public function test_dns_with_any_private_address_is_rejected_before_connection(): void
    {
        $gateway = new class extends PublicHttpGateway {
            protected function addresses(string $host): array { return ['8.8.8.8', '127.0.0.1']; }
        };
        $this->expectException(ValidationException::class);
        $gateway->request(['url' => 'https://api.example.com'], ['api.example.com']);
    }

    public function test_network_requires_admin_approval_and_disabled_extensions_are_denied(): void
    {
        Schema::create('nectar_extensions', function (Blueprint $table): void {
            $table->string('extension_id'); $table->boolean('enabled'); $table->boolean('network_approved'); $table->text('manifest'); $table->timestamps();
        });
        DB::table('nectar_extensions')->insert(['extension_id' => 'test.network', 'enabled' => true, 'network_approved' => false, 'manifest' => json_encode($this->manifest())]);
        $manager = new ExtensionManager();
        $this->assertFalse($manager->enabledWithCapability('test.network', 'network.tcp'));
        DB::table('nectar_extensions')->update(['network_approved' => true]);
        $this->assertTrue($manager->enabledWithCapability('test.network', 'network.tcp'));
        $this->assertFalse($manager->enabledWithCapability('test.network', 'undeclared.capability'));
        DB::table('nectar_extensions')->update(['enabled' => false]);
        $this->assertFalse($manager->enabledWithCapability('test.network', 'network.tcp'));
    }
}
