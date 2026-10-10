<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
    /**
     * Change the name and email a user is known by.
     *
     * Only those two are taken from the details, whatever else was sent along.
     *
     * @param  array{name: string, email: string}  $details
     */
    public function updateProfile(User $user, array $details): User
    {
        $user->update([
            'name' => $details['name'],
            'email' => $details['email'],
        ]);

        return $user;
    }

    /**
     * Replace the user's password. The model hashes it when it is saved.
     */
    public function changePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);

        Log::info('User changed their password.', ['user_id' => $user->id]);
    }

    /**
     * Block a user from signing in.
     *
     * Their API tokens are revoked here, so the API stops answering them at once.
     * A website session they still have open is ended on their next request.
     * Blocking a user who is already blocked keeps the time they were first blocked.
     */
    public function block(User $user, User $admin): void
    {
        if ($user->isBlocked()) {
            return;
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['blocked_at' => now()])->save();
            $user->tokens()->delete();
        });

        Log::info('User blocked.', ['user_id' => $user->id, 'blocked_by' => $admin->id]);
    }

    /**
     * Let a blocked user sign in again.
     */
    public function unblock(User $user, User $admin): void
    {
        if (! $user->isBlocked()) {
            return;
        }

        $user->forceFill(['blocked_at' => null])->save();

        Log::info('User unblocked.', ['user_id' => $user->id, 'unblocked_by' => $admin->id]);
    }
}
