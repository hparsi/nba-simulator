<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Season;
use App\Models\Team;
use App\Models\Game;

class ScheduleNextWeekTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * @var Season
     */
    protected $season;
    
    /**
     * @var \Illuminate\Database\Eloquent\Collection|Team[]
     */
    protected $teams;
    
    /**
     * Set up common test data before each test.
     */
    public function setUp(): void
    {
        parent::setUp();
        
        $this->season = Season::factory()->create([
            'name' => 'Test Season',
            'is_active' => true,
            'year_start' => 2023,
            'year_end' => 2024,
            'start_date' => now(),
            'end_date' => now()->addMonths(8)
        ]);
        
        $this->teams = Team::factory()->count(6)->create();
    }
    
    /**
     * Test that new games can be scheduled successfully.
     */
    public function test_can_schedule_new_games(): void
    {
        $response = $this->postJson('/api/games/schedule-next-week', [
            'played_matchups' => []
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'games' => [
                    '*' => [
                        'id',
                        'status',
                        'date',
                        'home_team' => [
                            'id',
                            'name',
                            'score'
                        ],
                        'away_team' => [
                            'id',
                            'name',
                            'score'
                        ],
                        'current_quarter',
                        'quarter_time_seconds'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true
            ]);
        
        $gamesCount = Game::where('season_id', $this->season->id)->count();
        $this->assertGreaterThan(0, $gamesCount, 'No games were created');
        
        $expectedGamesCount = floor(count($this->teams) / 2);
        $this->assertEquals($expectedGamesCount, $gamesCount, "Expected {$expectedGamesCount} games to be created");
        
        $games = Game::where('season_id', $this->season->id)->get();
        $usedTeams = [];
        
        foreach ($games as $game) {
            $this->assertNotContains($game->home_team_id, $usedTeams);
            $this->assertNotContains($game->away_team_id, $usedTeams);
            
            $usedTeams[] = $game->home_team_id;
            $usedTeams[] = $game->away_team_id;
        }
    }
    
    /**
     * Test that teams which recently played each other are not matched again.
     */
    public function test_avoids_recent_matchups(): void
    {
        $existingGame = Game::factory()->create([
            'season_id' => $this->season->id,
            'home_team_id' => $this->teams[0]->id,
            'away_team_id' => $this->teams[1]->id,
            'status' => 'completed'
        ]);
        
        $response = $this->postJson('/api/games/schedule-next-week', [
            'played_matchups' => [
                [
                    'home_team_id' => $this->teams[2]->id,
                    'away_team_id' => $this->teams[3]->id
                ]
            ]
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'games' => [
                    '*' => [
                        'id',
                        'status',
                        'date',
                        'home_team' => [
                            'id',
                            'name',
                            'score'
                        ],
                        'away_team' => [
                            'id',
                            'name',
                            'score'
                        ]
                    ]
                ]
            ])
            ->assertJson([
                'success' => true
            ]);
        
        $newGames = Game::where('season_id', $this->season->id)
            ->where('id', '!=', $existingGame->id)
            ->get();
            
        foreach ($this->teams as $index => $team) {
            echo "teams[$index] = Team ID {$team->id} ({$team->name})\n";
        }

        foreach ($newGames as $game) {
            $homeTeam = Team::find($game->home_team_id);
            $awayTeam = Team::find($game->away_team_id);
            echo "- Game ID {$game->id}: {$homeTeam->name} (ID {$game->home_team_id}) vs {$awayTeam->name} (ID {$game->away_team_id})\n";
        }
        
        $rematchFound = false;
        foreach ($newGames as $game) {
            if (($game->home_team_id == $this->teams[0]->id && $game->away_team_id == $this->teams[1]->id) ||
                ($game->home_team_id == $this->teams[1]->id && $game->away_team_id == $this->teams[0]->id)) {
                $rematchFound = true;
                break;
            }
        }
        
        $this->assertFalse($rematchFound, "Teams {$this->teams[0]->name} and {$this->teams[1]->name} should not be matched again");
        
        $rematchFound = false;
        foreach ($newGames as $game) {
            if (($game->home_team_id == $this->teams[2]->id && $game->away_team_id == $this->teams[3]->id) ||
                ($game->home_team_id == $this->teams[3]->id && $game->away_team_id == $this->teams[2]->id)) {
                $rematchFound = true;
                break;
            }
        }
        
        $this->assertFalse($rematchFound, "Teams {$this->teams[2]->name} and {$this->teams[3]->name} should not be matched again");
    }
    
    /**
     * Test that appropriate error is returned when there's no active season.
     */
    public function test_error_when_no_active_season(): void
    {
        $this->season->update(['is_active' => false]);
        
        $response = $this->postJson('/api/games/schedule-next-week', [
            'played_matchups' => []
        ]);
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'No active season found'
            ]);
    }
    
    /**
     * Test validation errors for bad input.
     */
    public function test_validation_errors(): void
    {
        $response = $this->postJson('/api/games/schedule-next-week', [
            'played_matchups' => [
                [
                    'home_team_id' => 999, // Non-existent team ID
                    'away_team_id' => $this->teams[0]->id
                ]
            ]
        ]);
        
        $response->assertStatus(422) // Validation error status
            ->assertJsonValidationErrors(['played_matchups.0.home_team_id']);
        
        $response = $this->postJson('/api/games/schedule-next-week', [
            'played_matchups' => [
                [
                    'home_team_id' => $this->teams[0]->id
                    // away_team_id is missing
                ]
            ]
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['played_matchups.0.away_team_id']);
    }
    
    /**
     * Test the special case when there are not enough teams for matchups.
     */
    public function test_handles_not_enough_teams(): void
    {
        Team::whereNotIn('id', [$this->teams[0]->id])->delete();
        
        $response = $this->postJson('/api/games/schedule-next-week', [
            'played_matchups' => []
        ]);
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Not enough teams to schedule games'
            ]);
    }
    
    /**
     * Test that next week's date is used for scheduling.
     */
    public function test_schedules_for_next_week(): void
    {
        $response = $this->postJson('/api/games/schedule-next-week', [
            'played_matchups' => []
        ]);
        
        $response->assertStatus(200);
        
        $game = Game::where('season_id', $this->season->id)->first();
        
        $nextWeek = now()->addWeek()->startOfDay();
        $gameDate = $game->scheduled_at->startOfDay();
        
        $this->assertTrue(
            $gameDate->between(
                $nextWeek->copy()->subDay(),
                $nextWeek->copy()->addDay()
            ),
            'Game scheduled date is not within a day of next week'
        );
    }
}
