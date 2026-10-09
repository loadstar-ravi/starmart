<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stands in for a payment gateway: no money moves, and each attempt succeeds unless a failure is asked for.
 */
class PaymentService
{
    private const string DECLINE_REASON = 'The payment was declined by the bank.';

    /**
     * Take the online payment for one of the user's own orders.
     *
     * The amount must be what the order costs; it is checked against the order, never trusted.
     * The order row is locked while the attempt is decided, so two requests cannot both pay for it.
     * A declined payment is a normal result, not an error: it comes back as a failed payment.
     *
     * @throws PaymentException
     * @throws ModelNotFoundException<Order>
     */
    public function process(User $user, int $orderId, float|int|string $amount, bool $simulateFailure = false): Payment
    {
        try {
            $payment = DB::transaction(function () use ($user, $orderId, $amount, $simulateFailure) {
                $order = $user->orders()->lockForUpdate()->findOrFail($orderId);

                $this->ensurePayable($order, $amount);

                $payment = $this->pendingPaymentFor($order);

                $payment->update($simulateFailure ? [
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => self::DECLINE_REASON,
                ] : [
                    'status' => PaymentStatus::Success,
                    'transaction_reference' => 'TXN-'.Str::upper(Str::random(16)),
                    'paid_at' => now(),
                ]);

                $order->update(['payment_status' => $payment->status]);

                return $payment->setRelation('order', $order);
            });
        } catch (PaymentException $exception) {
            Log::notice('Payment refused.', [
                'user_id' => $user->id,
                'order_id' => $exception->order->id,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->logResult($payment);

        return $payment;
    }

    /**
     * @throws PaymentException
     */
    private function ensurePayable(Order $order, float|int|string $amount): void
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw PaymentException::orderCancelled($order);
        }

        if ($order->payment_method !== PaymentMethod::Online) {
            throw PaymentException::cashOnDelivery($order);
        }

        if ($order->payment_status === PaymentStatus::Success) {
            throw PaymentException::alreadyPaid($order);
        }

        if ($order->payment_status === PaymentStatus::Refunded) {
            throw PaymentException::refunded($order);
        }

        if ($this->inPaise($amount) !== $this->inPaise($order->total_amount)) {
            throw PaymentException::amountMismatch($order);
        }
    }

    /**
     * Get the attempt waiting to be decided: the one made with the order,
     * or a new one when every earlier attempt has failed.
     */
    private function pendingPaymentFor(Order $order): Payment
    {
        return $order->payments()->where('status', PaymentStatus::Pending)->latest('id')->first()
            ?? $order->payments()->create(['amount' => $order->total_amount]);
    }

    /**
     * Convert an amount to whole paise, so "500", 500.0 and "500.00" compare as equal.
     */
    private function inPaise(float|int|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function logResult(Payment $payment): void
    {
        $context = [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'order_number' => $payment->order->order_number,
            'amount' => $payment->amount,
        ];

        if ($payment->status === PaymentStatus::Success) {
            Log::info('Payment succeeded.', [...$context, 'transaction_reference' => $payment->transaction_reference]);

            return;
        }

        Log::warning('Payment failed.', [...$context, 'reason' => $payment->failure_reason]);
    }
}
