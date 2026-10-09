<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * List every customer's orders, newest first, optionally filtered by search term,
     * order status and payment status.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
        ]);

        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $paymentStatus = $filters['payment_status'] ?? null;

        $orders = Order::query()
            ->with('user')
            ->when($search !== null, fn (Builder $query) => $query->search($search))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($paymentStatus !== null, fn (Builder $query) => $query->where('payment_status', $paymentStatus))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
            'filters' => ['search' => $search, 'status' => $status, 'payment_status' => $paymentStatus],
        ]);
    }

    /**
     * Show an order with its customer, lines and payment attempts.
     */
    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load([
                'user',
                'items',
                'payments' => fn (HasMany $payments) => $payments->orderBy('id'),
            ]),
        ]);
    }
}
