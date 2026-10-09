<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivates_an_active_category(): void
    {
        $category = Category::factory()->create(['name' => 'Books']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->from('/admin/categories')
            ->patch("/admin/categories/{$category->id}/status", ['is_active' => '0']);

        $response->assertRedirect('/admin/categories');
        $response->assertSessionHas('status', 'Category "Books" deactivated.');
        $this->assertFalse($category->refresh()->is_active);
    }

    public function test_activates_an_inactive_category(): void
    {
        $category = Category::factory()->inactive()->create(['name' => 'Books']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/categories/{$category->id}/status", ['is_active' => '1']);

        $response->assertSessionHas('status', 'Category "Books" activated.');
        $this->assertTrue($category->refresh()->is_active);
    }

    public function test_rejects_a_request_without_the_new_status(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/categories/{$category->id}/status", []);

        $response->assertSessionHasErrors(['is_active' => 'The is active field is required.']);
        $this->assertTrue($category->refresh()->is_active);
    }

    public function test_forbids_customers_from_changing_the_status(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->patch("/admin/categories/{$category->id}/status", ['is_active' => '0']);

        $response->assertForbidden();
        $this->assertTrue($category->refresh()->is_active);
    }
}
