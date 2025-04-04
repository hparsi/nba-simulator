<?php

namespace Database\Seeders;

use App\Models\Season;
use Illuminate\Database\Seeder;

class SeasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Season::create([
            'name' => '2023-2024 NBA Season',
            'year_start' => 2023,
            'year_end' => 2024,
            'start_date' => '2023-10-24',
            'end_date' => '2024-06-15',
            'is_active' => true,
        ]);
    }
} 