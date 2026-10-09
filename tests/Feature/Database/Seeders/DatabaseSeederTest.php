<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_admin_a_customer_and_the_sample_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(UserRole::Admin, User::where('email', 'admin@starmart.test')->sole()->role);
        $this->assertSame(UserRole::Customer, User::where('email', 'customer@starmart.test')->sole()->role);
        $this->assertDatabaseCount('categories', 6);
        $this->assertDatabaseCount('products', 48);
        $this->assertSame(
            'electronics',
            Product::where('slug', 'apple-iphone-15-128-gb')->sole()->category->slug,
        );
        $this->assertSame(8, Category::where('slug', 'books')->sole()->products()->count());
    }

    public function test_seeding_twice_creates_each_record_only_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('categories', 6);
        $this->assertDatabaseCount('products', 48);
    }
}
