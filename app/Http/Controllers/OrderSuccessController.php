<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderSuccessController extends Controller
{
    /**
     * Show the confirmation of an order the customer placed.
     */
    public function __invoke(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('orders.success', [
            'order' => $order->load('items'),
        ]);
    }
}
