<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Http\Requests\PaymentRequest;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Show the payment page of an order, or the order itself when nothing is left to pay online.
     */
    public function create(Request $request, string $orderNumber, OrderService $orderService): View|RedirectResponse
    {
        $order = $orderService->findForUser($request->user(), $orderNumber);

        if (! $order->isAwaitingPayment()) {
            return redirect()->route('orders.success', $order->order_number);
        }

        return view('payments.create', [
            'order' => $order,
            'lastAttemptFailed' => $order->payment_status === PaymentStatus::Failed,
        ]);
    }

    /**
     * Take the payment, then show the order, or the payment page again when it was declined.
     */
    public function store(PaymentRequest $request, PaymentService $paymentService): RedirectResponse
    {
        try {
            $payment = $paymentService->process(
                $request->user(),
                $request->integer('order_id'),
                $request->validated('amount'),
                $request->simulatesFailure(),
            );
        } catch (PaymentException $exception) {
            return redirect()
                ->route('orders.success', $exception->order->order_number)
                ->with('error', $exception->getMessage());
        }

        if ($payment->status === PaymentStatus::Failed) {
            return redirect()
                ->route('payments.create', $payment->order->order_number)
                ->with('error', "{$payment->failure_reason} You can try again.");
        }

        return redirect()
            ->route('orders.success', $payment->order->order_number)
            ->with('status', 'Payment received. Thank you!');
    }
}
