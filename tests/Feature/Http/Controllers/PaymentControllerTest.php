<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_login_form_and_takes_no_payment(): void
    {
        $order = $this->unpaidOnlineOrder();

        $this->get('/orders/ORD-10001/payment')->assertRedirectToRoute('login');
        $this->post('/payments', ['order_id' => $order->id, 'amount' => '500.00'])->assertRedirectToRoute('login');

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_forbids_admins_and_takes_no_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->unpaidOnlineOrder(['user_id' => $admin->id]);

        $this->actingAs($admin)->get('/orders/ORD-10001/payment')->assertForbidden();
        $this->actingAs($admin)->post('/payments', ['order_id' => $order->id, 'amount' => '500.00'])->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_shows_the_amount_to_pay_and_the_two_test_outcomes(): void
    {
        $order = $this->unpaidOnlineOrder();

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001/payment');

        $response->assertOk();
        $response->assertSeeInOrder(['ORD-10001', 'Amount to pay', '500.00', 'Successful payment', 'Failed payment']);
        $response->assertSee('name="order_id" value="'.$order->id.'"', false);
        $response->assertSee('name="amount" value="500.00"', false);
        $response->assertDontSee('Your last payment attempt failed');
    }

    public function test_tells_the_customer_when_the_last_attempt_failed(): void
    {
        $order = $this->unpaidOnlineOrder(['payment_status' => PaymentStatus::Failed]);

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001/payment');

        $response->assertOk();
        $response->assertSee('Your last payment attempt failed');
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function ordersWithNothingLeftToPayOnline(): array
    {
        return [
            'cash on delivery order' => [['payment_method' => PaymentMethod::Cod]],
            'order that is already paid' => [['payment_status' => PaymentStatus::Success]],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('ordersWithNothingLeftToPayOnline')]
    public function test_shows_the_order_instead_when_nothing_is_left_to_pay_online(array $attributes): void
    {
        $order = $this->unpaidOnlineOrder($attributes);

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001/payment');

        $response->assertRedirectToRoute('orders.success', 'ORD-10001');
    }

    public function test_the_payment_page_of_another_customers_order_is_not_found(): void
    {
        $this->unpaidOnlineOrder();

        $response = $this->actingAs(User::factory()->create())->get('/orders/ORD-10001/payment');

        $response->assertNotFound();
    }

    public function test_takes_the_payment_and_shows_the_paid_order(): void
    {
        $order = $this->unpaidOnlineOrder();

        $response = $this->actingAs($order->user)->post('/payments', [
            'order_id' => $order->id,
            'amount' => '500.00',
            'simulate' => 'success',
        ]);

        $response->assertRedirectToRoute('orders.success', 'ORD-10001');
        $response->assertSessionHas('status', 'Payment received. Thank you!');
        $this->assertSame(PaymentStatus::Success, $order->refresh()->payment_status);
        $this->assertSame(PaymentStatus::Success, Payment::query()->sole()->status);
    }

    public function test_a_declined_payment_returns_to_the_payment_page_with_the_reason(): void
    {
        $order = $this->unpaidOnlineOrder();

        $response = $this->actingAs($order->user)->post('/payments', [
            'order_id' => $order->id,
            'amount' => '500.00',
            'simulate' => 'failed',
        ]);

        $response->assertRedirectToRoute('payments.create', 'ORD-10001');
        $response->assertSessionHas('error', 'The payment was declined by the bank. You can try again.');
        $this->assertSame(PaymentStatus::Failed, $order->refresh()->payment_status);
    }

    public function test_paying_for_another_customers_order_is_not_found_and_takes_no_payment(): void
    {
        $order = $this->unpaidOnlineOrder();

        $response = $this->actingAs(User::factory()->create())
            ->post('/payments', ['order_id' => $order->id, 'amount' => '500.00']);

        $response->assertNotFound();
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_refuses_an_amount_changed_in_the_form_and_takes_no_payment(): void
    {
        $order = $this->unpaidOnlineOrder();

        $response = $this->actingAs($order->user)->post('/payments', ['order_id' => $order->id, 'amount' => '1.00']);

        $response->assertRedirectToRoute('orders.success', 'ORD-10001');
        $response->assertSessionHas('error', 'The amount does not match the order total of ₹500.00.');
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidPayments(): array
    {
        return [
            'no order' => [['order_id' => null], 'order_id', 'The order field is required.'],
            'no amount' => [['amount' => null], 'amount', 'The amount field is required.'],
            'amount that is not a number' => [['amount' => 'five hundred'], 'amount', 'The amount field must be a number.'],
            'unknown test outcome' => [['simulate' => 'refunded'], 'simulate', 'The selected test outcome is invalid.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $invalid
     */
    #[DataProvider('invalidPayments')]
    public function test_rejects_an_invalid_payment_and_takes_none(array $invalid, string $field, string $message): void
    {
        $order = $this->unpaidOnlineOrder();

        $response = $this->actingAs($order->user)
            ->post('/payments', ['order_id' => $order->id, 'amount' => '500.00', ...$invalid]);

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    /**
     * Create an online order of 500.00 the way checkout leaves it: unpaid, with one pending payment.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function unpaidOnlineOrder(array $attributes = []): Order
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-10001',
            'total_amount' => 500,
            'payment_method' => PaymentMethod::Online,
            ...$attributes,
        ]);

        Payment::factory()->for($order)->create(['amount' => 500]);

        return $order;
    }
}
