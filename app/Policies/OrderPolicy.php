<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Decides who may act on an order. Whether an order can still be cancelled or paid
 * is a rule of the order itself, which the services check.
 */
class OrderPolicy
{
    /**
     * Customers may see only their own orders.
     */
    public function view(User $user, Order $order): Response
    {
        return $this->allowOwner($user, $order);
    }

    /**
     * Customers may cancel only their own orders.
     */
    public function cancel(User $user, Order $order): Response
    {
        return $this->allowOwner($user, $order);
    }

    /**
     * Someone else's order is reported as missing rather than forbidden,
     * so order numbers and ids cannot be probed.
     */
    private function allowOwner(User $user, Order $order): Response
    {
        return $order->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
