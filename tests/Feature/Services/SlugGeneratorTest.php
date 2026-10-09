<?php

namespace Tests\Feature\Services;

use App\Models\Product;
use App\Services\SlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_a_lowercase_hyphenated_slug_from_the_text(): void
    {
        $slug = (new SlugGenerator)->generate(Product::class, 'Apple iPhone 15 (128 GB)');

        $this->assertSame('apple-iphone-15-128-gb', $slug);
    }

    public function test_appends_the_next_free_number_when_the_slug_is_taken(): void
    {
        Product::factory()->create(['slug' => 'gaming-laptop']);
        Product::factory()->create(['slug' => 'gaming-laptop-2']);

        $slug = (new SlugGenerator)->generate(Product::class, 'Gaming Laptop');

        $this->assertSame('gaming-laptop-3', $slug);
    }

    public function test_does_not_treat_the_slug_of_the_ignored_model_as_taken(): void
    {
        $product = Product::factory()->create(['slug' => 'gaming-laptop']);

        $slug = (new SlugGenerator)->generate(Product::class, 'Gaming Laptop', $product);

        $this->assertSame('gaming-laptop', $slug);
    }

    public function test_falls_back_to_the_model_name_when_the_text_has_no_usable_characters(): void
    {
        $slug = (new SlugGenerator)->generate(Product::class, '!!!');

        $this->assertSame('product', $slug);
    }

    public function test_cuts_the_slug_to_100_characters(): void
    {
        $slug = (new SlugGenerator)->generate(Product::class, str_repeat('a', 150));

        $this->assertSame(str_repeat('a', 100), $slug);
    }
}
