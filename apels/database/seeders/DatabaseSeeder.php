<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Bootstrap role + permission terlebih dulu (Req 2.1, 2.7-2.9).
        $this->call(RoleSeeder::class);

        // Modul MVP (Financial Presentation, Client Meeting) + basic placeholder
        // (Req 10.2, 11.1, 11.2, 11.3).
        $this->call(ModuleSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
