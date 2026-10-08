<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Register a new customer account.
     *
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function registerCustomer(array $attributes): User
    {
        return User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
        ]);
    }

    /**
     * Find the user matching the given credentials, if any.
     */
    public function findUserByCredentials(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Issue an API token whose only ability is the user's role.
     */
    public function issueApiToken(User $user): string
    {
        return $user->createToken('api-token', [$user->role->value])->plainTextToken;
    }
}
