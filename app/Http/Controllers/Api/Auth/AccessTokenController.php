<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccessTokenController extends Controller
{
    /**
     * Exchange valid credentials for an API token, unless an admin has blocked the user.
     */
    public function store(LoginRequest $request, AuthService $authService): JsonResponse
    {
        $user = $authService->findUserByCredentials(
            $request->validated('email'),
            $request->validated('password'),
        );

        if ($user === null) {
            return response()->json(['message' => __('auth.failed')], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isBlocked()) {
            return response()->json(['message' => AuthService::BLOCKED_MESSAGE], Response::HTTP_FORBIDDEN);
        }

        return (new UserResource($user))
            ->additional([
                'token' => $authService->issueApiToken($user),
                'token_type' => 'Bearer',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
