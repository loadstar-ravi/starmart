<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function statuses(): array
    {
        return [
            'no access' => [403, "You don't have access to this page"],
            'not found' => [404, 'Page not found'],
            'expired form' => [419, 'This page has expired'],
            'too many requests' => [429, 'Too many requests'],
            'server error' => [500, 'Something went wrong'],
            'maintenance' => [503, "We'll be right back"],
            'a client error without a page of its own' => [405, "That request didn't work"],
            'a server error without a page of its own' => [502, 'Something went wrong'],
        ];
    }

    /**
     * No real page answers with most of these statuses on demand, so a stand-in route does.
     */
    #[DataProvider('statuses')]
    public function test_shows_a_friendly_page_with_a_way_home_for_the_status(int $status, string $title): void
    {
        Route::middleware('web')->get('/error-check', fn () => abort($status));

        $response = $this->get('/error-check');

        $response->assertStatus($status);
        $response->assertSeeInOrder(["Error {$status}", e($title), 'Back to the home page'], false);
    }

    public function test_an_address_that_does_not_exist_shows_the_not_found_page(): void
    {
        $response = $this->get('/no-such-page');

        $response->assertNotFound();
        $response->assertSee('Page not found');
        $response->assertSee('Back to the home page');
    }

    public function test_an_error_inside_the_admin_panel_links_back_to_the_admin_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin/orders/999999');

        $response->assertNotFound();
        $response->assertSee('Back to the admin dashboard');
        $response->assertDontSee('Back to the home page');
    }

    public function test_a_customer_refused_from_the_admin_panel_is_linked_to_the_home_page(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/admin');

        $response->assertForbidden();
        $response->assertSee("You don't have access to this page");
        $response->assertSee('Back to the home page');
        $response->assertDontSee('Back to the admin dashboard');
    }

    public function test_hides_the_details_of_an_unexpected_error_and_still_reports_it(): void
    {
        config(['app.debug' => false]);
        Exceptions::fake();
        Route::middleware('web')->get('/error-check', fn () => throw new RuntimeException('SQLSTATE[HY000] secret detail'));

        $response = $this->get('/error-check');

        $response->assertInternalServerError();
        $response->assertSee('Something went wrong');
        $response->assertDontSee('secret detail');
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_the_api_returns_500_without_the_details_of_an_unexpected_error(): void
    {
        config(['app.debug' => false]);
        Exceptions::fake();
        Route::middleware('api')->get('/api/error-check', fn () => throw new RuntimeException('SQLSTATE[HY000] secret detail'));

        $response = $this->getJson('/api/error-check');

        $response->assertInternalServerError();
        $response->assertExactJson(['message' => 'Server Error']);
    }

    public function test_the_api_answers_a_refusal_as_json_not_with_an_error_page(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->get('/api/cart');

        $response->assertForbidden();
        $response->assertHeader('content-type', 'application/json');
        $response->assertDontSee('Back to the home page');
    }
}
