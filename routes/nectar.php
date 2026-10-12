
<?php

use App\Http\Controllers\Admin\NectarExtensionController;
use App\Http\Controllers\NectarAssetController;
use App\Http\Controllers\NectarCapabilityController;
use App\Http\Controllers\NectarPageController;
use App\Http\Controllers\NectarEventController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Nectar Static Assets
|--------------------------------------------------------------------------
|
| Sandboxed extension iframes have an opaque origin and cannot rely on
| the user's authenticated session.
|
| Only static extension assets are publicly accessible.
| NectarAssetController must validate the extension, file path,
| and permitted file types before serving any content.
|
*/

Route::middleware(['web'])->group(function () {
    Route::get('/nectar/assets/{id}/{path}', [NectarAssetController::class, 'show'])
        ->where([
            'id' => '[a-z0-9.-]+',
            'path' => 'dist/[a-zA-Z0-9_./-]+',
        ])
        ->name('nectar.asset');
});

/*
|--------------------------------------------------------------------------
| Authenticated Nectar Routes
|--------------------------------------------------------------------------
|
| Extension pages, capabilities, navigation, slots and management
| remain protected.
|
*/

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::post('/nectar/capabilities/{id}/network/{transport}', [\App\Http\Controllers\NectarNetworkController::class, 'handle'])
        ->where(['id' => '[a-z0-9][a-z0-9.-]{2,159}', 'transport' => 'http|tcp'])
        ->middleware('throttle:60,1')->name('nectar.capabilities.network');

    Route::post('/nectar/capabilities/{id}/events', [NectarEventController::class, 'subscribe'])
        ->where('id', '[a-z0-9.-]+')->middleware('throttle:60,1')
        ->name('nectar.capabilities.events');

    Route::post('/nectar/capabilities/{id}/settings', [NectarCapabilityController::class, 'settings'])
        ->where('id', '[a-z0-9.-]+')->middleware('throttle:60,1')
        ->name('nectar.capabilities.settings');

    Route::post('/nectar/capabilities/{id}/cells', [NectarCapabilityController::class, 'cells'])
        ->where('id', '[a-z0-9.-]+')
        ->middleware('throttle:60,1')
        ->name('nectar.capabilities.cells');

    Route::get('/nectar/navigation', [NectarPageController::class, 'navigation'])
        ->name('nectar.navigation');

    Route::get('/cells/{cell}/extensions/{id}/{slug}', [NectarPageController::class, 'showCell'])
        ->where(['id' => '[a-z0-9.-]+', 'slug' => '[a-z][a-z0-9-]*'])
        ->middleware('cell.permission:console.view')->name('nectar.pages.cell');

    Route::get('/extensions/{id}/{slug}', [NectarPageController::class, 'show'])
        ->where([
            'id' => '[a-z0-9.-]+',
            'slug' => '[a-z][a-z0-9-]*',
        ])
        ->name('nectar.pages.show');

    Route::get('/nectar/slots', [NectarAssetController::class, 'slots'])
        ->name('nectar.slots');

    /*
    |--------------------------------------------------------------------------
    | Nectar Administration
    |--------------------------------------------------------------------------
    */

    Route::middleware('admin')
        ->prefix('admin/extensions')
        ->name('admin.extensions.')
        ->group(function () {
            Route::get('/', [NectarExtensionController::class, 'index'])
                ->name('index');

            Route::get('/install', [NectarExtensionController::class, 'create'])
                ->name('create');

            Route::post('/', [NectarExtensionController::class, 'store'])
                ->name('store');

            Route::get('/{id}', [NectarExtensionController::class, 'show'])
                ->where('id', '[a-z0-9.-]+')
                ->name('show');

            Route::get('/{id}/settings', [NectarExtensionController::class, 'settings'])
                ->where('id', '[a-z0-9.-]+')->name('settings');
            Route::put('/{id}/settings', [NectarExtensionController::class, 'saveSettings'])
                ->where('id', '[a-z0-9.-]+')->name('settings.save');

            Route::patch('/{id}', [NectarExtensionController::class, 'update'])
                ->where('id', '[a-z0-9.-]+')
                ->middleware('admin.permission:admin.extensions.update')->name('update');

            Route::delete('/{id}', [NectarExtensionController::class, 'destroy'])
                ->where('id', '[a-z0-9.-]+')
                ->name('destroy');
        });
});
