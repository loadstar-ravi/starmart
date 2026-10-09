<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrderCancellationController extends Controller
{
    /**
     * Cancel one of the customer's orders.
     */
    public function store(Order $order, OrderService $orderService): OrderResource|JsonResponse
    {
        Gate::authorize('cancel', $order);

        try {
            $order = $orderService->cancel($order);
        } catch (OrderException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new OrderResource($order))->additional([
            'message' => $order->payment_status === PaymentStatus::Refunded
                ? "Order {$order->order_number} is cancelled and the payment has been refunded."
                : "Order {$order->order_number} is cancelled.",
        ]);
    }
}
