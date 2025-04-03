<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = [
            ['name' => 'Celtics'],
            ['name' => 'Nets'],
            ['name' => 'Knicks'],
            ['name' => '76ers'],
            ['name' => 'Raptors'],
            ['name' => 'Bulls'],
            ['name' => 'Cavaliers'],
            ['name' => 'Pistons'],
            ['name' => 'Pacers'],
            ['name' => 'Bucks'],
            ['name' => 'Hawks'],
            ['name' => 'Hornets'],
            ['name' => 'Heat'],
            ['name' => 'Magic'],
            ['name' => 'Wizards'],
            ['name' => 'Nuggets'],
            ['name' => 'Timberwolves'],
            ['name' => 'Thunder'],
            ['name' => 'Trail Blazers'],
            ['name' => 'Jazz'],
            ['name' => 'Warriors'],
            ['name' => 'Clippers'],
            ['name' => 'Lakers'],
            ['name' => 'Suns'],
            ['name' => 'Kings'],
            ['name' => 'Mavericks'],
            ['name' => 'Rockets'],
            ['name' => 'Grizzlies'],
            ['name' => 'Pelicans'],
            ['name' => 'Spurs'],
        ];
        
        foreach ($teams as $team) {
            Team::create($team);
        }
    }
}
