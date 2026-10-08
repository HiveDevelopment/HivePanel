<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\AppSetting;
use App\Support\AppSettings;
use App\Models\OAuthProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\OidcProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'general' => $this->setting('general', [
                    'company_name' => 'HivePanel',
                    'company_logo' => null,
                    'require_2fa' => 'not_required',
                    'default_language' => 'en',
                ]),

                'security' => $this->setting('security', [
                    'allow_registration' => false,
                    'require_email_verification' => false,
                    'allow_passkeys' => true,
                    'session_lifetime' => 120,
                    'password_min_length' => 8,
                ]),

                'mail' => $this->safeMailSettings(),

                'captcha' => $this->safeCaptchaSettings(),
                'ai' => $this->safeAISettings(),
            ],

            'oauthProviders' => $this->oauthProviders(),

            'oidcProviders' => OidcProvider::query()
                ->orderBy('name')
                ->get()
                ->map(fn (OidcProvider $provider) => [
                    'id' => $provider->id,
                    'name' => $provider->name,
                    'slug' => $provider->slug,
                    'enabled' => $provider->enabled,
                    'issuer' => $provider->issuer,
                    'client_id' => $provider->client_id,
                    'client_secret' => '',
                    'redirect_url' => $provider->redirect_url
                        ?: url("/oidc/{$provider->slug}/callback"),
                    'scopes' => $provider->scopes ?? ['openid', 'profile', 'email'],
                    'allow_registration' => $provider->allow_registration,
                ])
                ->values(),
        ]);
    }

    private function safeAISettings(): array
    {
        $settings = \App\AI\AISettings::current();
        return ['enabled' => $settings['enabled'], 'provider' => $settings['provider'] === 'disabled' ? 'gemini' : $settings['provider'], 'model' => $settings['provider'] === 'disabled' ? 'gemini-2.5-flash-lite' : ($settings['model'] ?: 'gemini-2.5-flash-lite'), 'url' => $settings['url'], 'has_key' => filled($settings['key'])];
    }

    public function updateGeneral(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:100'],
            'company_logo' => ['nullable', 'string', 'max:2048'],
            'default_language' => ['required', 'string', 'max:10'],
        ]);

        $existing = $this->setting('general', [
            'require_2fa' => 'not_required',
        ]);

        $this->setSetting('general', [
            ...$existing,
            ...$data,
        ]);

        return back()->with('success', 'General settings updated.');
    }

    public function updateSecurity(Request $request)
    {
        $data = $request->validate([
            'allow_registration' => ['boolean'],
            'require_email_verification' => ['boolean'],
            'allow_passkeys' => ['boolean'],
            'session_lifetime' => ['required', 'integer', 'min:5', 'max:10080'],
            'password_min_length' => ['required', 'integer', 'min:8', 'max:128'],
            'require_2fa' => ['required', 'in:not_required,admin_only,all_users'],
        ]);

        $general = $this->setting('general', [
            'company_name' => 'HivePanel',
            'company_logo' => null,
            'require_2fa' => 'not_required',
            'default_language' => 'en',
        ]);

        $general['require_2fa'] = $data['require_2fa'];

        $this->setSetting('general', $general);

        unset($data['require_2fa']);

        $this->setSetting('security', $data);

        return back()->with('success', 'Security settings updated.');
    }

    public function updateMail(Request $request)
    {
        $data = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'in:none,tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = $this->setting('mail', []);

        if (! filled($data['password'] ?? null)) {
            $data['password'] = $existing['password'] ?? '';
        }

        $this->setSetting('mail', $data);

        return back()->with('success', 'Mail settings updated.');
    }

    public function testMail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $mail = $this->setting('mail', []);

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $mail['host'] ?? '');
        Config::set('mail.mailers.smtp.port', $mail['port'] ?? 587);
        Config::set('mail.mailers.smtp.encryption', ($mail['encryption'] ?? 'tls') === 'none' ? null : $mail['encryption']);
        Config::set('mail.mailers.smtp.username', $mail['username'] ?? null);
        Config::set('mail.mailers.smtp.password', $mail['password'] ?? null);
        Config::set('mail.from.address', $mail['from_address'] ?? config('mail.from.address'));
        Config::set('mail.from.name', $mail['from_name'] ?? 'HivePanel');

        Mail::to($data['email'])->send(new TestMail());

        return back()->with('success', 'Test email sent.');
    }

    public function updateCaptcha(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'provider' => ['required', 'in:turnstile,recaptcha,hcaptcha'],
            'site_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = $this->setting('captcha', []);

        if (! filled($data['secret_key'] ?? null)) {
            $data['secret_key'] = $existing['secret_key'] ?? '';
        }

        $this->setSetting('captcha', $data);

        return back()->with('success', 'Captcha settings updated.');
    }

    public function updateOAuth(Request $request)
    {
        $providers = ['discord', 'google', 'github'];

        $data = $request->validate([
            'providers' => ['required', 'array'],
        ]);

        foreach ($providers as $provider) {
            $payload = $request->input("providers.{$provider}", []);

            $validated = validator($payload, [
                'enabled' => ['boolean'],
                'client_id' => ['nullable', 'string', 'max:255'],
                'client_secret' => ['nullable', 'string', 'max:255'],
                'redirect_url' => ['nullable', 'url', 'max:2048'],
            ])->validate();

            $existing = OAuthProvider::firstOrCreate([
                'provider' => $provider,
            ]);

            $existing->update([
                'enabled' => (bool) ($validated['enabled'] ?? false),
                'client_id' => $validated['client_id'] ?? null,
                'client_secret' => filled($validated['client_secret'] ?? null)
                    ? $validated['client_secret']
                    : $existing->client_secret,
                'redirect_url' => $validated['redirect_url'] ?? null,
            ]);
        }

        return back()->with('success', 'OAuth settings updated.');
    }

    private function setting(string $key, array $default = []): array
    {
        return AppSetting::where('key', $key)->first()?->value ?? $default;
    }

    private function setSetting(string $key, array $value): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        AppSettings::clear($key);
    }

    private function safeMailSettings(): array
    {
        $mail = $this->setting('mail', [
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_address' => '',
            'from_name' => '',
        ]);

        $mail['password'] = '';

        return $mail;
    }

    private function safeCaptchaSettings(): array
    {
        $captcha = $this->setting('captcha', [
            'enabled' => false,
            'provider' => 'turnstile',
            'site_key' => '',
            'secret_key' => '',
        ]);

        $captcha['secret_key'] = '';

        return $captcha;
    }

    private function oauthProviders(): array
    {
        $providers = OAuthProvider::query()
            ->get()
            ->keyBy('provider');

        return collect(['discord', 'google', 'github'])
            ->mapWithKeys(fn ($provider) => [
                $provider => [
                    'provider' => $provider,
                    'enabled' => (bool) ($providers[$provider]->enabled ?? false),
                    'client_id' => $providers[$provider]->client_id ?? '',
                    'client_secret' => '',
                    'redirect_url' => $providers[$provider]->redirect_url
                        ?? url("/auth/{$provider}/callback"),
                ],
            ])
            ->all();
    }

    public function storeOidc(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'issuer' => ['required', 'url', 'max:2048'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:2048'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:100'],
            'allow_registration' => ['boolean'],
        ]);

        $slug = Str::slug($data['name']);

        $baseSlug = $slug;
        $suffix = 2;

        while (OidcProvider::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        OidcProvider::create([
            'name' => $data['name'],
            'slug' => $slug,
            'enabled' => true,
            'issuer' => rtrim($data['issuer'], '/'),
            'client_id' => $data['client_id'],
            'client_secret' => $data['client_secret'],
            'redirect_url' => url("/oidc/{$slug}/callback"),
            'scopes' => $data['scopes'] ?? ['openid', 'profile', 'email'],
            'allow_registration' => (bool) ($data['allow_registration'] ?? false),
        ]);

        return back()->with('success', 'OpenID Connect provider added.');
    }

    public function updateOidc(Request $request, OidcProvider $oidcProvider)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'enabled' => ['boolean'],
            'issuer' => ['required', 'url', 'max:2048'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2048'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:100'],
            'allow_registration' => ['boolean'],
        ]);

        $oidcProvider->update([
            'name' => $data['name'],
            'enabled' => (bool) ($data['enabled'] ?? false),
            'issuer' => rtrim($data['issuer'], '/'),
            'client_id' => $data['client_id'],
            'client_secret' => filled($data['client_secret'] ?? null)
                ? $data['client_secret']
                : $oidcProvider->client_secret,
            'scopes' => $data['scopes'] ?? ['openid', 'profile', 'email'],
            'allow_registration' => (bool) ($data['allow_registration'] ?? false),
        ]);

        return back()->with('success', 'OpenID Connect provider updated.');
    }

    public function destroyOidc(OidcProvider $oidcProvider)
    {
        $oidcProvider->delete();

        return back()->with('success', 'OpenID Connect provider removed.');
    }
}