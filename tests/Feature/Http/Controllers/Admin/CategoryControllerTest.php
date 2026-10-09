<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_admin_login_form(): void
    {
        $response = $this->get('/admin/categories');

        $response->assertRedirectToRoute('admin.login');
    }

    public function test_forbids_customers_from_listing_categories(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get('/admin/categories');

        $response->assertForbidden();
    }

    public function test_forbids_customers_from_creating_categories(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->post('/admin/categories', ['name' => 'Books', 'is_active' => '1']);

        $response->assertForbidden();
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_lists_categories_by_name_with_their_product_counts(): void
    {
        $electronics = Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Books']);
        Product::factory()->count(2)->for($electronics)->create();

        $response = $this->actingAs($this->admin())->get('/admin/categories');

        $response->assertOk();
        $response->assertSeeInOrder(['Books', 'Electronics']);
        $response->assertViewHas(
            'categories',
            fn ($categories) => $categories->firstWhere('name', 'Electronics')->products_count === 2
                && $categories->firstWhere('name', 'Books')->products_count === 0,
        );
    }

    public function test_search_lists_only_categories_whose_name_contains_the_term(): void
    {
        Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Books']);

        $response = $this->actingAs($this->admin())->get('/admin/categories?search=electr');

        $response->assertOk();
        $response->assertSee('Electronics');
        $response->assertDontSee('Books');
    }

    public function test_escapes_category_names_in_the_list(): void
    {
        Category::factory()->create(['name' => "<script>alert('xss')</script>"]);

        $response = $this->actingAs($this->admin())->get('/admin/categories');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_renders_the_create_form(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/categories/create');

        $response->assertOk();
        $response->assertSee('New category');
    }

    public function test_renders_the_edit_form_with_the_current_values(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);

        $response = $this->actingAs($this->admin())->get("/admin/categories/{$category->id}/edit");

        $response->assertOk();
        $response->assertSee('value="Electronics"', false);
        $response->assertSee('value="electronics"', false);
    }

    public function test_valid_payload_creates_a_category_with_a_slug_generated_from_the_name(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/categories', [
            'name' => 'Home & Kitchen',
            'description' => 'Everything for the home.',
            'is_active' => '1',
        ]);

        $response->assertRedirectToRoute('admin.categories.index');
        $response->assertSessionHas('status', 'Category "Home & Kitchen" created.');

        $this->assertDatabaseHas('categories', [
            'name' => 'Home & Kitchen',
            'slug' => 'home-kitchen',
            'description' => 'Everything for the home.',
            'is_active' => true,
        ]);
    }

    public function test_generated_slug_gets_a_numeric_suffix_when_another_category_uses_it(): void
    {
        Category::factory()->create(['name' => 'Home Kitchen', 'slug' => 'home-kitchen']);

        $this->actingAs($this->admin())->post('/admin/categories', ['name' => 'Home & Kitchen', 'is_active' => '1']);

        $this->assertDatabaseHas('categories', ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen-2']);
    }

    public function test_keeps_a_slug_typed_by_the_admin(): void
    {
        $this->actingAs($this->admin())->post('/admin/categories', [
            'name' => 'Books',
            'slug' => 'reading',
            'is_active' => '0',
        ]);

        $this->assertDatabaseHas('categories', ['name' => 'Books', 'slug' => 'reading', 'is_active' => false]);
    }

    public function test_empty_payload_returns_required_errors_and_creates_no_category(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/categories', []);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'is_active' => 'The status field is required.',
        ]);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_rejects_a_name_that_another_category_uses(): void
    {
        Category::factory()->create(['name' => 'Books']);

        $response = $this->actingAs($this->admin())->post('/admin/categories', ['name' => 'Books', 'is_active' => '1']);

        $response->assertSessionHasErrors(['name' => 'The name has already been taken.']);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_rejects_a_slug_that_another_category_uses(): void
    {
        Category::factory()->create(['name' => 'Books', 'slug' => 'books']);

        $response = $this->actingAs($this->admin())->post('/admin/categories', [
            'name' => 'Novels',
            'slug' => 'books',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['slug' => 'The slug has already been taken.']);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_rejects_a_slug_that_is_not_lowercase_words_joined_by_hyphens(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/categories', [
            'name' => 'Books',
            'slug' => 'Not A Slug',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['slug' => 'The slug may only contain lowercase letters, numbers and hyphens.']);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_valid_payload_updates_the_category(): void
    {
        $category = Category::factory()->create(['name' => 'Books', 'slug' => 'books']);

        $response = $this->actingAs($this->admin())->put("/admin/categories/{$category->id}", [
            'name' => 'Books & Media',
            'slug' => 'books',
            'description' => 'Paperbacks and more.',
            'is_active' => '0',
        ]);

        $response->assertRedirectToRoute('admin.categories.index');
        $response->assertSessionHas('status', 'Category "Books & Media" updated.');
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Books & Media',
            'slug' => 'books',
            'description' => 'Paperbacks and more.',
            'is_active' => false,
        ]);
    }

    public function test_clearing_the_slug_regenerates_it_from_the_new_name(): void
    {
        $category = Category::factory()->create(['name' => 'Books', 'slug' => 'books']);

        $this->actingAs($this->admin())->put("/admin/categories/{$category->id}", [
            'name' => 'Books & Media',
            'slug' => '',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'books-media']);
    }

    public function test_update_accepts_the_name_and_slug_the_category_already_has(): void
    {
        $category = Category::factory()->create(['name' => 'Books', 'slug' => 'books']);

        $response = $this->actingAs($this->admin())->put("/admin/categories/{$category->id}", [
            'name' => 'Books',
            'slug' => 'books',
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirectToRoute('admin.categories.index');
    }

    public function test_update_rejects_a_slug_that_another_category_uses(): void
    {
        Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $category = Category::factory()->create(['name' => 'Books', 'slug' => 'books']);

        $response = $this->actingAs($this->admin())->put("/admin/categories/{$category->id}", [
            'name' => 'Books',
            'slug' => 'electronics',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['slug' => 'The slug has already been taken.']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'books']);
    }

    public function test_deletes_a_category_that_has_no_products(): void
    {
        $category = Category::factory()->create(['name' => 'Books']);

        $response = $this->actingAs($this->admin())->delete("/admin/categories/{$category->id}");

        $response->assertRedirectToRoute('admin.categories.index');
        $response->assertSessionHas('status', 'Category "Books" deleted.');
        $this->assertModelMissing($category);
    }

    public function test_does_not_delete_a_category_that_still_has_products(): void
    {
        $category = Category::factory()->create(['name' => 'Books']);
        Product::factory()->for($category)->create();

        $response = $this->actingAs($this->admin())
            ->from('/admin/categories')
            ->delete("/admin/categories/{$category->id}");

        $response->assertRedirect('/admin/categories');
        $response->assertSessionHas(
            'error',
            'Category "Books" still has products. Move or delete them before deleting the category.',
        );
        $this->assertModelExists($category);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
