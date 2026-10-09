<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CartException;
use App\Exceptions\OrderException;
use App\Jobs\SendOrderConfirmationJob;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    public const int DEFAULT_PER_PAGE = 10;

    private const string CANCELLED_BEFORE_PAYMENT = 'The order was cancelled before it was paid.';

    /**
     * Place an order for everything in the user's cart.
     *
     * Prices and the total come from the products as they are now, never from the request.
     * Everything is written in one transaction, so a failure leaves nothing half-saved.
     *
     * @param  array{name: string, mobile: string, email: string, address: string, city: string, state: string, pincode: string}  $shippingAddress
     *
     * @throws CartException
     */
    public function placeOrder(User $user, array $shippingAddress, PaymentMethod $paymentMethod): Order
    {
        try {
            $order = DB::transaction(fn () => $this->createOrderFromCart($user, $shippingAddress, $paymentMethod));
        } catch (CartException $exception) {
            Log::notice('Order refused.', ['user_id' => $user->id, 'reason' => $exception->getMessage()]);

            throw $exception;
        }

        Log::info('Order placed.', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $user->id,
            'total_amount' => $order->total_amount,
            'payment_method' => $paymentMethod->value,
            'stock_reduced_by_product' => $order->items->pluck('quantity', 'product_id')->all(),
        ]);

        SendOrderConfirmationJob::dispatch($order);

        return $order;
    }

    /**
     * Paginate the user's own orders with their lines, newest first.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForUser(User $user, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        return $user->orders()
            ->with('items')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Cancel an order: put its stock back and settle its payment.
     *
     * A paid order is refunded; an unpaid one has its open payment closed. The order row is
     * locked and everything is written in one transaction, so an order cannot be cancelled
     * twice and a failure leaves nothing half-done.
     *
     * @throws OrderException
     */
    public function cancel(Order $order): Order
    {
        try {
            $order = DB::transaction(function () use ($order) {
                $order = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);

                if (! $order->status->isCancellable()) {
                    throw OrderException::cannotBeCancelled($order);
                }

                $this->restoreStock($order);

                $order->update([
                    'status' => OrderStatus::Cancelled,
                    'payment_status' => $this->settlePayments($order),
                ]);

                return $order;
            });
        } catch (OrderException $exception) {
            Log::notice('Order cancellation refused.', [
                'order_id' => $exception->order->id,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Order cancelled.', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $order->user_id,
            'payment_status' => $order->payment_status->value,
            'stock_restored_by_product' => $order->items
                ->whereNotNull('product_id')
                ->pluck('quantity', 'product_id')
                ->all(),
        ]);

        return $order;
    }

    /**
     * Move an order to a later status on behalf of the shop.
     *
     * Cancelling goes through cancel(), so the stock and the payment are settled exactly as
     * when the customer cancels. A cash on delivery order is recorded as paid once it is delivered.
     *
     * @throws OrderException
     */
    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        if ($status === OrderStatus::Cancelled) {
            return $this->cancel($order);
        }

        try {
            [$order, $previousStatus] = DB::transaction(function () use ($order, $status) {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);

                if (! $order->status->canMoveTo($status)) {
                    throw OrderException::cannotBeMovedTo($order, $status);
                }

                $previousStatus = $order->status;

                $order->update([
                    'status' => $status,
                    'payment_status' => $status === OrderStatus::Delivered
                        ? $this->collectCashOnDelivery($order)
                        : $order->payment_status,
                ]);

                return [$order, $previousStatus];
            });
        } catch (OrderException $exception) {
            Log::notice('Order status change refused.', [
                'order_id' => $exception->order->id,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Order status updated.', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'from' => $previousStatus->value,
            'to' => $order->status->value,
            'payment_status' => $order->payment_status->value,
        ]);

        return $order;
    }

    /**
     * Cash on delivery is paid when the order is handed over, so record that payment
     * and return the payment status the delivered order ends up with.
     */
    private function collectCashOnDelivery(Order $order): PaymentStatus
    {
        if ($order->payment_method !== PaymentMethod::Cod || $order->payment_status !== PaymentStatus::Pending) {
            return $order->payment_status;
        }

        $order->payments()
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => PaymentStatus::Success, 'paid_at' => now()]);

        return PaymentStatus::Success;
    }

    /**
     * Give back the quantity of every line whose product still exists.
     *
     * The products are locked in id order, the same order checkout uses, so the two cannot deadlock.
     */
    private function restoreStock(Order $order): void
    {
        $quantities = $order->items->whereNotNull('product_id')->pluck('quantity', 'product_id');

        Product::query()
            ->whereKey($quantities->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(fn (Product $product) => $product->increment('stock', $quantities[$product->id]));
    }

    /**
     * Refund the order when it was paid, otherwise close its open payment,
     * and return the payment status the cancelled order ends up with.
     */
    private function settlePayments(Order $order): PaymentStatus
    {
        if ($order->payment_status === PaymentStatus::Success) {
            $order->payments()
                ->where('status', PaymentStatus::Success)
                ->update(['status' => PaymentStatus::Refunded]);

            return PaymentStatus::Refunded;
        }

        $order->payments()
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => PaymentStatus::Failed, 'failure_reason' => self::CANCELLED_BEFORE_PAYMENT]);

        return PaymentStatus::Failed;
    }

    /**
     * Write the order, its lines and its payment, take the stock and empty the cart.
     *
     * The cart row is locked first, so a double submit finds the cart already empty.
     *
     * @param  array<string, string>  $shippingAddress
     *
     * @throws CartException
     */
    private function createOrderFromCart(User $user, array $shippingAddress, PaymentMethod $paymentMethod): Order
    {
        $cart = $user->cart()->lockForUpdate()->first();

        if ($cart === null || $cart->items->isEmpty()) {
            throw CartException::emptyCart();
        }

        $this->lockAndCheckProducts($cart->items);

        $order = $this->createOrder($user, $cart->total(), $shippingAddress, $paymentMethod);

        $items = $order->items()->createMany($cart->items->map(fn (CartItem $item) => [
            'product_id' => $item->product_id,
            'product_name' => $item->product->name,
            'unit_price' => $item->product->price,
            'quantity' => $item->quantity,
            'line_total' => $item->lineTotal(),
        ])->all());

        foreach ($cart->items as $item) {
            $item->product->decrement('stock', $item->quantity);
        }

        $order->payments()->create(['amount' => $order->total_amount]);

        $cart->items()->delete();

        return $order->setRelation('items', $items);
    }

    /**
     * Lock the products in the cart, check every line can still be bought, and put the
     * locked product on its line so prices and stock are read from the current rows.
     *
     * The rows are locked in id order, so two orders for the same products cannot deadlock,
     * and the second one sees the stock the first one left.
     *
     * @param  Collection<int, CartItem>  $items
     *
     * @throws CartException
     */
    private function lockAndCheckProducts(Collection $items): void
    {
        $products = Product::query()
            ->with('category')
            ->whereKey($items->pluck('product_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get($item->product_id) ?? throw CartException::productUnavailable();

            if (! $product->isVisibleToCustomers()) {
                throw CartException::productNoLongerAvailable($product);
            }

            if ($item->quantity > $product->stock) {
                throw CartException::insufficientStock($product);
            }

            $item->setRelation('product', $product);
        }
    }

    /**
     * Save the order row.
     *
     * The order number is built from the id, which only exists after the insert,
     * so the row is first saved with a throwaway number.
     *
     * @param  array<string, string>  $shippingAddress
     */
    private function createOrder(User $user, string $total, array $shippingAddress, PaymentMethod $paymentMethod): Order
    {
        $order = $user->orders()->create([
            'order_number' => Str::random(20),
            'total_amount' => $total,
            'payment_method' => $paymentMethod,
            'shipping_address' => $shippingAddress,
        ]);

        $order->update(['order_number' => Order::numberFor($order->id)]);

        return $order;
    }
}
