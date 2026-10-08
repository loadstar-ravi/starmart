<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RegisteredUserController extends Controller
{
    /**
     * Register a customer and return an API token.
     */
    public function store(RegisterRequest $request, AuthService $authService): JsonResponse
    {
        $user = $authService->registerCustomer($request->validated());

        return (new UserResource($user))
            ->additional([
                'token' => $authService->issueApiToken($user),
                'token_type' => 'Bearer',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
