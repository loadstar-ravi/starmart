<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    private const array NEW_PASSWORD = [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ];

    public function test_redirects_guests_to_the_login_form(): void
    {
        $response = $this->put('/profile/password', self::NEW_PASSWORD);

        $response->assertRedirectToRoute('login');
    }

    public function test_forbids_admins_and_keeps_their_password(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put('/profile/password', self::NEW_PASSWORD);

        $response->assertForbidden();
        $this->assertTrue(Hash::check('password', $admin->refresh()->password));
    }

    public function test_changes_the_password_and_keeps_the_customer_signed_in(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->put('/profile/password', self::NEW_PASSWORD);

        $response->assertRedirectToRoute('profile.edit');
        $response->assertSessionHas('status', 'Your password has been changed.');
        $this->assertTrue(Hash::check('new-password-123', $customer->refresh()->password));
        $this->assertAuthenticatedAs($customer);
    }

    public function test_the_old_password_no_longer_signs_the_customer_in(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->put('/profile/password', self::NEW_PASSWORD);
        $this->post('/logout');

        $response = $this->post('/login', ['email' => $customer->email, 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_the_new_password_signs_the_customer_in(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->put('/profile/password', self::NEW_PASSWORD);
        $this->post('/logout');

        $response = $this->post('/login', ['email' => $customer->email, 'password' => 'new-password-123']);

        $response->assertRedirectToRoute('home');
        $this->assertAuthenticatedAs($customer);
    }

    public function test_changes_only_the_password_of_the_customer_who_asked(): void
    {
        $other = User::factory()->create();

        $this->actingAs(User::factory()->create())->put('/profile/password', self::NEW_PASSWORD);

        $this->assertTrue(Hash::check('password', $other->refresh()->password));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidPasswordChanges(): array
    {
        return [
            'no current password' => [
                ['current_password' => null],
                'current_password',
                'The current password field is required.',
            ],
            'wrong current password' => [
                ['current_password' => 'not-my-password'],
                'current_password',
                'The password is incorrect.',
            ],
            'no new password' => [
                ['password' => null, 'password_confirmation' => null],
                'password',
                'The new password field is required.',
            ],
            'confirmation that does not match' => [
                ['password_confirmation' => 'something-else-123'],
                'password',
                'The new password field confirmation does not match.',
            ],
            'new password shorter than 8 characters' => [
                ['password' => 'short', 'password_confirmation' => 'short'],
                'password',
                'The new password field must be at least 8 characters.',
            ],
            'new password equal to the current one' => [
                ['password' => 'password', 'password_confirmation' => 'password'],
                'password',
                'The new password field and current password must be different.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $invalid
     */
    #[DataProvider('invalidPasswordChanges')]
    public function test_rejects_an_invalid_change_and_keeps_the_old_password(array $invalid, string $field, string $message): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->put('/profile/password', [...self::NEW_PASSWORD, ...$invalid]);

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertTrue(Hash::check('password', $customer->refresh()->password));
    }

    public function test_stops_answering_after_six_attempts_in_a_minute(): void
    {
        $customer = User::factory()->create();
        $wrong = [...self::NEW_PASSWORD, 'current_password' => 'not-my-password'];

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($customer)->put('/profile/password', $wrong)->assertSessionHasErrors('current_password');
        }

        $this->actingAs($customer)->put('/profile/password', self::NEW_PASSWORD)->assertTooManyRequests();
        $this->assertTrue(Hash::check('password', $customer->refresh()->password));
    }
}
