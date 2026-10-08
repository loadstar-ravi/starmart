<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_login_and_register_links_to_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('login'));
        $response->assertSee(route('register'));
    }

    public function test_escapes_the_name_of_the_signed_in_customer(): void
    {
        $customer = User::factory()->create(['name' => "<script>alert('xss')</script>"]);

        $response = $this->actingAs($customer)->get('/');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }
}
