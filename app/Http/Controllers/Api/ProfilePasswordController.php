<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfilePasswordRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class ProfilePasswordController extends Controller
{
    /**
     * Change the customer's password. The token used for the request keeps working.
     */
    public function update(ProfilePasswordRequest $request, UserService $userService): JsonResponse
    {
        $userService->changePassword($request->user(), $request->validated('password'));

        return response()->json(['message' => 'Password changed.']);
    }
}
