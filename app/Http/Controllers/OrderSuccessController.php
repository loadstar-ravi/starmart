<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderSuccessController extends Controller
{
    /**
     * Show the confirmation of an order the customer placed.
     */
    public function __invoke(Request $request, string $orderNumber, OrderService $orderService): View
    {
        return view('orders.success', [
            'order' => $orderService->findForUser($request->user(), $orderNumber),
        ]);
    }
}
