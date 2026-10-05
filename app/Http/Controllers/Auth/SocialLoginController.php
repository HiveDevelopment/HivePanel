<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OAuthProvider;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\Config;

class SocialLoginController extends Controller
{
    private const SUPPORTED_PROVIDERS = [
        'discord',
        'google',
        'github',
    ];

    /**
     * Redirect the user to the configured OAuth provider.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $config = $this->providerConfig($provider);

        return $this->socialiteDriver($provider, $config)->redirect();
    }

    /**
     * Handle the OAuth provider callback.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $config = $this->providerConfig($provider);

        $socialUser = $this->socialiteDriver($provider, $config)->user();

        $user = $this->resolveUser($socialUser);

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
     * Create the configured Socialite driver.
     */
    private function socialiteDriver(string $provider, OAuthProvider $config)
    {
        return Socialite::driver($provider)
            ->setConfig(new Config(
                $config->client_id,
                $config->client_secret,
                $config->redirect_url
            ));
    }

    /**
     * Resolve a local user from the OAuth identity.
     */
    private function resolveUser(SocialiteUser $socialUser): User
    {
        $email = $socialUser->getEmail();

        abort_unless(
            is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL),
            422,
            'No valid email address was returned by the provider.'
        );

        $email = Str::lower(trim($email));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user) {
            return $user;
        }

        $security = AppSettings::security();

        abort_unless(
            (bool) $security['allow_registration'],
            403,
            'Registration is currently disabled.'
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

    /**
     * Get the enabled configuration for a supported provider.
     */
    private function providerConfig(string $provider): OAuthProvider
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS, true), 404);

        $config = OAuthProvider::query()
            ->where('provider', $provider)
            ->where('enabled', true)
            ->whereNotNull('client_id')
            ->whereNotNull('client_secret')
            ->first();

        abort_unless($config, 404);

        return $config;
    }
}