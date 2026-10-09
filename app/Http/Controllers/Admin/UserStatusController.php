<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserStatusController extends Controller
{
    /**
     * Block or unblock a customer.
     */
    public function update(Request $request, User $user, UserService $userService): RedirectResponse
    {
        Gate::authorize('block', $user);

        $request->validate([
            'blocked' => ['required', 'boolean'],
        ]);

        if ($request->boolean('blocked')) {
            $userService->block($user, $request->user());

            return back()->with('status', "{$user->name} is blocked and can no longer log in.");
        }

        $userService->unblock($user, $request->user());

        return back()->with('status', "{$user->name} is unblocked and can log in again.");
    }
}
