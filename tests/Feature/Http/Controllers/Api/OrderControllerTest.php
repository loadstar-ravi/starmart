<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
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

    public function test_returns_401_and_places_no_order_when_no_token_is_provided(): void
    {
        $response = $this->postJson('/api/orders', [...self::ADDRESS, 'payment_method' => 'cod']);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_returns_403_and_places_no_order_for_admins(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/orders', [...self::ADDRESS, 'payment_method' => 'cod']);

        $response->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_places_the_order_and_returns_201_with_its_confirmation(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'price' => 250, 'stock' => 5]);
        $this->putInCart($customer, $product, 2);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/orders', [...self::ADDRESS, 'payment_method' => 'online']);

        $order = Order::query()->sole();
        $line = $order->items()->sole();
        $response->assertCreated();
        $response->assertJsonPath('message', "Order {$order->order_number} placed.");
        $response->assertJsonPath('data', [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => 'placed',
            'payment_status' => 'pending',
            'payment_method' => 'online',
            'total_amount' => '500.00',
            'shipping_address' => self::ADDRESS,
            'items' => [[
                'id' => $line->id,
                'product_id' => $product->id,
                'product_name' => 'Gaming Laptop',
                'unit_price' => '250.00',
                'quantity' => 2,
                'line_total' => '500.00',
            ]],
            'created_at' => $order->created_at->toIso8601String(),
        ]);
    }

    public function test_placing_an_order_takes_the_stock_records_the_payment_and_empties_the_cart(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 250, 'stock' => 5]);
        $this->putInCart($customer, $product, 2);
        Sanctum::actingAs($customer);

        $this->postJson('/api/orders', [...self::ADDRESS, 'payment_method' => 'cod'])->assertCreated();

        $this->assertSame(3, $product->refresh()->stock);
        $this->assertSame(0, $customer->cartItems()->count());
        $this->assertDatabaseHas('payments', [
            'order_id' => Order::query()->sole()->id,
            'amount' => 500,
            'status' => 'pending',
        ]);
    }

    public function test_accepts_a_mobile_number_and_pincode_sent_as_numbers(): void
    {
        $customer = User::factory()->create();
        $this->putInCart($customer, Product::factory()->create(['stock' => 5]), 1);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/orders', [
            ...self::ADDRESS,
            'mobile' => 9876543210,
            'pincode' => 302001,
            'payment_method' => 'COD',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.shipping_address.mobile', '9876543210');
        $response->assertJsonPath('data.shipping_address.pincode', '302001');
        $response->assertJsonPath('data.payment_method', 'cod');
    }

    public function test_ignores_prices_statuses_and_owners_sent_in_the_payload(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $this->putInCart($customer, Product::factory()->create(['price' => 250, 'stock' => 5]), 2);
        Sanctum::actingAs($customer);

        $this->postJson('/api/orders', [
            ...self::ADDRESS,
            'payment_method' => 'cod',
            'total_amount' => 1,
            'items' => [['unit_price' => 1, 'line_total' => 1, 'quantity' => 1]],
            'status' => 'delivered',
            'payment_status' => 'success',
            'user_id' => $other->id,
        ])->assertCreated();

        $order = Order::query()->sole();
        $this->assertSame('500.00', $order->total_amount);
        $this->assertSame('250.00', $order->items()->sole()->unit_price);
        $this->assertSame(OrderStatus::Placed, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame($customer->id, $order->user_id);
    }

    public function test_returns_422_with_required_errors_for_an_empty_payload(): void
    {
        $customer = User::factory()->create();
        $this->putInCart($customer, Product::factory()->create(['stock' => 5]), 1);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/orders', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'name' => 'The name field is required.',
            'mobile' => 'The mobile field is required.',
            'email' => 'The email field is required.',
            'address' => 'The address field is required.',
            'city' => 'The city field is required.',
            'state' => 'The state field is required.',
            'pincode' => 'The pincode field is required.',
            'payment_method' => 'The payment method field is required.',
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_returns_422_for_a_malformed_mobile_number_and_pincode(): void
    {
        $customer = User::factory()->create();
        $this->putInCart($customer, Product::factory()->create(['stock' => 5]), 1);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/orders', [
            ...self::ADDRESS,
            'mobile' => '12345',
            'pincode' => 'ABCDEF',
            'payment_method' => 'cod',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'mobile' => 'The mobile number must be 10 digits and start with 6, 7, 8 or 9.',
            'pincode' => 'The pincode must be 6 digits.',
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_returns_422_when_the_cart_is_empty(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/orders', [...self::ADDRESS, 'payment_method' => 'cod']);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => 'Your cart is empty.']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_returns_422_and_keeps_the_stock_when_there_is_not_enough_of_a_product(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 1]);
        $this->putInCart($customer, $product, 3);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/orders', [...self::ADDRESS, 'payment_method' => 'cod']);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => '"Gaming Laptop" has only 1 in stock.']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->refresh()->stock);
    }

    private function putInCart(User $user, Product $product, int $quantity): void
    {
        CartItem::factory()->for(Cart::factory()->for($user))->for($product)->create(['quantity' => $quantity]);
    }
}
