<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Services\Nectar\ExtensionManager;
use App\Services\Nectar\PublicHttpGateway;
use App\Services\Node\NodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class NectarNetworkController extends Controller
{
    public function handle(Request $request, string $id, string $transport, ExtensionManager $manager, PublicHttpGateway $http, NodeClient $nodes)
    {
        $outcome = 'denied';
        $destination = null;
        $cellId = null;
        $status = null;
        $operation = "network.{$transport}";
        // Require durable audit storage before performing any network side effect.
        $auditId = DB::table('nectar_network_audits')->insertGetId([
            'extension_id' => $id, 'user_id' => $request->user()?->id,
            'operation' => $operation, 'outcome' => 'pending', 'created_at' => now(),
        ]);
        try {
            abort_unless($manager->enabledWithCapability($id, $operation), 403, 'This network capability is not enabled and approved.');
            $key = 'nectar-network:' . hash('sha256', $id . ':' . $request->user()->id);
            abort_if(RateLimiter::tooManyAttempts($key, 30), 429, 'Extension network limit reached.');
            RateLimiter::hit($key, 60);
            $manifest = collect($manager->installed())->firstWhere('id', $id)['manifest'];
            if ($transport === 'http') {
                $input = $request->validate([
                    'url' => ['required', 'string', 'max:2048'],
                    'method' => ['sometimes', 'in:GET,POST'],
                    'body' => ['sometimes', 'string', 'max:16384'],
                ]);
                $destination = $http->destination($input['url'], $manifest['network']['http']['hosts'] ?? []);
                $result = $http->request($input, $manifest['network']['http']['hosts'] ?? []);
                $status = $result['status'];
            } else {
                abort_unless(($manifest['network']['tcp']['scope'] ?? '') === 'cell-allocations', 403);
                $input = $request->validate([
                    'cell_id' => ['required', 'string', 'max:64'],
                    'allocation_id' => ['required', 'string', 'max:64'],
                    'action' => ['required', 'in:open,exchange,close'],
                    'session_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/D'],
                    'data_base64' => ['sometimes', 'string', 'max:21848'],
                    'read_line' => ['sometimes', 'boolean'],
                    'read_json' => ['sometimes', 'boolean'],
                ]);
                $cell = Cell::query()->visibleTo($request->user())->with(['node', 'allocations'])->findOrFail($input['cell_id']);
                abort_unless($cell->userCan($request->user(), \App\Support\CellPermissions::CONSOLE_SEND), 403, 'Console command permission is required.');
                $cellId = $cell->id;
                $comb = \App\Models\Comb::find(data_get($cell->metadata, 'comb_id'))?->data ?? data_get($cell->metadata, 'comb_data', []);
                $scopes = $manifest['frontend']['combs'] ?? [];
                if ($scopes) abort_unless(in_array((string) $cell->comb, $scopes, true) || in_array('game:' . strtolower((string) ($comb['game'] ?? '')), $scopes, true), 403, 'This extension does not support this Comb.');
                $allocation = $cell->allocations->firstWhere('id', $input['allocation_id']);
                abort_unless($allocation && (string) $allocation->node_id === (string) $cell->node_id, 403, 'Allocation is not assigned to this Cell.');
                $desired = data_get($cell->metadata, 'desired_allocation_ids');
                if (is_array($desired)) abort_unless(in_array((string) $allocation->id, array_map('strval', $desired), true), 403, 'Allocation is pending removal.');
                $destination = $allocation->ip . ':' . $allocation->port;
                $operation .= '.' . $input['action'];
                $response = $nodes->client($cell->node)->timeout(8)->post('/cells/' . rawurlencode($cell->daemon_id) . '/network/tcp', [
                    ...$input,
                    'scope' => hash('sha256', $id . ':' . $request->user()->id . ':' . $cell->id),
                    'ip' => $allocation->ip,
                    'port' => (int) $allocation->port,
                ]);
                $status = $response->status();
                if (! $response->successful()) {
                    $outcome = 'failed';
                    return response()->json(['message' => $status === 403 ? 'The worker denied access to this allocation or TCP session.' : 'The TCP operation failed. Check the allocation and server listener.'], $status >= 400 && $status < 500 ? $status : 502);
                }
                $result = $response->json();
            }
            $outcome = 'success';
            return response()->json($result)->header('Cache-Control', 'private, no-store');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            $outcome = 'failed';
            return response()->json(['message' => 'The extension network request could not be completed.'], 502);
        } finally {
            // Never log payloads, token values, session IDs, URL queries or remote response bodies.
            DB::table('nectar_network_audits')->where('id', $auditId)->update([
                'extension_id' => $id, 'user_id' => $request->user()?->id, 'cell_id' => $cellId,
                'operation' => $operation, 'destination' => $destination, 'outcome' => $outcome,
                'response_status' => $status, 'created_at' => now(),
            ]);
        }
    }
}
