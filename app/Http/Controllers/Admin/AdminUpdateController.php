<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Services\HivePanelUpdateService;
use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AdminUpdateController extends Controller
{
    private const WORKERS_PER_PAGE = 25;
    private const WORKER_ONLINE_MINUTES = 2;

    public function index(
        Request $request,
        HivePanelUpdateService $updates
    ): Response {
        $latest = null;
        $error = null;

        try {
            $latest = $updates->latestRelease();
        } catch (Throwable) {
            $error = 'HivePanel could not check for updates right now.';
        }

        $latestWorkerVersion = $this->latestWorkerVersion();

        return Inertia::render('Admin/Updates/Index', [
            'currentVersion' => $updates->currentVersion(),
            'latestRelease' => $latest,
            'updateStatus' => $updates->status(),

            'canInstallUpdates' =>
                $request->user()?->hasAdminPermission(
                    AdminPermissions::UPDATES_INSTALL
                ) ?? false,

            'checkError' => $error,

            'latestWorkerVersion' => $latestWorkerVersion,

            'workers' => $this->workers(
                $request,
                $latestWorkerVersion
            ),

            'workerSummary' => $this->workerSummary(
                $latestWorkerVersion
            ),

            'workerFilters' => [
                'search' => trim(
                    (string) $request->query(
                        'worker_search',
                        ''
                    )
                ),

                'status' => $this->workerStatusFilter(
                    $request
                ),
            ],
        ]);
    }

    public function status(
        HivePanelUpdateService $updates
    ): JsonResponse {
        return response()->json(
            $updates->status()
        );
    }

    public function install(
        Request $request,
        HivePanelUpdateService $updates
    ): RedirectResponse {
        $data = $request->validate([
            'version' => [
                'required',
                'string',
                'max:64',
            ],
        ]);

        try {
            $updates->request(
                $data['version'],
                $request->user()->id
            );
        } catch (Throwable $exception) {
            return back()->withErrors([
                'update' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'HivePanel update has been queued.'
        );
    }

    private function workers(
        Request $request,
        ?string $latestVersion
    ): array {
        $search = trim(
            (string) $request->query(
                'worker_search',
                ''
            )
        );

        $status = $this->workerStatusFilter(
            $request
        );

        $onlineSince = now()->subMinutes(
            self::WORKER_ONLINE_MINUTES
        );

        $query = Node::query()
            ->where('is_active', true)
            ->select([
                'id',
                'name',
                'worker_version',
                'worker_hostname',
                'worker_platform',
                'last_seen_at',
            ]);

        if ($search !== '') {
            $query->where(function (
                Builder $query
            ) use ($search) {
                $query
                    ->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'worker_hostname',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'worker_version',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'worker_platform',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($status === 'offline') {
            $query->where(function (
                Builder $query
            ) use ($onlineSince) {
                $query
                    ->whereNull('last_seen_at')
                    ->orWhere(
                        'last_seen_at',
                        '<',
                        $onlineSince
                    );
            });
        }

        if ($status === 'unknown') {
            $query->where(function (
                Builder $query
            ) {
                $query
                    ->whereNull('worker_version')
                    ->orWhere(
                        'worker_version',
                        ''
                    );
            });
        }

        /*
         * MariaDB/MySQL cannot reliably compare arbitrary
         * semantic versions using a normal SQL comparison.
         *
         * For current/outdated filtering we therefore obtain
         * the small id/version dataset and perform the semantic
         * comparison in PHP. This still results in zero HTTP
         * requests to Workers.
         */
        if (
            in_array(
                $status,
                ['outdated', 'current'],
                true
            )
        ) {
            if ($latestVersion === null) {
                $query->whereRaw('1 = 0');
            } else {
                $matchingIds = $this
                    ->matchingWorkerVersionIds(
                        $status,
                        $latestVersion
                    );

                if ($matchingIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn(
                        'id',
                        $matchingIds->all()
                    );
                }
            }
        }

        $paginator = $query
            ->orderBy('name')
            ->paginate(
                self::WORKERS_PER_PAGE,
                ['*'],
                'worker_page'
            )
            ->withQueryString();

        return [
            'data' => collect(
                $paginator->items()
            )
                ->map(
                    fn (Node $node) =>
                        $this->workerPayload(
                            $node,
                            $latestVersion,
                            $onlineSince
                        )
                )
                ->values()
                ->all(),

            'current_page' =>
                $paginator->currentPage(),

            'last_page' =>
                $paginator->lastPage(),

            'per_page' =>
                $paginator->perPage(),

            'total' =>
                $paginator->total(),

            'from' =>
                $paginator->firstItem(),

            'to' =>
                $paginator->lastItem(),

            'prev_page_url' =>
                $paginator->previousPageUrl(),

            'next_page_url' =>
                $paginator->nextPageUrl(),
        ];
    }

    private function workerSummary(
        ?string $latestVersion
    ): array {
        $onlineSince = now()->subMinutes(
            self::WORKER_ONLINE_MINUTES
        );

        $nodes = Node::query()
            ->where('is_active', true)
            ->select([
                'id',
                'worker_version',
                'last_seen_at',
            ])
            ->get();

        $summary = [
            'total' => $nodes->count(),
            'up_to_date' => 0,
            'outdated' => 0,
            'offline' => 0,
            'unknown' => 0,
        ];

        foreach ($nodes as $node) {
            $online =
                $node->last_seen_at !== null
                && $node->last_seen_at->gte(
                    $onlineSince
                );

            if (! $online) {
                $summary['offline']++;
            }

            if (
                $node->worker_version === null
                || trim(
                    (string) $node->worker_version
                ) === ''
            ) {
                $summary['unknown']++;

                continue;
            }

            if ($latestVersion === null) {
                continue;
            }

            $current = $this->normaliseVersion(
                $node->worker_version
            );

            if (
                version_compare(
                    $current,
                    $latestVersion,
                    '<'
                )
            ) {
                $summary['outdated']++;
            } else {
                $summary['up_to_date']++;
            }
        }

        return $summary;
    }

    private function matchingWorkerVersionIds(
        string $status,
        string $latestVersion
    ): Collection {
        return Node::query()
            ->where('is_active', true)
            ->whereNotNull('worker_version')
            ->where('worker_version', '!=', '')
            ->select([
                'id',
                'worker_version',
            ])
            ->get()
            ->filter(function (
                Node $node
            ) use (
                $status,
                $latestVersion
            ) {
                $current =
                    $this->normaliseVersion(
                        $node->worker_version
                    );

                $outdated =
                    version_compare(
                        $current,
                        $latestVersion,
                        '<'
                    );

                return $status === 'outdated'
                    ? $outdated
                    : ! $outdated;
            })
            ->pluck('id')
            ->values();
    }

    private function workerPayload(
        Node $node,
        ?string $latestVersion,
        $onlineSince
    ): array {
        $version = null;

        if (
            $node->worker_version !== null
            && trim(
                (string) $node->worker_version
            ) !== ''
        ) {
            $version = $this->normaliseVersion(
                $node->worker_version
            );
        }

        $online =
            $node->last_seen_at !== null
            && $node->last_seen_at->gte(
                $onlineSince
            );

        $outdated =
            $version !== null
            && $latestVersion !== null
            && version_compare(
                $version,
                $latestVersion,
                '<'
            );

        return [
            'id' => $node->id,
            'name' => $node->name,

            'hostname' =>
                $node->worker_hostname,

            'platform' =>
                $node->worker_platform,

            'version' =>
                $version,

            'latest_version' =>
                $latestVersion,

            'online' =>
                $online,

            'outdated' =>
                $outdated,

            'version_available' =>
                $version !== null,

            'last_seen_at' =>
                $node->last_seen_at
                    ?->toISOString(),
        ];
    }

    private function workerStatusFilter(
        Request $request
    ): string {
        $status = strtolower(
            trim(
                (string) $request->query(
                    'worker_status',
                    'all'
                )
            )
        );

        return in_array(
            $status,
            [
                'all',
                'outdated',
                'current',
                'offline',
                'unknown',
            ],
            true
        )
            ? $status
            : 'all';
    }

    private function latestWorkerVersion(): ?string
    {
        return Cache::remember(
            'hivepanel.worker_latest_version',
            now()->addHour(),
            function () {
                try {
                    $response = Http::timeout(5)
                        ->acceptJson()
                        ->withHeaders([
                            'User-Agent' =>
                                'HivePanel',
                        ])
                        ->get(
                            'https://api.github.com/repos/HiveDevelopment/HiveWorker/releases/latest'
                        );

                    if (! $response->successful()) {
                        return null;
                    }

                    $tag = trim(
                        (string) $response->json(
                            'tag_name'
                        )
                    );

                    if ($tag === '') {
                        return null;
                    }

                    return $this->normaliseVersion(
                        $tag
                    );
                } catch (Throwable) {
                    return null;
                }
            }
        );
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