<?php

namespace App\Http\Middleware;

use App\Support\AppSettings;
use App\Services\HivePanelUpdateService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $update = null;

        if ($request->user()?->hasAdminPermission(\App\Support\AdminPermissions::UPDATES_VIEW)) {
            try {
                $release = app(HivePanelUpdateService::class)->latestRelease();
                $update = $release ? [
                    'available' => (bool) $release['available'],
                    'version' => $release['version'],
                ] : null;
            } catch (\Throwable) {
                $update = null;
            }
        }

        return [
            ...parent::share($request),

            'name' => config('app.name'),

            'appSettings' => [
                'name' => AppSettings::name(),
                'logo' => AppSettings::logo(),
                'general' => AppSettings::general(),
            ],

            'auth' => [
                'user' => $request->user(),
                'adminAccess' => $request->user()?->hasAdminAccess() ?? false,
                'adminPermissions' => $request->user()?->adminPermissions() ?? [],
            ],

            'hivePanelUpdate' => $update,

            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'warning' => fn (): ?string => $request->session()->get('warning'),
                'info' => fn (): ?string => $request->session()->get('info'),
            ],
        ];
    }
}