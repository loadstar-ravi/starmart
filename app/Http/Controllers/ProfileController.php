<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the signed-in customer their details and the form to change their password.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the customer's name and email.
     */
    public function update(ProfileRequest $request, UserService $userService): RedirectResponse
    {
        $userService->updateProfile($request->user(), $request->validated());

        return redirect()->route('profile.edit')->with('status', 'Your details have been updated.');
    }
}
