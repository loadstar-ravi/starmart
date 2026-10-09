<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /**
     * List the customer's own orders, newest first.
     */
    public function index(Request $request, OrderService $orderService): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $orders = $orderService
            ->paginateForUser($request->user(), $request->integer('per_page', OrderService::DEFAULT_PER_PAGE))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    /**
     * Show one of the customer's orders.
     */
    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load('items'));
    }

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
