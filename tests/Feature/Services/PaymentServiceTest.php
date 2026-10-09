<?php

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_payment_marks_the_attempt_made_with_the_order_and_the_order_as_paid(): void
    {
        $this->travelTo('2026-10-09 10:30:00');
        $order = $this->unpaidOnlineOrder();

        $payment = (new PaymentService)->process($order->user, $order->id, '500.00');

        $saved = Payment::query()->sole();
        $this->assertTrue($saved->is($payment));
        $this->assertSame(PaymentStatus::Success, $saved->status);
        $this->assertMatchesRegularExpression('/^TXN-[A-Z0-9]{16}$/', $saved->transaction_reference);
        $this->assertSame('2026-10-09 10:30:00', $saved->paid_at->toDateTimeString());
        $this->assertNull($saved->failure_reason);
        $this->assertSame(PaymentStatus::Success, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::Placed, $order->status);
    }

    public function test_a_declined_payment_records_the_reason_and_marks_the_order_as_failed(): void
    {
        $order = $this->unpaidOnlineOrder();

        (new PaymentService)->process($order->user, $order->id, '500.00', simulateFailure: true);

        $saved = Payment::query()->sole();
        $this->assertSame(PaymentStatus::Failed, $saved->status);
        $this->assertSame('The payment was declined by the bank.', $saved->failure_reason);
        $this->assertNull($saved->transaction_reference);
        $this->assertNull($saved->paid_at);
        $this->assertSame(PaymentStatus::Failed, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::Placed, $order->status);
    }

    public function test_paying_again_after_a_decline_keeps_the_failed_attempt_and_adds_a_paid_one(): void
    {
        $order = $this->unpaidOnlineOrder();
        (new PaymentService)->process($order->user, $order->id, '500.00', simulateFailure: true);

        $retry = (new PaymentService)->process($order->user, $order->id, '500.00');

        $statuses = $order->payments()->orderBy('id')->pluck('status')->all();
        $this->assertSame([PaymentStatus::Failed, PaymentStatus::Success], $statuses);
        $this->assertSame('500.00', $retry->amount);
        $this->assertSame(PaymentStatus::Success, $order->refresh()->payment_status);
    }

    public function test_accepts_the_order_total_written_without_decimals(): void
    {
        $order = $this->unpaidOnlineOrder();

        (new PaymentService)->process($order->user, $order->id, 500);

        $this->assertSame(PaymentStatus::Success, $order->refresh()->payment_status);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function ordersThatCannotBePaid(): array
    {
        return [
            'cash on delivery order' => [
                ['payment_method' => PaymentMethod::Cod],
                'Order ORD-10001 is paid in cash on delivery.',
            ],
            'order that is already paid' => [
                ['payment_status' => PaymentStatus::Success],
                'Order ORD-10001 is already paid.',
            ],
            'order that was refunded' => [
                ['payment_status' => PaymentStatus::Refunded],
                'Order ORD-10001 was refunded and cannot be paid again.',
            ],
            'cancelled order' => [
                ['status' => OrderStatus::Cancelled],
                'Order ORD-10001 is cancelled and cannot be paid.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('ordersThatCannotBePaid')]
    public function test_refuses_an_order_that_cannot_be_paid_online(array $attributes, string $expected): void
    {
        $order = $this->unpaidOnlineOrder($attributes);

        $message = $this->refusalOf(fn () => (new PaymentService)->process($order->user, $order->id, '500.00'));

        $this->assertSame($expected, $message);
        $this->assertSame(PaymentStatus::Pending, Payment::query()->sole()->status);
        $this->assertSame($attributes['payment_status'] ?? PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_refuses_an_amount_that_is_not_the_order_total(): void
    {
        $order = $this->unpaidOnlineOrder();

        $message = $this->refusalOf(fn () => (new PaymentService)->process($order->user, $order->id, '499.99'));

        $this->assertSame('The amount does not match the order total of ₹500.00.', $message);
        $this->assertSame(PaymentStatus::Pending, Payment::query()->sole()->status);
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_the_order_of_another_user_is_not_found_and_stays_unpaid(): void
    {
        $order = $this->unpaidOnlineOrder();
        $intruder = User::factory()->create();

        try {
            (new PaymentService)->process($intruder, $order->id, '500.00');
            $this->fail('An order of another user should not be found.');
        } catch (ModelNotFoundException) {
            $this->assertSame(PaymentStatus::Pending, Payment::query()->sole()->status);
            $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
        }
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

    /**
     * Run a payment that must be refused and return the message the customer would get.
     */
    private function refusalOf(Closure $pay): string
    {
        try {
            $pay();
        } catch (PaymentException $exception) {
            return $exception->getMessage();
        }

        $this->fail('The payment should have been refused.');
    }
}
