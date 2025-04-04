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
        // Seed default user for admin access
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        
        // Call NBA simulator seeders
        $this->call([
            TeamSeeder::class,
            PlayerSeeder::class,
            GameSeeder::class,
            SeasonSeeder::class,
        ]);
    }
}
