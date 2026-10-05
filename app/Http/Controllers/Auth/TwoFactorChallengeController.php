<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.two_factor_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/TwoFactorChallenge', [
            'recovery' => (bool) $request->session()->get('login.two_factor_recovery', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user->hasTwoFactorAuthenticationEnabled()) {
            return $this->completeLogin($request, $user);
        }

        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($user->two_factor_secret, preg_replace('/\s+/', '', $validated['code']))) {
            throw ValidationException::withMessages([
                'code' => ['The authentication code is invalid.'],
            ]);
        }

        return $this->completeLogin($request, $user);
    }

    public function recovery(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recovery_code' => ['required', 'string'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user->hasTwoFactorAuthenticationEnabled()) {
            return $this->completeLogin($request, $user);
        }

        $submittedCode = Str::lower(trim($validated['recovery_code']));
        $recoveryCodes = $user->two_factor_recovery_codes ?? [];

        $matchedIndex = collect($recoveryCodes)->search(
            fn ($code) => hash_equals(Str::lower($code), $submittedCode)
        );

        if ($matchedIndex === false) {
            throw ValidationException::withMessages([
                'recovery_code' => ['The recovery code is invalid.'],
            ]);
        }

        unset($recoveryCodes[$matchedIndex]);

        $user->forceFill([
            'two_factor_recovery_codes' => array_values($recoveryCodes),
        ])->save();

        return $this->completeLogin($request, $user);
    }

    private function pendingUser(Request $request): User
    {
        $userId = $request->session()->get('login.two_factor_user_id');

        if (! $userId) {
            throw ValidationException::withMessages([
                'code' => ['Your login session has expired. Please sign in again.'],
            ]);
        }

        $user = User::find($userId);

        if (! $user) {
            $this->clearPendingLogin($request);

            throw ValidationException::withMessages([
                'code' => ['Your login session has expired. Please sign in again.'],
            ]);
        }

        return $user;
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        $remember = (bool) $request->session()->pull('login.remember', false);

        $this->clearPendingLogin($request);

        Auth::login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function clearPendingLogin(Request $request): void
    {
        $request->session()->forget([
            'login.two_factor_user_id',
            'login.remember',
            'login.two_factor_recovery',
        ]);
    }
}