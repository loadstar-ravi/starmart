<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class OrderCancellationController extends Controller
{
    /**
     * Cancel one of the customer's orders.
     */
    public function store(Order $order, OrderService $orderService): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        try {
            $order = $orderService->cancel($order);
        } catch (OrderException $exception) {
            return redirect()->route('orders.show', $order->order_number)->with('error', $exception->getMessage());
        }

        return redirect()->route('orders.show', $order->order_number)->with(
            'status',
            $order->payment_status === PaymentStatus::Refunded
                ? "Order {$order->order_number} is cancelled and your payment has been refunded."
                : "Order {$order->order_number} is cancelled.",
        );
    }
}
