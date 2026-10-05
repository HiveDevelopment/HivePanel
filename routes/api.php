<?php

use App\Http\Controllers\Admin\AdminCombImportController;
use App\Http\Controllers\Api\Workers\SftpAuthController;
use App\Http\Controllers\Api\Workers\WorkerHeartbeatController;
use App\Http\Controllers\Api\Workers\WorkerRegistrationController;
use App\Services\RegistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/worker/register', WorkerRegistrationController::class)
    ->middleware('throttle:20,1')
    ->name('worker.register');

Route::prefix('worker')
    ->middleware([
        'worker.auth',
        'throttle:120,1',
    ])
    ->group(function () {
        Route::post('/heartbeat', WorkerHeartbeatController::class)
            ->name('worker.heartbeat');

        Route::post('/sftp/auth', SftpAuthController::class)
            ->name('api.worker.sftp.auth');
    });

Route::get('/registry/combs', function (RegistryService $registry) {
    return $registry->getCombs();
});

Route::post('/registry/combs/{id}/import', [AdminCombImportController::class, 'import']);
Route::post('/registry/combs/sync', [AdminCombImportController::class, 'sync']);