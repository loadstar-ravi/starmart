<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuthService;
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
     * Sign a customer in, unless an admin has blocked them.
     *
     * The block is only checked once the password has matched, so nobody
     * can find out that an account is blocked without knowing its password.
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            ...$request->safe()->only(['email', 'password']),
            'role' => UserRole::Customer->value,
        ];

        $blocked = false;

        $signedIn = Auth::attemptWhen($credentials, function (User $user) use (&$blocked) {
            return ! ($blocked = $user->isBlocked());
        }, $request->boolean('remember'));

        if (! $signedIn) {
            throw ValidationException::withMessages([
                'email' => $blocked ? AuthService::BLOCKED_MESSAGE : __('auth.failed'),
            ]);
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
