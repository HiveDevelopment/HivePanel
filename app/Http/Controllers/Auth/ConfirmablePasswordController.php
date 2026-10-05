<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password page.
     */
    public function show(Request $request): Response
    {
        $returnTo = $request->query('return');

        if (is_string($returnTo) && $this->isSafeReturnPath($returnTo)) {
            $request->session()->put('password_confirmation_return_to', $returnTo);
        }

        return Inertia::render('auth/ConfirmPassword', [
            'returnTo' => $request->session()->get('password_confirmation_return_to'),
        ]);
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        $returnTo = $request->session()->pull('password_confirmation_return_to');

        if (is_string($returnTo) && $this->isSafeReturnPath($returnTo)) {
            return redirect($returnTo);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Determine whether a return path is safe to redirect to.
     */
    private function isSafeReturnPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ! str_contains($path, "\r")
            && ! str_contains($path, "\n");
    }
}