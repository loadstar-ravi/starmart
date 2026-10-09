<?php

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CartException;
use App\Exceptions\OrderException;
use App\Jobs\SendOrderConfirmationJob;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private const array ADDRESS = [
        'name' => 'Priya Sharma',
        'mobile' => '9876543210',
        'email' => 'priya@example.com',
        'address' => '12 MG Road',
        'city' => 'Jaipur',
        'state' => 'Rajasthan',
        'pincode' => '302001',
    ];

    public function test_placing_an_order_saves_it_with_the_total_worked_out_from_the_products(): void
    {
        $user = User::factory()->create();
        $this->putInCart($user, Product::factory()->create(['price' => 54990.50, 'stock' => 5]), 2);
        $this->putInCart($user, Product::factory()->create(['price' => 799, 'stock' => 5]), 1);

        $order = (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Online);

        $saved = Order::query()->sole();
        $this->assertTrue($saved->is($order));
        $this->assertSame($user->id, $saved->user_id);
        $this->assertSame(Order::numberFor($saved->id), $saved->order_number);
        $this->assertSame('110780.00', $saved->total_amount);
        $this->assertSame(OrderStatus::Placed, $saved->status);
        $this->assertSame(PaymentStatus::Pending, $saved->payment_status);
        $this->assertSame(PaymentMethod::Online, $saved->payment_method);
        $this->assertSame(self::ADDRESS, $saved->shipping_address);
    }

    public function test_placing_an_order_copies_each_line_with_the_products_name_and_price(): void
    {
        $user = User::factory()->create();
        $laptop = Product::factory()->create(['name' => 'Gaming Laptop', 'price' => 54990.50, 'stock' => 5]);
        $this->putInCart($user, $laptop, 2);

        $order = (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Cod);

        $line = $order->items()->sole();
        $this->assertSame($laptop->id, $line->product_id);
        $this->assertSame('Gaming Laptop', $line->product_name);
        $this->assertSame('54990.50', $line->unit_price);
        $this->assertSame(2, $line->quantity);
        $this->assertSame('109981.00', $line->line_total);
    }

    public function test_placing_an_order_takes_only_the_ordered_quantities_out_of_stock(): void
    {
        $user = User::factory()->create();
        $ordered = Product::factory()->create(['stock' => 5]);
        $notOrdered = Product::factory()->create(['stock' => 7]);
        $this->putInCart($user, $ordered, 2);

        (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Cod);

        $this->assertSame(3, $ordered->refresh()->stock);
        $this->assertSame(7, $notOrdered->refresh()->stock);
    }

    public function test_placing_an_order_may_take_the_last_unit_in_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);
        $this->putInCart($user, $product, 2);

        (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Cod);

        $this->assertSame(0, $product->refresh()->stock);
    }

    public function test_placing_an_order_records_a_pending_payment_for_the_total(): void
    {
        $user = User::factory()->create();
        $this->putInCart($user, Product::factory()->create(['price' => 250, 'stock' => 5]), 2);

        $order = (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Online);

        $payment = Payment::query()->sole();
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame('500.00', $payment->amount);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }

    public function test_placing_an_order_empties_the_users_cart_and_no_one_elses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->putInCart($user, $product, 1);
        $this->putInCart($other, $product, 1);

        (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Cod);

        $this->assertSame(0, $user->cartItems()->count());
        $this->assertSame(1, $other->cartItems()->count());
    }

    public function test_placing_an_order_queues_the_confirmation_for_that_order(): void
    {
        $user = User::factory()->create();
        $this->putInCart($user, Product::factory()->create(['stock' => 5]), 1);
        Queue::fake([SendOrderConfirmationJob::class]);

        $order = (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Cod);

        Queue::assertPushed(fn (SendOrderConfirmationJob $job) => $job->order->is($order));
    }

    public function test_refuses_a_user_who_has_no_cart(): void
    {
        $user = User::factory()->create();

        $message = $this->refusalOf(fn () => (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Cod));

        $this->assertSame('Your cart is empty.', $message);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_refuses_a_cart_without_lines(): void
    {
        $cart = Cart::factory()->create();

        $message = $this->refusalOf(fn () => (new OrderService)->placeOrder($cart->user, self::ADDRESS, PaymentMethod::Cod));

        $this->assertSame('Your cart is empty.', $message);
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @return array<string, array{0: Closure(): Product, 1: int, 2: string}>
     */
    public static function linesThatCannotBeBought(): array
    {
        return [
            'inactive product' => [
                fn (): Product => Product::factory()->inactive()->create(['name' => 'Gaming Laptop', 'stock' => 5]),
                1,
                '"Gaming Laptop" is no longer available.',
            ],
            'product in an inactive category' => [
                fn (): Product => Product::factory()
                    ->for(Category::factory()->inactive())
                    ->create(['name' => 'Gaming Laptop', 'stock' => 5]),
                1,
                '"Gaming Laptop" is no longer available.',
            ],
            'more than is in stock' => [
                fn (): Product => Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 2]),
                3,
                '"Gaming Laptop" has only 2 in stock.',
            ],
            'product that ran out of stock' => [
                fn (): Product => Product::factory()->outOfStock()->create(['name' => 'Gaming Laptop']),
                1,
                '"Gaming Laptop" is out of stock.',
            ],
        ];
    }

    /**
     * @param  Closure(): Product  $product
     */
    #[DataProvider('linesThatCannotBeBought')]
    public function test_refuses_a_cart_with_a_line_that_cannot_be_bought(Closure $product, int $quantity, string $expected): void
    {
        $user = User::factory()->create();
        $this->putInCart($user, $product(), $quantity);

        $message = $this->refusalOf(fn () => (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Cod));

        $this->assertSame($expected, $message);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_refused_order_changes_no_stock_keeps_the_cart_and_queues_nothing(): void
    {
        $user = User::factory()->create();
        $available = Product::factory()->create(['stock' => 5]);
        $this->putInCart($user, $available, 2);
        $this->putInCart($user, Product::factory()->create(['stock' => 1]), 3);
        Queue::fake();

        $this->refusalOf(fn () => (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Cod));

        $this->assertSame(5, $available->refresh()->stock);
        $this->assertSame(2, $user->cartItems()->count());
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
        Queue::assertNothingPushed();
    }

    public function test_a_failure_half_way_through_rolls_everything_back(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->putInCart($user, $product, 2);
        Queue::fake();
        Payment::creating(fn () => throw new RuntimeException('The payment could not be saved.'));

        try {
            (new OrderService)->placeOrder($user, self::ADDRESS, PaymentMethod::Cod);
            $this->fail('Placing the order should have failed while saving the payment.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_items', 0);
            $this->assertSame(5, $product->refresh()->stock);
            $this->assertSame(1, $user->cartItems()->count());
            Queue::assertNothingPushed();
        }
    }

    public function test_lists_only_the_users_own_orders_newest_first(): void
    {
        $user = User::factory()->create();
        $older = Order::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
        $newer = Order::factory()->for($user)->create(['created_at' => now()->subDay()]);
        Order::factory()->create();

        $orders = (new OrderService)->paginateForUser($user);

        $this->assertSame([$newer->id, $older->id], $orders->pluck('id')->all());
        $this->assertSame(10, $orders->perPage());
    }

    #[TestWith([OrderStatus::Placed], 'placed')]
    #[TestWith([OrderStatus::Confirmed], 'confirmed')]
    public function test_cancelling_marks_the_order_cancelled_and_puts_the_stock_of_each_line_back(OrderStatus $status): void
    {
        $laptop = Product::factory()->create(['stock' => 3]);
        $mouse = Product::factory()->outOfStock()->create();
        $untouched = Product::factory()->create(['stock' => 7]);
        $order = Order::factory()->status($status)->create();
        OrderItem::factory()->for($order)->for($laptop)->create(['quantity' => 2]);
        OrderItem::factory()->for($order)->for($mouse)->create(['quantity' => 1]);

        $cancelled = (new OrderService)->cancel($order);

        $this->assertTrue($cancelled->is($order));
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(5, $laptop->refresh()->stock);
        $this->assertSame(1, $mouse->refresh()->stock);
        $this->assertSame(7, $untouched->refresh()->stock);
    }

    public function test_cancelling_an_unpaid_order_closes_its_open_payment(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create();

        (new OrderService)->cancel($order);

        $this->assertSame(PaymentStatus::Failed, $order->refresh()->payment_status);
        $this->assertSame(PaymentStatus::Failed, $payment->refresh()->status);
        $this->assertSame('The order was cancelled before it was paid.', $payment->failure_reason);
    }

    public function test_cancelling_a_paid_order_refunds_its_payment(): void
    {
        $order = Order::factory()->paidOnline()->create();
        $declined = Payment::factory()->for($order)->failed()->create();
        $paid = Payment::factory()->for($order)->successful()->create();

        (new OrderService)->cancel($order);

        $this->assertSame(PaymentStatus::Refunded, $order->refresh()->payment_status);
        $this->assertSame(PaymentStatus::Refunded, $paid->refresh()->status);
        $this->assertNotNull($paid->transaction_reference);
        $this->assertSame(PaymentStatus::Failed, $declined->refresh()->status);
    }

    public function test_cancelling_skips_a_line_whose_product_was_deleted(): void
    {
        $order = Order::factory()->create();
        $deleted = Product::factory()->create();
        OrderItem::factory()->for($order)->for($deleted)->create(['quantity' => 2]);
        $deleted->delete();

        (new OrderService)->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    /**
     * @return array<string, array{0: OrderStatus, 1: string}>
     */
    public static function statusesThatCannotBeCancelled(): array
    {
        return [
            'processing' => [OrderStatus::Processing, 'Order ORD-10001 is processing and can no longer be cancelled.'],
            'shipped' => [OrderStatus::Shipped, 'Order ORD-10001 is shipped and can no longer be cancelled.'],
            'delivered' => [OrderStatus::Delivered, 'Order ORD-10001 is delivered and can no longer be cancelled.'],
            'already cancelled' => [OrderStatus::Cancelled, 'Order ORD-10001 is already cancelled.'],
        ];
    }

    #[DataProvider('statusesThatCannotBeCancelled')]
    public function test_refuses_to_cancel_an_order_that_is_past_that_point_and_leaves_it_as_it_is(OrderStatus $status, string $expected): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->status($status)->paidOnline()->create(['order_number' => 'ORD-10001']);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);

        try {
            (new OrderService)->cancel($order);
            $this->fail('The cancellation should have been refused.');
        } catch (OrderException $exception) {
            $this->assertSame($expected, $exception->getMessage());
            $this->assertSame($status, $order->refresh()->status);
            $this->assertSame(PaymentStatus::Success, $order->payment_status);
            $this->assertSame(3, $product->refresh()->stock);
        }
    }

    public function test_a_failure_while_cancelling_rolls_the_stock_back(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);
        Order::updating(fn () => throw new RuntimeException('The order could not be saved.'));

        try {
            (new OrderService)->cancel($order);
            $this->fail('Cancelling should have failed while saving the order.');
        } catch (RuntimeException) {
            $this->assertSame(3, $product->refresh()->stock);
            $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
        }
    }

    #[TestWith([OrderStatus::Placed, OrderStatus::Confirmed], 'to the next step')]
    #[TestWith([OrderStatus::Placed, OrderStatus::Shipped], 'past several steps')]
    #[TestWith([OrderStatus::Shipped, OrderStatus::Delivered], 'to the last step')]
    public function test_updating_the_status_moves_the_order_forward(OrderStatus $from, OrderStatus $to): void
    {
        $order = Order::factory()->status($from)->paidOnline()->create();

        $updated = (new OrderService)->updateStatus($order, $to);

        $this->assertTrue($updated->is($order));
        $this->assertSame($to, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Success, $order->payment_status);
    }

    public function test_delivering_a_cash_on_delivery_order_records_its_payment_as_paid(): void
    {
        $this->travelTo('2026-10-09 10:30:00');
        $order = Order::factory()->status(OrderStatus::Shipped)->create(['payment_method' => PaymentMethod::Cod]);
        $payment = Payment::factory()->for($order)->create();

        (new OrderService)->updateStatus($order, OrderStatus::Delivered);

        $this->assertSame(PaymentStatus::Success, $order->refresh()->payment_status);
        $this->assertSame(PaymentStatus::Success, $payment->refresh()->status);
        $this->assertSame('2026-10-09 10:30:00', $payment->paid_at->toDateTimeString());
    }

    public function test_shipping_a_cash_on_delivery_order_leaves_its_payment_pending(): void
    {
        $order = Order::factory()->create(['payment_method' => PaymentMethod::Cod]);
        $payment = Payment::factory()->for($order)->create();

        (new OrderService)->updateStatus($order, OrderStatus::Shipped);

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_delivering_an_online_order_that_is_not_paid_leaves_its_payment_pending(): void
    {
        $order = Order::factory()->create(['payment_method' => PaymentMethod::Online]);
        $payment = Payment::factory()->for($order)->create();

        (new OrderService)->updateStatus($order, OrderStatus::Delivered);

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_updating_the_status_to_cancelled_cancels_the_order_and_puts_its_stock_back(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->status(OrderStatus::Confirmed)->paidOnline()->create();
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);

        (new OrderService)->updateStatus($order, OrderStatus::Cancelled);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    /**
     * @return array<string, array{0: OrderStatus, 1: OrderStatus, 2: string}>
     */
    public static function statusChangesThatAreRefused(): array
    {
        return [
            'to the status it already has' => [
                OrderStatus::Shipped,
                OrderStatus::Shipped,
                'Order ORD-10001 is already shipped.',
            ],
            'back to an earlier step' => [
                OrderStatus::Shipped,
                OrderStatus::Confirmed,
                'Order ORD-10001 is shipped and cannot go back to confirmed.',
            ],
            'after it was delivered' => [
                OrderStatus::Delivered,
                OrderStatus::Shipped,
                'Order ORD-10001 is delivered and its status can no longer be changed.',
            ],
            'after it was cancelled' => [
                OrderStatus::Cancelled,
                OrderStatus::Confirmed,
                'Order ORD-10001 is cancelled and its status can no longer be changed.',
            ],
            'to cancelled once it is shipped' => [
                OrderStatus::Shipped,
                OrderStatus::Cancelled,
                'Order ORD-10001 is shipped and can no longer be cancelled.',
            ],
        ];
    }

    #[DataProvider('statusChangesThatAreRefused')]
    public function test_refuses_a_status_change_the_order_does_not_allow_and_leaves_it_as_it_is(OrderStatus $from, OrderStatus $to, string $expected): void
    {
        $order = Order::factory()->status($from)->create(['order_number' => 'ORD-10001']);

        try {
            (new OrderService)->updateStatus($order, $to);
            $this->fail('The status change should have been refused.');
        } catch (OrderException $exception) {
            $this->assertSame($expected, $exception->getMessage());
            $this->assertSame($from, $order->refresh()->status);
            $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        }
    }

    private function putInCart(User $user, Product $product, int $quantity): void
    {
        $cart = $user->cart()->first() ?? Cart::factory()->for($user)->create();

        CartItem::factory()->for($cart)->for($product)->create(['quantity' => $quantity]);
    }

    /**
     * Run an order that must be refused and return the message the customer would get.
     */
    private function refusalOf(Closure $placeOrder): string
    {
        try {
            $placeOrder();
        } catch (CartException $exception) {
            return $exception->getMessage();
        }

        $this->fail('The order should have been refused.');
    }
}
