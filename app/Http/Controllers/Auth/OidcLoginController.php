<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OidcProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class OidcLoginController extends Controller
{
    /**
     * Redirect the user to the configured OpenID Connect provider.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $config = $this->providerConfig($provider);

        $this->configureProvider($config);

        return Socialite::driver("oidc_{$config->slug}")->redirect();
    }

    /**
     * Handle the OpenID Connect callback.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $config = $this->providerConfig($provider);

        $this->configureProvider($config);

        $socialUser = Socialite::driver("oidc_{$config->slug}")->user();

        $user = $this->resolveUser($socialUser, $config);

        if ($user->hasTwoFactorAuthenticationEnabled()) {
            $request->session()->put([
                'login.two_factor_user_id' => $user->getKey(),
                'login.remember' => true,
            ]);

            $request->session()->regenerate();

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Load an enabled OIDC provider.
     */
    private function providerConfig(string $provider): OidcProvider
    {
        $config = OidcProvider::query()
            ->where('slug', $provider)
            ->where('enabled', true)
            ->whereNotNull('client_id')
            ->whereNotNull('client_secret')
            ->first();

        abort_unless($config, 404);

        return $config;
    }

    /**
     * Configure the OIDC Socialite connection at runtime.
     */
    private function configureProvider(OidcProvider $provider): void
    {
        $scopes = $provider->scopes ?: [
            'openid',
            'profile',
            'email',
        ];

        if (! in_array('openid', $scopes, true)) {
            array_unshift($scopes, 'openid');
        }

        Config::set("oidc.connections.{$provider->slug}", [
            'base_url' => rtrim($provider->issuer, '/'),
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'redirect' => $provider->redirect_url
                ?: route('oidc.callback', $provider->slug),
            'scopes' => $scopes,
            'require_email' => true,
            'verify_jwt' => true,
            'use_nonce' => true,
        ]);
    }

    /**
     * Resolve the HivePanel user associated with the OIDC identity.
     */
    private function resolveUser(
        SocialiteUser $socialUser,
        OidcProvider $provider
    ): User {
        $email = $socialUser->getEmail();

        abort_unless(
            is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL),
            422,
            'No valid email address was returned by the identity provider.'
        );

        $email = Str::lower(trim($email));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user) {
            return $user;
        }

        abort_unless(
            $provider->allow_registration,
            403,
            'Your account is not authorised to access this HivePanel installation.'
        );

        return User::create([
            'name' => $socialUser->getName()
                ?: $socialUser->getNickname()
                ?: $email,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(64)),
        ]);
    }
}