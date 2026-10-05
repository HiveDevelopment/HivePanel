<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response|RedirectResponse
    {
        $security = AppSettings::get('security', []);

        if (! (bool) ($security['allow_registration'] ?? false)) {
            return to_route('login')
                ->with('status', 'Account registration is currently disabled.');
        }

        return Inertia::render('auth/Register', [
            'passwordMinLength' => (int) ($security['password_min_length'] ?? 8),
            'passkeysEnabled' => (bool) ($security['allow_passkeys'] ?? true),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $security = AppSettings::get('security', []);

        abort_unless(
            (bool) ($security['allow_registration'] ?? false),
            403
        );

        $passwordMinLength = (int) ($security['password_min_length'] ?? 8);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
            ],
            'password' => [
                'required',
                'confirmed',
                Rules\Password::min($passwordMinLength),
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        if ((bool) ($security['allow_passkeys'] ?? true)) {
            return to_route('passkey.setup');
        }

        return to_route('dashboard');
    }
}