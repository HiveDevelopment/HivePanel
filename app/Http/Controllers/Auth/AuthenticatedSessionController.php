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

        return redirect()->to(
            $this->resolveIntendedRedirect($request)
        );
    }

    /**
     * Resolve a safe post-login destination.
     *
     * Only relative application paths or URLs matching the
     * configured application origin are accepted.
     */
    private function resolveIntendedRedirect(Request $request): string
    {
        $fallback = route('dashboard', absolute: false);

        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return $fallback;
        }

        // Reject protocol-relative URLs, backslashes and
        // control characters to prevent redirect ambiguity.
        if (
            str_starts_with($intended, '//') ||
            str_contains($intended, '\\') ||
            preg_match('/[\x00-\x1F\x7F]/', $intended)
        ) {
            return $fallback;
        }

        // Allow local application paths.
        if (str_starts_with($intended, '/')) {
            return $intended;
        }

        $appUrl = config('app.url');

        if (! is_string($appUrl) || $appUrl === '') {
            return $fallback;
        }

        $app = parse_url($appUrl);
        $target = parse_url($intended);

        if ($app === false || $target === false) {
            return $fallback;
        }

        $appHost = $app['host'] ?? null;
        $targetHost = $target['host'] ?? null;
        $targetScheme = strtolower($target['scheme'] ?? '');

        if (
            ! $appHost ||
            ! $targetHost ||
            ! in_array($targetScheme, ['http', 'https'], true) ||
            strcasecmp($appHost, $targetHost) !== 0 ||
            isset($target['user']) ||
            isset($target['pass'])
        ) {
            return $fallback;
        }

        // Keep redirects on the configured application port.
        $appPort = $app['port'] ?? null;
        $targetPort = $target['port'] ?? null;

        if ($appPort !== $targetPort) {
            return $fallback;
        }

        // Return a relative URL so the browser preserves
        // the current HTTPS origin behind reverse proxies.
        $path = $target['path'] ?? '/';

        if (
            ! str_starts_with($path, '/') ||
            str_starts_with($path, '//') ||
            str_contains($path, '\\')
        ) {
            return $fallback;
        }

        $query = isset($target['query'])
            ? '?'.$target['query']
            : '';

        $fragment = isset($target['fragment'])
            ? '#'.$target['fragment']
            : '';

        return $path.$query.$fragment;
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
