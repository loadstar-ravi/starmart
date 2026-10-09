<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    /**
     * Place an order for everything in the cart.
     */
    public function store(OrderRequest $request, OrderService $orderService): JsonResponse
    {
        try {
            $order = $orderService->placeOrder(
                $request->user(),
                $request->shippingAddress(),
                $request->paymentMethod(),
            );
        } catch (CartException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new OrderResource($order))
            ->additional(['message' => "Order {$order->order_number} placed."])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
