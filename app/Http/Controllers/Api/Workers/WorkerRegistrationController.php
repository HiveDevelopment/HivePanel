<?php

namespace App\Http\Controllers\Api\Workers;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkerRegistrationController extends Controller
{
    /**
     * Register a new HivePanel worker.
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'registration_token' => ['required', 'string'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:100'],
        ]);

        $hashedToken = hash('sha256', $data['registration_token']);

        $node = Node::query()
            ->where('registration_token', $hashedToken)
            ->where(function ($query) {
                $query
                    ->whereNull('registration_token_expires_at')
                    ->orWhere('registration_token_expires_at', '>', now());
            })
            ->firstOrFail();

        $workerToken = 'hpwk_' . Str::random(64);

        $node->update([
            'api_token' => $workerToken,
            'registration_token' => null,
            'registration_token_expires_at' => null,
            'is_registered' => true,
            'registered_at' => now(),
            'last_seen_at' => now(),
            'worker_hostname' => $data['hostname'] ?? null,
            'worker_platform' => $data['platform'] ?? null,
            'worker_version' => $data['version'] ?? null,
            'worker_ip' => $request->ip(),
        ]);

        $allocations = $node->allocations()
            ->orderBy('ip')
            ->orderBy('port')
            ->get(['ip', 'port'])
            ->map(fn ($allocation) => [
                'ip' => $allocation->ip,
                'port' => (int) $allocation->port,
            ])
            ->values()
            ->all();

        return response()->json([
            'node_id' => $node->id,
            'token' => $workerToken,

            'configuration' => [
                'panel' => [
                    'url' => rtrim(config('app.url'), '/'),
                ],

                'worker' => [
                    'listen' => "0.0.0.0:{$node->daemon_port}",
                ],

                'sftp' => [
                    'enabled' => (bool) $node->sftp_enabled,
                    'listen' => "0.0.0.0:{$node->sftp_port}",
                    'public_fqdn' => $node->sftpHost(),
                    'public_port' => (int) $node->sftp_port,
                    'host_key_path' => '/etc/hivepanel/keys/sftp_host_ed25519',
                    'auth_timeout_seconds' => 10,
                ],

                'paths' => [
                    'data' => '/var/lib/hivepanel/data',
                    'instances' => '/var/lib/hivepanel/cells',
                    'backups' => '/var/lib/hivepanel/backups',
                    'backup_mounts' => '/var/lib/hivepanel/backup_mounts',
                ],

                'runtime' => [
                    'type' => 'docker',
                ],

                'docker' => [
                    'network' => 'hivepanel',
                ],

                'allocations' => [
                    'entries' => $allocations,
                ],
            ],
        ]);
    }
}