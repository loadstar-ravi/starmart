<?php

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CartException;
use App\Jobs\SendOrderConfirmationJob;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
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
