<?php

namespace Tests\Unit;

use App\Http\Controllers\NectarEventController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class NectarEventControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('nectar_extensions', function (Blueprint $table): void {
            $table->string('extension_id');
            $table->boolean('enabled');
            $table->text('manifest');
        });
        DB::table('nectar_extensions')->insert([
            'extension_id' => 'test.events',
            'enabled' => true,
            'manifest' => json_encode([
                'permissions' => ['cells.read'],
                'events' => ['subscribe' => ['cell.updated']],
            ]),
        ]);
    }

    private function subscribe(array $params)
    {
        return (new NectarEventController)->subscribe(Request::create('/nectar/events', 'POST', $params), 'test.events');
    }

    public function test_declared_events_are_accepted(): void
    {
        $this->assertSame(['ok' => true], $this->subscribe(['events' => ['cell.updated']])->getData(true));
    }

    public function test_empty_subscription_is_accepted_to_stop_polling(): void
    {
        $this->assertSame(['ok' => true], $this->subscribe(['events' => []])->getData(true));
    }

    public function test_missing_events_are_not_treated_as_unsubscribe(): void
    {
        $this->expectException(ValidationException::class);
        $this->subscribe([]);
    }

    public function test_undeclared_events_are_denied(): void
    {
        try {
            $this->subscribe(['events' => ['cell.deleted']]);
            $this->fail('Undeclared event allowed');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_disabled_extensions_cannot_subscribe(): void
    {
        DB::table('nectar_extensions')->update(['enabled' => false]);
        $this->expectException(HttpException::class);
        $this->subscribe(['events' => []]);
    }

    public function test_cell_read_permission_is_required_even_for_declared_events(): void
    {
        DB::table('nectar_extensions')->update(['manifest' => json_encode(['events' => ['subscribe' => ['cell.updated']]])]);
        $this->expectException(HttpException::class);
        $this->subscribe(['events' => ['cell.updated']]);
    }
}
