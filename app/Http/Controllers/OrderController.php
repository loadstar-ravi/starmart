<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * List the signed-in customer's orders, newest first.
     */
    public function index(Request $request, OrderService $orderService): View
    {
        return view('orders.index', [
            'orders' => $orderService->paginateForUser($request->user()),
        ]);
    }

    /**
     * Show one of the customer's orders.
     */
    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('orders.show', [
            'order' => $order->load('items'),
        ]);
    }
}
