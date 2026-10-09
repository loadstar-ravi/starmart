<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\CartException;
use App\Jobs\SendOrderConfirmationJob;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
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
     * Find one of the user's own orders by its number, together with its lines.
     *
     * @throws ModelNotFoundException<Order>
     */
    public function findForUser(User $user, string $orderNumber): Order
    {
        return $user->orders()->with('items')->where('order_number', $orderNumber)->firstOrFail();
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
