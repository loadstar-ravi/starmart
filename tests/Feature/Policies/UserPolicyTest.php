<?php

namespace Tests\Feature\Policies;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_may_block_a_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('block', $customer));
    }

    public function test_an_admin_may_not_block_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('block', $otherAdmin));
    }

    public function test_an_admin_may_not_block_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('block', $admin));
    }

    public function test_a_customer_may_not_block_another_customer(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();

        $this->assertFalse(Gate::forUser($customer)->allows('block', $otherCustomer));
    }
}
