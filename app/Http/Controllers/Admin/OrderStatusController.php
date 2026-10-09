<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderStatusController extends Controller
{
    /**
     * Move an order to a later status, or cancel it.
     */
    public function update(Request $request, Order $order, OrderService $orderService): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        try {
            $order = $orderService->updateStatus($order, $request->enum('status', OrderStatus::class));
        } catch (OrderException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', $this->confirmationFor($order));
    }

    /**
     * Describe what the status change did to the order.
     */
    private function confirmationFor(Order $order): string
    {
        if ($order->status !== OrderStatus::Cancelled) {
            return "Order {$order->order_number} marked as ".Str::lower($order->status->label()).'.';
        }

        return $order->payment_status === PaymentStatus::Refunded
            ? "Order {$order->order_number} cancelled and its payment refunded."
            : "Order {$order->order_number} cancelled.";
    }
}
