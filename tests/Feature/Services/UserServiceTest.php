<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocking_records_when_the_user_was_blocked(): void
    {
        $this->travelTo('2026-10-09 10:30:00');
        $customer = User::factory()->create();

        (new UserService)->block($customer, User::factory()->admin()->create());

        $this->assertSame('2026-10-09 10:30:00', $customer->refresh()->blocked_at->toDateTimeString());
    }

    public function test_blocking_revokes_the_api_tokens_of_that_user_only(): void
    {
        $customer = User::factory()->create();
        $customer->createToken('phone');
        $customer->createToken('tablet');
        $other = User::factory()->create();
        $other->createToken('phone');

        (new UserService)->block($customer, User::factory()->admin()->create());

        $this->assertSame(0, $customer->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->assertFalse($other->refresh()->isBlocked());
    }

    public function test_blocking_a_user_who_is_already_blocked_keeps_the_first_time(): void
    {
        $customer = User::factory()->create(['blocked_at' => '2026-10-01 08:00:00']);
        $this->travelTo('2026-10-09 10:30:00');

        (new UserService)->block($customer, User::factory()->admin()->create());

        $this->assertSame('2026-10-01 08:00:00', $customer->refresh()->blocked_at->toDateTimeString());
    }

    public function test_unblocking_lets_the_user_sign_in_again(): void
    {
        $customer = User::factory()->blocked()->create();

        (new UserService)->unblock($customer, User::factory()->admin()->create());

        $this->assertNull($customer->refresh()->blocked_at);
        $this->assertFalse($customer->isBlocked());
    }
}
