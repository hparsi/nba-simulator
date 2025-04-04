<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use Carbon\Carbon;

class GameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = Team::all();
        
        if ($teams->isEmpty()) {
            $this->command->info('No teams found. Please run TeamSeeder first.');
            return;
        }

        $season = Season::updateOrCreate(
            ['is_active' => true],
            [
                'name' => '2023-2024 Regular Season',
                'year_start' => 2023,
                'year_end' => 2024,
                'start_date' => Carbon::create(2023, 10, 24),
                'end_date' => Carbon::create(2024, 4, 14),
                'is_active' => true,
            ]
        );

        $teamCount = count($teams);
        for ($i = 0; $i < 5; $i++) {
            $gameDate = Carbon::now()->addDays($i);
            
            for ($j = 0; $j < 5; $j++) {
                $homeTeamIndex = rand(0, $teamCount - 1);
                $awayTeamIndex = ($homeTeamIndex + 1 + rand(0, $teamCount - 2)) % $teamCount;
                
                $homeTeam = $teams[$homeTeamIndex];
                $awayTeam = $teams[$awayTeamIndex];
                
                Game::updateOrCreate(
                    [
                        'season_id' => $season->id,
                        'home_team_id' => $homeTeam->id,
                        'away_team_id' => $awayTeam->id,
                        'scheduled_at' => $gameDate->copy()->addHours(17 + $j * 1), // Games from 5PM to 9PM hourly
                    ],
                    [
                        'status' => 'scheduled',
                        'home_team_score' => 0,
                        'away_team_score' => 0,
                        'current_quarter' => 0,
                        'quarter_time_seconds' => 0,
                    ]
                );
                
                $this->command->info("Created game: {$homeTeam->name} vs {$awayTeam->name} at {$gameDate->format('Y-m-d H:i')}");
            }
        }
    }
}
