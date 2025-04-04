<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameStatistic;
use App\Models\Player;
use App\Models\PlayerStatistic;
use App\Models\Season;
use App\Models\Team;
use App\Services\GameSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GameSimulationServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameSimulationService $gameSimulationService;
    private Team $homeTeam;
    private Team $awayTeam;
    private Game $game;
    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameSimulationService = new GameSimulationService();

        $this->createTestData();
    }

    private function createTestData(): void
    {
        $this->season = Season::factory()->active()->create();

        $this->homeTeam = Team::factory()->eastern()->create([
            'name' => 'Boston Celtics (East)',
        ]);

        $this->awayTeam = Team::factory()->western()->create([
            'name' => 'Los Angeles Lakers (West)',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Player::factory()->create([
                'team_id' => $this->homeTeam->id,
                'first_name' => "Home Player {$i}",
                'last_name' => "Test",
                'jersey_number' => $i,
            ]);
        }

        for ($i = 1; $i <= 5; $i++) {
            Player::factory()->create([
                'team_id' => $this->awayTeam->id,
                'first_name' => "Away Player {$i}",
                'last_name' => "Test",
                'jersey_number' => $i,
            ]);
        }

        $this->game = Game::factory()->create([
            'season_id' => $this->season->id,
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'status' => 'scheduled',
        ]);
    }

    #[Test]
    public function it_can_simulate_a_game()
    {
        $this->gameSimulationService->simulateGame($this->game);

        $this->game->refresh();

        $this->assertEquals('completed', $this->game->status);
        $this->assertNotNull($this->game->started_at);
        $this->assertNotNull($this->game->ended_at);
        
        $this->assertGreaterThan(0, $this->game->home_team_score);
        $this->assertGreaterThan(0, $this->game->away_team_score);

        $eventCount = GameEvent::where('game_id', $this->game->id)->count();
        $this->assertGreaterThan(0, $eventCount);

        $homeStats = GameStatistic::where('game_id', $this->game->id)
            ->where('is_home_team', true)
            ->first();
        $awayStats = GameStatistic::where('game_id', $this->game->id)
            ->where('is_home_team', false)
            ->first();
            
        $this->assertNotNull($homeStats);
        $this->assertNotNull($awayStats);
        
        $this->assertGreaterThanOrEqual(0, $homeStats->field_goals_made);
        $this->assertGreaterThanOrEqual(0, $homeStats->field_goals_attempted);
        $this->assertGreaterThanOrEqual(0, $homeStats->three_pointers_made);
        $this->assertGreaterThanOrEqual(0, $homeStats->three_pointers_attempted);
        
        $this->assertGreaterThanOrEqual(0, $homeStats->q1_score);
        $this->assertGreaterThanOrEqual(0, $homeStats->q2_score);
        $this->assertGreaterThanOrEqual(0, $homeStats->q3_score);
        $this->assertGreaterThanOrEqual(0, $homeStats->q4_score);
        
        $this->assertGreaterThanOrEqual(0, $awayStats->q1_score);
        $this->assertGreaterThanOrEqual(0, $awayStats->q2_score);
        $this->assertGreaterThanOrEqual(0, $awayStats->q3_score);
        $this->assertGreaterThanOrEqual(0, $awayStats->q4_score);
        
        $playerStatsCount = PlayerStatistic::where('game_id', $this->game->id)->count();
        $this->assertGreaterThan(0, $playerStatsCount);
    }

    #[Test]
    public function it_creates_appropriate_game_events()
    {
        $this->gameSimulationService->simulateGame($this->game);

        $startEvent = GameEvent::where('game_id', $this->game->id)
            ->where('event_type', 'game_start')
            ->first();
        $this->assertNotNull($startEvent);

        $endEvent = GameEvent::where('game_id', $this->game->id)
            ->where('event_type', 'game_end')
            ->first();
        $this->assertNotNull($endEvent);

        $quarterEvents = GameEvent::where('game_id', $this->game->id)
            ->whereIn('event_type', ['quarter_start', 'quarter_end'])
            ->count();
        $this->assertGreaterThanOrEqual(2, $quarterEvents);

        $shotEvents = GameEvent::where('game_id', $this->game->id)
            ->whereIn('event_type', ['field_goal', 'three_pointer', 'free_throw'])
            ->count();
        $this->assertGreaterThan(0, $shotEvents);
    }
    
    #[Test]
    public function it_correctly_tracks_player_statistics()
    {
        $this->gameSimulationService->simulateGame($this->game);
        
        $playerStats = PlayerStatistic::where('game_id', $this->game->id)->get();
        
        foreach ($playerStats as $stat) {
            $this->assertGreaterThanOrEqual($stat->field_goals_made, $stat->field_goals_attempted);
            $this->assertGreaterThanOrEqual($stat->three_pointers_made, $stat->three_pointers_attempted);
            $this->assertGreaterThanOrEqual($stat->free_throws_made, $stat->free_throws_attempted);
            
            $this->assertGreaterThanOrEqual(0, $stat->points);
        }
    }

    #[Test]
    public function it_tracks_fouls_correctly()
    {
        $this->gameSimulationService->simulateGame($this->game);
        
        $foulEvents = GameEvent::where('game_id', $this->game->id)
            ->where('event_type', 'foul')
            ->get();
        
        $this->assertGreaterThan(0, $foulEvents->count());
        
        foreach ($foulEvents as $foulEvent) {
            $this->assertNotNull($foulEvent->player_id);
            $this->assertNotNull($foulEvent->team_id);
            $this->assertStringContainsString('foul', strtolower($foulEvent->description));
        }
        
        $playerFoulCount = $foulEvents->groupBy('player_id')->count();
        $this->assertGreaterThan(1, $playerFoulCount, 'Fouls should be distributed among multiple players');
    }

    #[Test]
    public function it_tracks_shot_accuracy_correctly()
    {
        $this->gameSimulationService->simulateGame($this->game);
        
        $playerStats = PlayerStatistic::where('game_id', $this->game->id)->get();
        
        foreach ($playerStats as $stat) {
            if ($stat->field_goals_attempted > 0) {
                $calculatedFGPercentage = ($stat->field_goals_made / $stat->field_goals_attempted) * 100;
                
                if ($stat->field_goal_percentage !== null) {
                    $this->assertEqualsWithDelta(
                        $calculatedFGPercentage, 
                        $stat->field_goal_percentage, 
                        0.2, 
                        'Field goal percentage should match calculation'
                    );
                }
            }
            
            if ($stat->three_pointers_attempted > 0) {
                $calculated3PPercentage = ($stat->three_pointers_made / $stat->three_pointers_attempted) * 100;
                
                if ($stat->three_point_percentage !== null) {
                    $this->assertEqualsWithDelta(
                        $calculated3PPercentage, 
                        $stat->three_point_percentage, 
                        0.2, 
                        'Three point percentage should match calculation'
                    );
                }
            }
            
            if ($stat->free_throws_attempted > 0) {
                $calculatedFTPercentage = ($stat->free_throws_made / $stat->free_throws_attempted) * 100;
                
                if ($stat->free_throw_percentage !== null) {
                    $this->assertEqualsWithDelta(
                        $calculatedFTPercentage, 
                        $stat->free_throw_percentage, 
                        0.2, 
                        'Free throw percentage should match calculation'
                    );
                }
            }
        }
    }

    #[Test]
    public function it_calculates_player_points_correctly()
    {
        $this->gameSimulationService->simulateGame($this->game);
        
        $playerStats = PlayerStatistic::where('game_id', $this->game->id)->get();
        
        foreach ($playerStats as $stat) {
            $this->assertGreaterThanOrEqual(0, $stat->points, 'Player points should be non-negative');
            
            $this->assertLessThanOrEqual(100, $stat->points, 'Player points should not exceed realistic maximum');
            
            if ($stat->field_goals_attempted > 0 || $stat->free_throws_attempted > 0) {
                if ($stat->field_goals_made > 0 || $stat->free_throws_made > 0) {
                    $this->assertGreaterThan(0, $stat->points, 'Player with made shots should have points');
                }
            }
            
            if ($stat->field_goals_made === 0 && $stat->free_throws_made === 0) {
            }
        }
        
        $homePlayerPoints = $playerStats->where('team_id', $this->homeTeam->id)->sum('points');
        $awayPlayerPoints = $playerStats->where('team_id', $this->awayTeam->id)->sum('points');
        
        $this->game->refresh();
        
        $this->assertGreaterThan(0, $homePlayerPoints, 'Home team player points should be positive');
        $this->assertGreaterThan(0, $awayPlayerPoints, 'Away team player points should be positive');
    }

    #[Test]
    public function it_distributes_shots_among_multiple_players()
    {
        $this->gameSimulationService->simulateGame($this->game);
        
        $playerStats = PlayerStatistic::where('game_id', $this->game->id)->get();
        
        $playersWithPoints = $playerStats->filter(function ($stat) {
            return $stat->points > 0;
        })->count();
        
        $this->assertGreaterThan(1, $playersWithPoints, 'Multiple players should score points in a game');
        
        $playersWithFieldGoals = $playerStats->filter(function ($stat) {
            return $stat->field_goals_attempted > 0;
        })->count();
        
        $this->assertGreaterThan(1, $playersWithFieldGoals, 'Multiple players should attempt field goals');
    }

    #[Test]
    public function it_produces_reasonable_game_scores()
    {
        $minScore = 999;
        $maxScore = 0;
        
        for ($i = 0; $i < 5; $i++) {
            $game = Game::factory()->create([
                'season_id' => $this->season->id,
                'home_team_id' => $this->homeTeam->id,
                'away_team_id' => $this->awayTeam->id,
                'status' => 'scheduled',
            ]);
            
            $this->gameSimulationService->simulateGame($game);
            
            $game->refresh();
            
            $minScore = min($minScore, $game->home_team_score, $game->away_team_score);
            $maxScore = max($maxScore, $game->home_team_score, $game->away_team_score);
        }
        
        $this->assertGreaterThan(30, $minScore, 'Minimum score should be reasonable');
        $this->assertLessThan(180, $maxScore, 'Maximum score should be reasonable');
    }
} 