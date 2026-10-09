<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
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
