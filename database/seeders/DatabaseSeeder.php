<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public const string CUSTOMER_EMAIL = 'customer@starmart.test';

    /**
     * Seed the application's database. Safe to run more than once.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            CatalogSeeder::class,
        ]);

        if (User::where('email', self::CUSTOMER_EMAIL)->doesntExist()) {
            User::factory()->create([
                'name' => 'Test Customer',
                'email' => self::CUSTOMER_EMAIL,
            ]);
        }
    }
}
