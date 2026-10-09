<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Only an admin may block or unblock, and only a customer can be blocked.
     * That keeps admins from locking themselves or each other out of the admin panel.
     */
    public function block(User $user, User $target): bool
    {
        return $user->isAdmin() && $target->isCustomer();
    }
}
