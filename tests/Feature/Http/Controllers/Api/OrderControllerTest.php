<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
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

    public function test_returns_401_for_the_order_list_and_an_order_when_no_token_is_provided(): void
    {
        $order = Order::factory()->create();

        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson("/api/orders/{$order->id}")->assertUnauthorized();
    }

    public function test_returns_403_for_the_order_list_and_an_order_for_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for($admin)->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/orders')->assertForbidden();
        $this->getJson("/api/orders/{$order->id}")->assertForbidden();
    }

    public function test_lists_only_the_customers_own_orders_newest_first_ten_per_page(): void
    {
        $customer = User::factory()->create();
        $older = Order::factory()->for($customer)->create(['created_at' => now()->subDays(2)]);
        $newer = Order::factory()->for($customer)->create(['created_at' => now()->subDay()]);
        OrderItem::factory()->for($newer)->create(['product_name' => 'Gaming Laptop']);
        Order::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/orders');

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], $response->json('data.*.id'));
        $response->assertJsonPath('data.0.items.0.product_name', 'Gaming Laptop');
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('meta.per_page', 10);
    }

    public function test_per_page_sets_the_page_size_of_the_order_list(): void
    {
        $customer = User::factory()->create();
        Order::factory()->for($customer)->count(3)->create();
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/orders?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.last_page', 2);
        $this->assertStringContainsString('per_page=2', $response->json('links.next'));
    }

    public function test_returns_422_for_a_page_size_above_the_limit(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/orders?per_page=51');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['per_page' => 'The per page field must not be greater than 50.']);
    }

    public function test_shows_one_of_the_customers_orders_with_its_lines(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-10001',
            'total_amount' => 500,
            'shipping_address' => self::ADDRESS,
        ]);
        $line = OrderItem::factory()->for($order)->create([
            'product_name' => 'Gaming Laptop',
            'unit_price' => 250,
            'quantity' => 2,
            'line_total' => 500,
        ]);
        Sanctum::actingAs($order->user);

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertOk();
        $response->assertExactJson(['data' => [
            'id' => $order->id,
            'order_number' => 'ORD-10001',
            'status' => 'placed',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'total_amount' => '500.00',
            'shipping_address' => self::ADDRESS,
            'items' => [[
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => 'Gaming Laptop',
                'unit_price' => '250.00',
                'quantity' => 2,
                'line_total' => '500.00',
            ]],
            'created_at' => $order->created_at->toIso8601String(),
        ]]);
    }

    public function test_returns_404_for_the_order_of_another_customer(): void
    {
        $order = Order::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'The requested resource was not found.']);
    }

    public function test_returns_404_for_an_unknown_order(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/orders/999999');

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'The requested resource was not found.']);
    }

    public function test_returns_404_for_an_order_id_that_is_not_a_number(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/orders/ORD-10001')->assertNotFound();
    }

    private function putInCart(User $user, Product $product, int $quantity): void
    {
        CartItem::factory()->for(Cart::factory()->for($user))->for($product)->create(['quantity' => $quantity]);
    }
}
