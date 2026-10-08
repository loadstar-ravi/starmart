<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Show the customer registration form.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Register a customer and sign them in.
     */
    public function store(RegisterRequest $request, AuthService $authService): RedirectResponse
    {
        $user = $authService->registerCustomer($request->validated());

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Welcome to StarMart! Your account has been created.');
    }
}
