<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\AppSettings;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationController extends Controller
{
    /**
     * Begin two-factor authentication setup.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(
            $user->hasTwoFactorAuthenticationEnabled(),
            422,
            'Two-factor authentication is already enabled.'
        );

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $otpauthUrl = $google2fa->getQRCodeUrl(
            AppSettings::name(),
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(240, 0),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return response()->json([
            'secret' => $secret,
            'qr_code' => $writer->writeString($otpauthUrl),
        ]);
    }

    /**
     * Confirm two-factor authentication setup.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = $request->user();

        abort_unless(
            filled($user->two_factor_secret),
            422,
            'Two-factor authentication setup has not been started.'
        );

        abort_if(
            $user->hasTwoFactorAuthenticationEnabled(),
            422,
            'Two-factor authentication is already enabled.'
        );

        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey(
            $user->two_factor_secret,
            $validated['code']
        )) {
            return response()->json([
                'message' => 'The authentication code is invalid.',
                'errors' => [
                    'code' => [
                        'The authentication code is invalid. Check your authenticator app and try again.',
                    ],
                ],
            ], 422);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return response()->json([
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable two-factor authentication.
     */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', 'Two-factor authentication disabled.');
    }

    /**
     * Generate a new set of recovery codes.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasTwoFactorAuthenticationEnabled(),
            422,
            'Two-factor authentication is not enabled.'
        );

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return response()->json([
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Generate single-use recovery codes.
     */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::lower(
                Str::random(5).'-'.Str::random(5)
            ))
            ->all();
    }
}