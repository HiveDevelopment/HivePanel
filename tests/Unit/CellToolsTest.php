<?php

namespace Tests\Unit;

use App\Http\Middleware\SetPanelLocale;
use App\Models\SubdomainZone;
use App\Services\Cells\CellSplitterService;
use App\Services\Dns\ManagedDnsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CellToolsTest extends TestCase
{
    public function test_split_preserves_total_budget(): void
    {
        $total = ['memory_mb' => 4096, 'disk_mb' => 10000, 'cpu_percent' => 200, 'swap_mb' => 0, 'overhead_memory_mb' => 256];
        $child = ['memory_mb' => 1024, 'disk_mb' => 2000, 'cpu_percent' => 50, 'swap_mb' => 0, 'overhead_memory_mb' => 64];
        $remaining = CellSplitterService::remaining($total, $child);
        foreach ($total as $key => $value) {
            $this->assertSame($value, $remaining[$key] + $child[$key]);
        }
    }

    public function test_cannot_split_unlimited_or_all_of_parent_budget(): void
    {
        $this->expectException(ValidationException::class);
        CellSplitterService::remaining(['memory_mb' => 0, 'disk_mb' => 1000, 'cpu_percent' => 100], ['memory_mb' => 128, 'disk_mb' => 128, 'cpu_percent' => 10]);
    }

    private function zone(): SubdomainZone
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $zone = new SubdomainZone;
        $zone->forceFill(['provider_zone_id' => str_repeat('a', 32), 'api_token' => 'test-token', 'domain' => 'example.com']);

        return $zone;
    }

    public function test_dns_never_replaces_an_unowned_record(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'result' => [['id' => str_repeat('b', 32), 'type' => 'A', 'comment' => 'another service']]])]);
        try {
            app(ManagedDnsService::class)->create($this->zone(), 'play.example.com', ['type' => 'A', 'content' => '192.0.2.1'], 'reservation');
            $this->fail('Unowned record replaced');
        } catch (ValidationException) {
            Http::assertSentCount(1);
        }
    }

    public function test_retry_updates_only_its_owned_dns_record(): void
    {
        Http::fakeSequence()->push(['success' => true, 'result' => [['id' => str_repeat('b', 32), 'type' => 'TXT', 'comment' => 'HivePanel reservation']]])->push(['success' => true, 'result' => ['id' => str_repeat('b', 32)]]);
        app(ManagedDnsService::class)->create($this->zone(), '_hivepanel.play.example.com', ['type' => 'TXT', 'content' => 'new-token'], 'reservation');
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && $r['content'] === 'new-token');
    }

    public function test_deleted_dns_record_cleanup_is_idempotent(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 404)]);
        app(ManagedDnsService::class)->remove($this->zone(), ['id' => str_repeat('b', 32), 'name' => 'play.example.com'], 'reservation');
        Http::assertSentCount(1);
    }

    public function test_cell_tools_schema_can_be_installed_and_reverted(): void
    {
        Schema::create('cells', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });
        $migration = require base_path('database/migrations/2026_10_15_120000_create_cell_tools_tables.php');
        $migration->up();
        foreach (['cell_splits', 'subdomain_zones', 'cell_subdomains'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $migration->down();
        $this->assertFalse(Schema::hasTable('cell_subdomains'));
    }

    public function test_request_locale_is_limited_to_installed_catalogs(): void
    {
        $request = Request::create('/', 'GET', [], ['hivepanel_locale' => 'de']);
        $middleware = new SetPanelLocale;
        $middleware->handle($request, function () {
            $this->assertSame('de', app()->getLocale());

            return response('ok');
        });
        $request = Request::create('/', 'GET', [], ['hivepanel_locale' => '../../secret']);
        $middleware->handle($request, function () {
            $this->assertSame('en', app()->getLocale());

            return response('ok');
        });
    }
}
