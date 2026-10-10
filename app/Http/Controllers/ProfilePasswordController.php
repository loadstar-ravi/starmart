<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilePasswordRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;

class ProfilePasswordController extends Controller
{
    /**
     * Change the customer's password. They stay signed in.
     */
    public function update(ProfilePasswordRequest $request, UserService $userService): RedirectResponse
    {
        $userService->changePassword($request->user(), $request->validated('password'));

        return redirect()->route('profile.edit')->with('status', 'Your password has been changed.');
    }
}
