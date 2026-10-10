<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_login_form_and_changes_nothing(): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sharma']);

        $this->get('/profile')->assertRedirectToRoute('login');
        $this->put('/profile', ['name' => 'Someone Else', 'email' => 'else@example.com'])->assertRedirectToRoute('login');

        $this->assertSame('Priya Sharma', $customer->refresh()->name);
    }

    public function test_forbids_admins(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Asha Verma']);

        $this->actingAs($admin)->get('/profile')->assertForbidden();
        $this->actingAs($admin)->put('/profile', ['name' => 'Someone Else', 'email' => 'else@example.com'])->assertForbidden();

        $this->assertSame('Asha Verma', $admin->refresh()->name);
    }

    public function test_shows_the_customer_their_name_and_email_and_the_password_form(): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);

        $response = $this->actingAs($customer)->get('/profile');

        $response->assertOk();
        $response->assertSeeInOrder([
            'Your details', 'value="Priya Sharma"', 'value="priya@example.com"', 'Save details',
            'Change password', 'name="current_password"', 'name="password"', 'name="password_confirmation"',
        ], false);
    }

    public function test_updates_the_name_and_email_of_the_customer_and_of_no_one_else(): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);
        $other = User::factory()->create(['name' => 'Arjun Mehta', 'email' => 'arjun@example.com']);

        $response = $this->actingAs($customer)->put('/profile', ['name' => 'Priya Nair', 'email' => 'priya.nair@example.com']);

        $response->assertRedirectToRoute('profile.edit');
        $response->assertSessionHas('status', 'Your details have been updated.');
        $customer->refresh();
        $this->assertSame('Priya Nair', $customer->name);
        $this->assertSame('priya.nair@example.com', $customer->email);
        $this->assertSame('Arjun Mehta', $other->refresh()->name);
        $this->assertSame('arjun@example.com', $other->email);
    }

    public function test_lets_the_customer_keep_their_own_email(): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);

        $response = $this->actingAs($customer)->put('/profile', ['name' => 'Priya Nair', 'email' => 'priya@example.com']);

        $response->assertSessionHasNoErrors();
        $this->assertSame('Priya Nair', $customer->refresh()->name);
    }

    public function test_rejects_an_email_that_belongs_to_another_user(): void
    {
        User::factory()->create(['email' => 'arjun@example.com']);
        $customer = User::factory()->create(['email' => 'priya@example.com']);

        $response = $this->actingAs($customer)->put('/profile', ['name' => 'Priya Sharma', 'email' => 'arjun@example.com']);

        $response->assertSessionHasErrors(['email' => 'The email has already been taken.']);
        $this->assertSame('priya@example.com', $customer->refresh()->email);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidDetails(): array
    {
        return [
            'no name' => [['name' => null], 'name', 'The name field is required.'],
            'name above 255 characters' => [
                ['name' => str_repeat('a', 256)],
                'name',
                'The name field must not be greater than 255 characters.',
            ],
            'no email' => [['email' => null], 'email', 'The email field is required.'],
            'malformed email' => [['email' => 'priya-at-example'], 'email', 'The email field must be a valid email address.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $invalid
     */
    #[DataProvider('invalidDetails')]
    public function test_rejects_invalid_details_and_keeps_the_old_ones(array $invalid, string $field, string $message): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);

        $response = $this->actingAs($customer)
            ->put('/profile', ['name' => 'Priya Nair', 'email' => 'priya.nair@example.com', ...$invalid]);

        $response->assertSessionHasErrors([$field => $message]);
        $customer->refresh();
        $this->assertSame('Priya Sharma', $customer->name);
        $this->assertSame('priya@example.com', $customer->email);
    }

    public function test_ignores_a_role_a_block_and_a_password_sent_with_the_details(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->put('/profile', [
            'name' => 'Priya Nair',
            'email' => 'priya.nair@example.com',
            'role' => 'admin',
            'blocked_at' => '2026-10-09 10:30:00',
            'password' => 'another-password',
        ]);

        $customer->refresh();
        $this->assertSame('Priya Nair', $customer->name);
        $this->assertSame(UserRole::Customer, $customer->role);
        $this->assertNull($customer->blocked_at);
        $this->assertTrue(Hash::check('password', $customer->password));
    }

    public function test_escapes_the_name_in_the_form(): void
    {
        $customer = User::factory()->create(['name' => '"><script>alert(\'xss\')</script>']);

        $response = $this->actingAs($customer)->get('/profile');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }
}
