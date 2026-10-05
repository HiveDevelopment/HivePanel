<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\OAuthProvider;
use App\Models\OidcProvider;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        $security = AppSettings::security();

        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),

            'oauthProviders' => OAuthProvider::query()
                ->where('enabled', true)
                ->whereNotNull('client_id')
                ->whereNotNull('client_secret')
                ->get(['provider']),

            'oidcProviders' => OidcProvider::query()
                ->where('enabled', true)
                ->whereNotNull('client_id')
                ->whereNotNull('client_secret')
                ->orderBy('name')
                ->get()
                ->map(fn (OidcProvider $provider) => [
                    'name' => $provider->name,
                    'slug' => $provider->slug,
                ])
                ->values(),

            'passkeysEnabled' => (bool) $security['allow_passkeys'],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();
        $remember = $request->boolean('remember');

        if ($user->hasTwoFactorAuthenticationEnabled()) {
            Auth::guard('web')->logout();

            $request->session()->put([
                'login.two_factor_user_id' => $user->getKey(),
                'login.remember' => $remember,
            ]);

            $request->session()->regenerate();

            return redirect()->route('two-factor.login');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}