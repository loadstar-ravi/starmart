<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Sample products as [name, price, stock], grouped by category name.
     *
     * @var array<string, list<array{0: string, 1: int, 2: int}>>
     */
    private const array CATALOG = [
        'Electronics' => [
            ['Apple iPhone 15 (128 GB)', 69900, 25],
            ['Samsung Galaxy S24', 74999, 18],
            ['OnePlus Nord CE 4', 24999, 40],
            ['Dell Inspiron 15 Laptop', 54990, 12],
            ['Apple MacBook Air M2', 99900, 8],
            ['Sony WH-1000XM5 Headphones', 26990, 30],
            ['boAt Airdopes 141 Earbuds', 1299, 120],
            ['Logitech M235 Wireless Mouse', 799, 75],
        ],
        'Fashion' => [
            ['Men\'s Cotton Casual Shirt', 899, 60],
            ['Women\'s Printed Kurta', 1199, 45],
            ['Slim Fit Denim Jeans', 1799, 50],
            ['Running Shoes', 2499, 35],
            ['Leather Wallet', 699, 80],
            ['Analog Wrist Watch', 2999, 22],
            ['Polarised Sunglasses', 1499, 0],
            ['Canvas Backpack', 1299, 40],
        ],
        'Home & Kitchen' => [
            ['Non-Stick Cookware Set', 2499, 30],
            ['Stainless Steel Water Bottle', 499, 150],
            ['Mixer Grinder 750W', 3499, 20],
            ['Double Bed Cotton Bedsheet', 999, 65],
            ['LED Desk Lamp', 899, 40],
            ['Electric Kettle 1.5L', 1099, 55],
            ['Wall Clock', 599, 70],
            ['Vacuum Cleaner', 6999, 10],
        ],
        'Books' => [
            ['The Psychology of Money', 399, 90],
            ['Atomic Habits', 499, 110],
            ['Wings of Fire', 299, 75],
            ['The Alchemist', 349, 100],
            ['Sapiens: A Brief History of Humankind', 599, 60],
            ['Clean Code', 2999, 15],
            ['Rich Dad Poor Dad', 399, 85],
            ['The White Tiger', 350, 40],
        ],
        'Sports & Fitness' => [
            ['Yoga Mat 6mm', 699, 80],
            ['Adjustable Dumbbell Set 20kg', 2999, 25],
            ['Cricket Bat English Willow', 8999, 10],
            ['Football Size 5', 899, 45],
            ['Badminton Racket Pair', 1299, 35],
            ['Skipping Rope', 249, 130],
            ['Fitness Tracker Band', 2499, 30],
            ['Gym Duffel Bag', 999, 50],
        ],
        'Beauty & Personal Care' => [
            ['Vitamin C Face Serum', 599, 70],
            ['Herbal Shampoo 340ml', 349, 95],
            ['Beard Trimmer', 1499, 40],
            ['Hair Dryer 1200W', 1199, 30],
            ['Sunscreen SPF 50', 449, 85],
            ['Perfume Eau de Parfum 100ml', 1999, 20],
            ['Moisturising Body Lotion', 399, 60],
            ['Electric Toothbrush', 1799, 25],
        ],
    ];

    /**
     * Seed the sample categories and products. Safe to run more than once.
     */
    public function run(): void
    {
        foreach (self::CATALOG as $categoryName => $products) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                [
                    'name' => $categoryName,
                    'description' => "Browse our {$categoryName} range.",
                    'is_active' => true,
                ],
            );

            foreach ($products as [$name, $price, $stock]) {
                Product::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                        'description' => "{$name} from our {$categoryName} range.",
                        'price' => $price,
                        'stock' => $stock,
                        'status' => ProductStatus::Active,
                    ],
                );
            }
        }
    }
}
