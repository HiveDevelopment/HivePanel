<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\AuthenticateWorker;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsureCellPermission;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $trustedProxies = array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
            );
        }
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdminAccess::class,
            'admin.permission' => EnsureAdminPermission::class,
            'cell.permission' => EnsureCellPermission::class,
            'worker.auth' => AuthenticateWorker::class,
        ]);
    })
    ->withSchedule(function ($schedule) {
        $schedule
            ->command('hivepanel:run-due-schedules')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
