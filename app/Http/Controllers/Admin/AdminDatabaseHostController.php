<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DatabaseHost;
use App\Services\Databases\DatabaseProvisioningService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class AdminDatabaseHostController extends Controller
{
    public function index()
    {
        return Inertia::render(
            'Admin/DatabaseHosts/Index',
            [
                'hosts' => DatabaseHost::query()
                    ->withCount('databases')
                    ->orderBy('name')
                    ->get()
                    ->map(
                        fn (DatabaseHost $host) =>
                            $this->payload($host)
                    ),
            ]
        );
    }

    public function store(
        Request $request,
        DatabaseProvisioningService $databases,
    ) {
        $data = $this->validateHost(
            $request,
            true
        );

        $host = DatabaseHost::create(
            $data
        );

        try {
            $databases->testHost($host);
        } catch (Throwable $exception) {
            $host->delete();

            return back()->withErrors([
                'host' =>
                    'Database connection failed: '
                    . $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Database host added.'
        );
    }

    public function update(
        DatabaseHost $databaseHost,
        Request $request,
        DatabaseProvisioningService $databases,
    ) {
        $data = $this->validateHost(
            $request,
            false
        );

        if (
            ! filled(
                $data['password']
                ?? null
            )
        ) {
            unset($data['password']);
        }

        $original = $databaseHost
            ->getAttributes();

        $databaseHost->fill($data);
        $databaseHost->save();

        try {
            $databases->testHost(
                $databaseHost
            );
        } catch (Throwable $exception) {
            $databaseHost->setRawAttributes(
                $original,
                true
            );
            $databaseHost->save();

            return back()->withErrors([
                'host' =>
                    'Database connection failed: '
                    . $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Database host updated.'
        );
    }

    public function destroy(
        DatabaseHost $databaseHost,
    ) {
        abort_if(
            $databaseHost->databases()
                ->exists(),
            409,
            'This host still has Cell databases assigned to it.'
        );

        $databaseHost->delete();

        return back()->with(
            'success',
            'Database host removed.'
        );
    }

    public function test(
        DatabaseHost $databaseHost,
        DatabaseProvisioningService $databases,
    ) {
        $databases->testHost(
            $databaseHost
        );

        return response()->json([
            'ok' => true,
            'message' =>
                'Database connection successful.',
        ]);
    }

    private function validateHost(
        Request $request,
        bool $passwordRequired,
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'driver' => [
                'required',
                'string',
                'in:mysql',
            ],
            'host' => [
                'required',
                'string',
                'max:255',
            ],
            'port' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
            ],
            'username' => [
                'required',
                'string',
                'max:255',
            ],
            'password' => [
                $passwordRequired
                    ? 'required'
                    : 'nullable',
                'string',
                'max:4096',
            ],
            'public_host' => [
                'nullable',
                'string',
                'max:255',
            ],
            'public_port' => [
                'nullable',
                'integer',
                'min:1',
                'max:65535',
            ],
            'max_databases' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'enabled' => [
                'required',
                'boolean',
            ],
        ]);
    }

    private function payload(
        DatabaseHost $host,
    ): array {
        return [
            'id' => $host->id,
            'name' => $host->name,
            'driver' => $host->driver,
            'host' => $host->host,
            'port' => $host->port,
            'username' => $host->username,
            'public_host' => $host->public_host,
            'public_port' => $host->public_port,
            'display_host' =>
                $host->displayHost(),
            'display_port' =>
                $host->displayPort(),
            'max_databases' =>
                $host->max_databases,
            'databases_count' =>
                $host->databases_count,
            'enabled' => $host->enabled,
            'created_at' =>
                $host->created_at
                    ?->toISOString(),
        ];
    }
}