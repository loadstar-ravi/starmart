<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_and_takes_no_payment_when_no_token_is_provided(): void
    {
        $order = $this->unpaidOnlineOrder();

        $response = $this->postJson('/api/payment/process', ['order_id' => $order->id, 'amount' => 500]);

        $response->assertUnauthorized();
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_returns_403_and_takes_no_payment_for_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->unpaidOnlineOrder(['user_id' => $admin->id]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/payment/process', ['order_id' => $order->id, 'amount' => 500]);

        $response->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_takes_the_payment_and_returns_200_with_it(): void
    {
        $this->travelTo('2026-10-09 10:30:00');
        $order = $this->unpaidOnlineOrder();
        Sanctum::actingAs($order->user);

        $response = $this->postJson('/api/payment/process', ['order_id' => $order->id, 'amount' => 500]);

        $payment = Payment::query()->sole();
        $response->assertOk();
        $response->assertExactJson([
            'message' => 'Payment successful.',
            'data' => [
                'id' => $payment->id,
                'order_id' => $order->id,
                'order_number' => 'ORD-10001',
                'amount' => '500.00',
                'status' => 'success',
                'transaction_reference' => $payment->transaction_reference,
                'failure_reason' => null,
                'paid_at' => '2026-10-09T10:30:00+00:00',
            ],
        ]);
        $this->assertNotNull($payment->transaction_reference);
        $this->assertSame(PaymentStatus::Success, $order->refresh()->payment_status);
    }

    public function test_returns_402_with_the_failed_payment_when_a_decline_is_simulated(): void
    {
        $order = $this->unpaidOnlineOrder();
        Sanctum::actingAs($order->user);

        $response = $this->postJson('/api/payment/process', [
            'order_id' => $order->id,
            'amount' => 500,
            'simulate' => 'FAILED',
        ]);

        $response->assertPaymentRequired();
        $response->assertJsonPath('message', 'The payment was declined by the bank.');
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.failure_reason', 'The payment was declined by the bank.');
        $response->assertJsonPath('data.transaction_reference', null);
        $response->assertJsonPath('data.paid_at', null);
        $this->assertSame(PaymentStatus::Failed, $order->refresh()->payment_status);
    }

    public function test_returns_404_and_takes_no_payment_for_the_order_of_another_customer(): void
    {
        $order = $this->unpaidOnlineOrder();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/payment/process', ['order_id' => $order->id, 'amount' => 500]);

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'The requested resource was not found.']);
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_returns_422_with_required_errors_for_an_empty_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/payment/process', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'order_id' => 'The order field is required.',
            'amount' => 'The amount field is required.',
        ]);
    }

    public function test_returns_422_for_an_unknown_test_outcome(): void
    {
        $order = $this->unpaidOnlineOrder();
        Sanctum::actingAs($order->user);

        $response = $this->postJson('/api/payment/process', [
            'order_id' => $order->id,
            'amount' => 500,
            'simulate' => 'pending',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['simulate' => 'The selected test outcome is invalid.']);
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_returns_422_and_takes_no_payment_when_the_amount_is_not_the_order_total(): void
    {
        $order = $this->unpaidOnlineOrder();
        Sanctum::actingAs($order->user);

        $response = $this->postJson('/api/payment/process', ['order_id' => $order->id, 'amount' => 1]);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => 'The amount does not match the order total of ₹500.00.']);
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_returns_422_for_a_cash_on_delivery_order(): void
    {
        $order = $this->unpaidOnlineOrder(['payment_method' => PaymentMethod::Cod]);
        Sanctum::actingAs($order->user);

        $response = $this->postJson('/api/payment/process', ['order_id' => $order->id, 'amount' => 500]);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => 'Order ORD-10001 is paid in cash on delivery.']);
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_returns_422_and_adds_no_payment_when_the_order_is_already_paid(): void
    {
        $order = $this->unpaidOnlineOrder();
        Sanctum::actingAs($order->user);
        $this->postJson('/api/payment/process', ['order_id' => $order->id, 'amount' => 500])->assertOk();

        $response = $this->postJson('/api/payment/process', ['order_id' => $order->id, 'amount' => 500]);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => 'Order ORD-10001 is already paid.']);
        $this->assertDatabaseCount('payments', 1);
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
