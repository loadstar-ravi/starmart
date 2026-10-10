<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;

class ProfileController extends Controller
{
    /**
     * Update the customer's name and email.
     */
    public function update(ProfileRequest $request, UserService $userService): UserResource
    {
        $user = $userService->updateProfile($request->user(), $request->validated());

        return (new UserResource($user))->additional(['message' => 'Profile updated.']);
    }
}
