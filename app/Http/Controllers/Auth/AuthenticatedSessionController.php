<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the customer login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Sign a customer in.
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            ...$request->safe()->only(['email', 'password']),
            'role' => UserRole::Customer->value,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * Sign the current user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
