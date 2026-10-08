<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public const string EMAIL = 'admin@starmart.test';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            return;
        }

        User::factory()->admin()->create([
            'name' => 'StarMart Admin',
            'email' => self::EMAIL,
        ]);
    }
}
