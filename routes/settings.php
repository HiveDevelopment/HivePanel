<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::redirect('settings/password', 'settings/security')->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('settings/two-factor', [TwoFactorAuthenticationController::class, 'enable'])
        ->middleware('password.confirm')
        ->name('two-factor.enable');

    Route::post('settings/two-factor/confirm', [TwoFactorAuthenticationController::class, 'confirm'])
        ->name('two-factor.confirm');

    Route::delete('settings/two-factor', [TwoFactorAuthenticationController::class, 'disable'])
        ->middleware('password.confirm')
        ->name('two-factor.disable');

    Route::post('settings/two-factor/recovery-codes', [TwoFactorAuthenticationController::class, 'recoveryCodes'])
        ->middleware('password.confirm')
        ->name('two-factor.recovery-codes');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});