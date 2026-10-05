<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class AppSettings
{
    private const DEFAULTS = [
        'general' => [
            'company_name' => 'HivePanel',
            'company_logo' => null,
            'require_2fa' => 'not_required',
            'default_language' => 'en',
        ],
        'security' => [
            'allow_registration' => false,
            'require_email_verification' => false,
            'allow_passkeys' => true,
            'session_lifetime' => 120,
            'password_min_length' => 8,
        ],
        'mail' => [
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_address' => '',
            'from_name' => '',
        ],
        'captcha' => [
            'enabled' => false,
            'provider' => 'turnstile',
            'site_key' => '',
            'secret_key' => '',
        ],
    ];

    /**
     * Get a settings group with its defaults applied.
     */
    public static function get(string $key, array $default = []): array
    {
        $defaults = array_replace(
            self::DEFAULTS[$key] ?? [],
            $default
        );

        $stored = Cache::rememberForever("settings:{$key}", function () use ($key) {
            return AppSetting::where('key', $key)->first()?->value ?? [];
        });

        if (! is_array($stored)) {
            $stored = [];
        }

        return array_replace($defaults, $stored);
    }

    /**
     * Get the default values for a settings group.
     */
    public static function defaults(string $key): array
    {
        return self::DEFAULTS[$key] ?? [];
    }

    /**
     * Clear the cached settings group.
     */
    public static function clear(string $key): void
    {
        Cache::forget("settings:{$key}");
    }

    /**
     * Get the general application settings.
     */
    public static function general(): array
    {
        return self::get('general');
    }

    /**
     * Get the security settings.
     */
    public static function security(): array
    {
        return self::get('security');
    }

    /**
     * Get the mail settings.
     */
    public static function mail(): array
    {
        return self::get('mail');
    }

    /**
     * Get the CAPTCHA settings.
     */
    public static function captcha(): array
    {
        return self::get('captcha');
    }

    /**
     * Get the configured application name.
     */
    public static function name(): string
    {
        return self::general()['company_name'] ?? 'HivePanel';
    }

    /**
     * Get the configured application logo.
     */
    public static function logo(): ?string
    {
        return self::general()['company_logo'] ?? null;
    }
}