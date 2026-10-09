<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private const array CHECKOUT = [
        'name' => 'Priya Sharma',
        'mobile' => '9876543210',
        'email' => 'priya@example.com',
        'address' => '12 MG Road',
        'city' => 'Jaipur',
        'state' => 'Rajasthan',
        'pincode' => '302001',
        'payment_method' => 'cod',
    ];

    public function test_redirects_guests_to_the_login_form_and_places_no_order(): void
    {
        $this->get('/checkout')->assertRedirectToRoute('login');
        $this->post('/checkout', self::CHECKOUT)->assertRedirectToRoute('login');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_forbids_admins_and_places_no_order(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/checkout')->assertForbidden();
        $this->actingAs($admin)->post('/checkout', self::CHECKOUT)->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_shows_the_form_with_the_customers_name_and_email_and_the_cart_summary(): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);
        $this->putInCart($customer, Product::factory()->create(['name' => 'Gaming Laptop', 'price' => 250, 'stock' => 5]), 2);

        $response = $this->actingAs($customer)->get('/checkout');

        $response->assertOk();
        $response->assertSee('value="Priya Sharma"', false);
        $response->assertSee('value="priya@example.com"', false);
        $response->assertSeeInOrder(['Cash on delivery', 'Online payment']);
        $response->assertSeeInOrder(['Order summary', 'Gaming Laptop', '500.00', 'Total', '500.00', 'Place order']);
    }

    public function test_sends_a_customer_with_an_empty_cart_back_to_the_cart(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/checkout');

        $response->assertRedirectToRoute('cart.show');
    }

    public function test_sends_a_customer_back_to_a_cart_holding_a_line_that_cannot_be_bought(): void
    {
        $customer = User::factory()->create();
        $this->putInCart($customer, Product::factory()->outOfStock()->create(), 1);

        $response = $this->actingAs($customer)->get('/checkout');

        $response->assertRedirectToRoute('cart.show');
        $response->assertSessionHas('error', 'Update or remove the items that cannot be bought before you check out.');
    }

    public function test_places_the_order_and_shows_its_confirmation_page(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 250, 'stock' => 5]);
        $this->putInCart($customer, $product, 2);

        $response = $this->actingAs($customer)->post('/checkout', self::CHECKOUT);

        $order = Order::query()->sole();
        $response->assertRedirectToRoute('orders.success', $order->order_number);
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('500.00', $order->total_amount);
        $this->assertSame(PaymentMethod::Cod, $order->payment_method);
        $this->assertSame('Priya Sharma', $order->shipping_address['name']);
        $this->assertSame('302001', $order->shipping_address['pincode']);
        $this->assertSame(3, $product->refresh()->stock);
        $this->assertSame(0, $customer->cartItems()->count());
    }

    public function test_an_order_paid_online_goes_to_its_payment_page(): void
    {
        $customer = User::factory()->create();
        $this->putInCart($customer, Product::factory()->create(['stock' => 5]), 1);

        $response = $this->actingAs($customer)->post('/checkout', [...self::CHECKOUT, 'payment_method' => 'online']);

        $response->assertRedirectToRoute('payments.create', Order::query()->sole()->order_number);
    }

    public function test_accepts_the_payment_method_in_upper_case(): void
    {
        $customer = User::factory()->create();
        $this->putInCart($customer, Product::factory()->create(['stock' => 5]), 1);

        $this->actingAs($customer)->post('/checkout', [...self::CHECKOUT, 'payment_method' => 'ONLINE']);

        $this->assertSame(PaymentMethod::Online, Order::query()->sole()->payment_method);
    }

    public function test_ignores_prices_statuses_and_owners_sent_with_the_form(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $this->putInCart($customer, Product::factory()->create(['price' => 250, 'stock' => 5]), 2);

        $this->actingAs($customer)->post('/checkout', [
            ...self::CHECKOUT,
            'total_amount' => 1,
            'unit_price' => 1,
            'price' => 1,
            'status' => 'delivered',
            'payment_status' => 'success',
            'user_id' => $other->id,
        ]);

        $order = Order::query()->sole();
        $this->assertSame('500.00', $order->total_amount);
        $this->assertSame('250.00', $order->items()->sole()->unit_price);
        $this->assertSame(OrderStatus::Placed, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame($customer->id, $order->user_id);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidCheckouts(): array
    {
        return [
            'no name' => [['name' => null], 'name', 'The name field is required.'],
            'no mobile' => [['mobile' => null], 'mobile', 'The mobile field is required.'],
            'mobile with nine digits' => [
                ['mobile' => '987654321'],
                'mobile',
                'The mobile number must be 10 digits and start with 6, 7, 8 or 9.',
            ],
            'mobile starting with five' => [
                ['mobile' => '5876543210'],
                'mobile',
                'The mobile number must be 10 digits and start with 6, 7, 8 or 9.',
            ],
            'no email' => [['email' => null], 'email', 'The email field is required.'],
            'malformed email' => [['email' => 'priya-at-example'], 'email', 'The email field must be a valid email address.'],
            'no address' => [['address' => null], 'address', 'The address field is required.'],
            'no city' => [['city' => null], 'city', 'The city field is required.'],
            'no state' => [['state' => null], 'state', 'The state field is required.'],
            'no pincode' => [['pincode' => null], 'pincode', 'The pincode field is required.'],
            'pincode with five digits' => [['pincode' => '30200'], 'pincode', 'The pincode must be 6 digits.'],
            'no payment method' => [['payment_method' => null], 'payment_method', 'The payment method field is required.'],
            'unknown payment method' => [['payment_method' => 'upi'], 'payment_method', 'The selected payment method is invalid.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $invalid
     */
    #[DataProvider('invalidCheckouts')]
    public function test_rejects_an_invalid_checkout_and_keeps_the_cart(array $invalid, string $field, string $message): void
    {
        $customer = User::factory()->create();
        $this->putInCart($customer, Product::factory()->create(['stock' => 5]), 1);

        $response = $this->actingAs($customer)->post('/checkout', [...self::CHECKOUT, ...$invalid]);

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $customer->cartItems()->count());
    }

    public function test_sends_the_customer_back_to_the_cart_when_stock_ran_short(): void
    {
        $customer = User::factory()->create();
        $this->putInCart($customer, Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 1]), 3);

        $response = $this->actingAs($customer)->post('/checkout', self::CHECKOUT);

        $response->assertRedirectToRoute('cart.show');
        $response->assertSessionHas('error', '"Gaming Laptop" has only 1 in stock.');
        $this->assertDatabaseCount('orders', 0);
    }

    private function putInCart(User $user, Product $product, int $quantity): void
    {
        CartItem::factory()->for(Cart::factory()->for($user))->for($product)->create(['quantity' => $quantity]);
    }
}
