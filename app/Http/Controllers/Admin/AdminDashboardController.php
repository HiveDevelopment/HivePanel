<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Cell;
use App\Models\Node;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Throwable;

class AdminDashboardController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'nodes' => Node::count(),
                'active_nodes' => Node::where('is_active', true)->count(),
                'cells' => Cell::count(),
                'users' => User::count(),
                'audit_logs' => AuditLog::whereHas(
                    'user',
                    fn ($query) => $query->where('is_admin', true)
                )->count(),
            ],

            'recentLogs' => AuditLog::query()
                ->with([
                    'user:id,name,email',
                    'cell:id,name',
                ])
                ->whereHas(
                    'user',
                    fn ($query) => $query->where('is_admin', true)
                )
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'description' => $log->description,
                    'created_at' => $log->created_at?->toISOString(),

                    'user' => $log->user ? [
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,

                    'cell' => $log->cell ? [
                        'id' => $log->cell->id,
                        'name' => $log->cell->name,
                    ] : null,
                ]),

            'versionStatus' => $this->versionStatus(),

            'workerVersions' => $this->workerVersions(),

            'quickLinks' => [
                [
                    'label' => 'Get Help',
                    'description' => 'Join the Discord community.',
                    'url' => 'https://hivepanel.dev/r/discord',
                    'external' => true,
                ],
                [
                    'label' => 'Documentation',
                    'description' => 'Read the HivePanel docs.',
                    'url' => 'https://docs.hivepanel.dev',
                    'external' => true,
                ],
                [
                    'label' => 'GitHub',
                    'description' => 'View the source code.',
                    'url' => 'https://github.com/HiveDevelopment/HivePanel',
                    'external' => true,
                ],
                [
                    'label' => 'Support Project',
                    'description' => 'Help fund development.',
                    'url' => 'https://github.com/sponsors/HiveDevelopment',
                    'external' => true,
                ],
            ],
        ]);
    }

    private function versionStatus(): array
    {
        $current = $this->normaliseVersion(
            config(
                'hivepanel.version',
                config('app.version', '0.0.0')
            )
        );

        $latest = $this->latestPanelVersion();

        return [
            'current' => $current,
            'latest' => $latest,
            'is_outdated' => $latest !== null
                && version_compare($current, $latest, '<'),
            'checked' => $latest !== null,
        ];
    }

    private function workerVersions(): array
    {
        $latest = $this->latestWorkerVersion();
        $onlineThreshold = now()->subMinutes(2);

        /*
         * The dashboard is deliberately only a summary.
         *
         * Do not send hundreds/thousands of Workers through
         * Inertia. The full paginated Worker list belongs on
         * the Updates page.
         */
        return Node::query()
            ->where('is_active', true)
            ->select([
                'id',
                'name',
                'worker_version',
                'worker_hostname',
                'worker_platform',
                'last_seen_at',
            ])
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function (Node $node) use (
                $latest,
                $onlineThreshold
            ) {
                $current = $node->worker_version
                    ? $this->normaliseVersion(
                        $node->worker_version
                    )
                    : null;

                $reachable = $node->last_seen_at !== null
                    && $node->last_seen_at->gte(
                        $onlineThreshold
                    );

                return [
                    'id' => $node->id,
                    'name' => $node->name,
                    'version' => $current,
                    'latest_version' => $latest,

                    'reachable' => $reachable,

                    'version_available' =>
                        $current !== null
                        && $current !== '',

                    'latest_version_available' =>
                        $latest !== null,

                    'is_outdated' =>
                        $current !== null
                        && $current !== ''
                        && $latest !== null
                        && version_compare(
                            $current,
                            $latest,
                            '<'
                        ),

                    'last_seen_at' =>
                        $node->last_seen_at?->toISOString(),

                    'hostname' =>
                        $node->worker_hostname,

                    'platform' =>
                        $node->worker_platform,
                ];
            })
            ->values()
            ->all();
    }

    private function latestPanelVersion(): ?string
    {
        return Cache::remember(
            'hivepanel.latest_version',
            now()->addHour(),
            fn () => $this->latestGithubRelease(
                'HiveDevelopment',
                'HivePanel'
            )
        );
    }

    private function latestWorkerVersion(): ?string
    {
        return Cache::remember(
            'hivepanel.worker_latest_version',
            now()->addHour(),
            fn () => $this->latestGithubRelease(
                'HiveDevelopment',
                'HiveWorker'
            )
        );
    }

    private function latestGithubRelease(
        string $owner,
        string $repository
    ): ?string {
        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'HivePanel',
                ])
                ->get(
                    "https://api.github.com/repos/{$owner}/{$repository}/releases/latest"
                );

            if (! $response->successful()) {
                return null;
            }

            $tag = trim(
                (string) $response->json('tag_name')
            );

            if ($tag === '') {
                return null;
            }

            return $this->normaliseVersion($tag);
        } catch (Throwable) {
            return null;
        }
    }

    private function normaliseVersion(
        mixed $version
    ): string {
        return ltrim(
            trim((string) $version),
            'vV'
        );
    }
}