<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\AppSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $security = AppSettings::security();
        $general = AppSettings::general();

        $passkeys = $security['allow_passkeys']
            ? $user->passkeys()
                ->latest()
                ->get()
                ->map(fn ($passkey) => [
                    'id' => $passkey->getKey(),
                    'name' => $passkey->name,
                    'created_at' => $passkey->created_at?->toISOString(),
                    'last_used_at' => $passkey->last_used_at?->toISOString(),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('settings/Security', [
            'passkeysEnabled' => (bool) $security['allow_passkeys'],
            'passwordMinLength' => (int) $security['password_min_length'],
            'passkeys' => $passkeys,
            'twoFactor' => [
                'enabled' => $user->hasTwoFactorAuthenticationEnabled(),
                'confirmed_at' => $user->two_factor_confirmed_at?->toISOString(),
                'required' => $this->twoFactorRequired($user, $general),
            ],
        ]);
    }

    private function twoFactorRequired($user, array $general): bool
    {
        return match ($general['require_2fa'] ?? 'not_required') {
            'all_users' => true,
            'admin_only' => (bool) $user->is_admin,
            default => false,
        };
    }
}