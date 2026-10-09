<?php

namespace Tests\Feature\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['view'], 'view')]
    #[TestWith(['cancel'], 'cancel')]
    public function test_a_customer_may_act_on_their_own_order(string $ability): void
    {
        $order = Order::factory()->create();

        $response = Gate::forUser($order->user)->inspect($ability, $order);

        $this->assertTrue($response->allowed());
    }

    #[TestWith(['view'], 'view')]
    #[TestWith(['cancel'], 'cancel')]
    public function test_the_order_of_another_customer_is_denied_as_not_found(string $ability): void
    {
        $order = Order::factory()->create();

        $response = Gate::forUser(User::factory()->create())->inspect($ability, $order);

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }
}
