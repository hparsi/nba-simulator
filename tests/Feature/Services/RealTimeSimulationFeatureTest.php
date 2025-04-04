<?php

namespace Tests\Feature\Services;

use App\Models\Game;
use App\Models\Team;
use App\Models\Season;
use App\Models\TeamSeason;
use App\Services\GameSimulationService;
use App\Services\RealTimeSimulationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

class RealTimeSimulationFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var MockInterface|GameSimulationService */
    private $mockGameSimulationService;
    private RealTimeSimulationService $realTimeSimulationService;
    private Season $season;
    private Collection $teams;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createTestData();
        
        // Create a proper Mockery mock instance
        $this->mockGameSimulationService = Mockery::mock(GameSimulationService::class);
        $this->realTimeSimulationService = new RealTimeSimulationService($this->mockGameSimulationService);
        
        Cache::forget('simulation_state');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create test data needed for all tests by directly inserting into the database
     */
    private function createTestData(): void
    {
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => 'Test Season 2023',
            'year_start' => 2023,
            'year_end' => 2024,
            'start_date' => Carbon::create(2023, 10, 1),
            'end_date' => Carbon::create(2024, 6, 1),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->season = Season::find($seasonId);
        
        $this->teams = collect();
        for ($i = 1; $i <= 6; $i++) {
            $teamId = DB::table('teams')->insertGetId([
                'name' => "Team $i",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $team = Team::find($teamId);
            $this->teams->push($team);
            
            TeamSeason::create([
                'team_id' => $team->id,
                'season_id' => $this->season->id,
                'wins' => 0,
                'losses' => 0,
                'games_played' => 0,
                'points_for' => 0,
                'points_against' => 0
            ]);
        }
    }

    /**
     * Create scheduled games for testing
     */
    private function createScheduledGames($count = 2): Collection
    {
        $games = collect();
        $now = Carbon::now();
        
        for ($i = 0; $i < $count; $i++) {
            $gameId = DB::table('games')->insertGetId([
                'season_id' => $this->season->id,
                'home_team_id' => $this->teams[$i * 2]->id,
                'away_team_id' => $this->teams[$i * 2 + 1]->id,
                'status' => 'scheduled',
                'home_team_score' => 0,
                'away_team_score' => 0,
                'scheduled_at' => $now,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $game = Game::find($gameId);
            $games->push($game);
        }
        
        return $games;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_start_a_simulation()
    {
        $games = $this->createScheduledGames(2);
        
        $this->mockGameSimulationService
            ->shouldReceive('initializeGame')
            ->times(2)
            ->andReturnUsing(function (Game $game) {
                $game->status = 'in_progress';
                $game->save();
                return null;
            });
            
        $this->realTimeSimulationService->startSimulation($games);
        
        $state = $this->realTimeSimulationService->getSimulationState();
        
        $this->assertTrue($state['is_active']);
        $this->assertCount(2, $state['active_games']);
        $this->assertContains($games[0]->id, $state['active_games']);
        $this->assertContains($games[1]->id, $state['active_games']);
        $this->assertEmpty($state['completed_games']);
        
        $this->assertArrayHasKey($games[0]->id, $state['game_progress']);
        $this->assertEquals(0, $state['game_progress'][$games[0]->id]['current_minute']);
        $this->assertEquals(48, $state['game_progress'][$games[0]->id]['total_minutes']);
        
        $this->assertEquals('in_progress', Game::find($games[0]->id)->status);
        $this->assertEquals('in_progress', Game::find($games[1]->id)->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_process_game_updates()
    {
        $games = $this->createScheduledGames(1);
        $game = $games[0];
        
        $this->mockGameSimulationService
            ->shouldReceive('initializeGame')
            ->once()
            ->andReturnUsing(function (Game $game) {
                $game->status = 'in_progress';
                $game->save();
                return null;
            });
        
        $this->mockGameSimulationService
            ->shouldReceive('simulateMinute')
            ->once()
            ->andReturnUsing(function (Game $game) {
                $game->home_team_score += 2;
                $game->away_team_score += 3;
                $game->save();
                
                return [
                    'minute' => 1,
                    'events' => [
                        ['type' => 'shot', 'team' => 'home', 'points' => 2, 'success' => true],
                        ['type' => 'shot', 'team' => 'away', 'points' => 3, 'success' => true]
                    ]
                ];
            });
        
        $this->realTimeSimulationService->startSimulation($games);
        
        $result = $this->realTimeSimulationService->processUpdate();
        
        $this->assertArrayHasKey('active_games', $result);
        $this->assertArrayHasKey('completed_games', $result);
        $this->assertArrayHasKey('updates', $result);
        
        $this->assertContains($game->id, $result['active_games']);
        $this->assertEmpty($result['completed_games']);
        
        $this->assertArrayHasKey($game->id, $result['updates']);
        $this->assertEquals(1, $result['updates'][$game->id]['minute']);
        $this->assertCount(2, $result['updates'][$game->id]['events']);
        
        $state = $this->realTimeSimulationService->getSimulationState();
        $this->assertEquals(1, $state['game_progress'][$game->id]['current_minute']);
        $this->assertEquals(2, $state['game_progress'][$game->id]['home_score']);
        $this->assertEquals(3, $state['game_progress'][$game->id]['away_score']);
        
        $updatedGame = Game::find($game->id);
        $this->assertEquals(2, $updatedGame->home_team_score);
        $this->assertEquals(3, $updatedGame->away_team_score);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_completes_games_and_updates_standings()
    {
        $games = $this->createScheduledGames(1);
        $game = $games[0];
        
        $this->mockGameSimulationService
            ->shouldReceive('initializeGame')
            ->once()
            ->andReturnUsing(function (Game $game) {
                $game->status = 'in_progress';
                $game->save();
                return null;
            });
            
        $this->mockGameSimulationService
            ->shouldReceive('endGame')
            ->once()
            ->andReturnUsing(function (Game $game) {
                $game->home_team_score = 95;  // Home team loses
                $game->away_team_score = 105; // Away team wins
                $game->status = 'completed';
                $game->save();
                return null;
            });
        
        $this->realTimeSimulationService->startSimulation($games);
        
        $this->realTimeSimulationService->stopSimulation();
        
        $updatedGame = Game::find($game->id);
        $this->assertEquals('completed', $updatedGame->status);
        $this->assertEquals(95, $updatedGame->home_team_score);
        $this->assertEquals(105, $updatedGame->away_team_score);
        
        $homeTeamSeason = TeamSeason::where('team_id', $game->home_team_id)
            ->where('season_id', $this->season->id)
            ->first();
            
        $awayTeamSeason = TeamSeason::where('team_id', $game->away_team_id)
            ->where('season_id', $this->season->id)
            ->first();
        
        $this->assertEquals(1, $homeTeamSeason->games_played);
        $this->assertEquals(0, $homeTeamSeason->wins);
        $this->assertEquals(1, $homeTeamSeason->losses);
        $this->assertEquals(95, $homeTeamSeason->points_for);
        $this->assertEquals(105, $homeTeamSeason->points_against);
        
        $this->assertEquals(1, $awayTeamSeason->games_played);
        $this->assertEquals(1, $awayTeamSeason->wins);
        $this->assertEquals(0, $awayTeamSeason->losses);
        $this->assertEquals(105, $awayTeamSeason->points_for);
        $this->assertEquals(95, $awayTeamSeason->points_against);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_scheduled_games()
    {
        $date = Carbon::tomorrow();
        $games = $this->realTimeSimulationService->createScheduledGames($this->season, $date, 3);
        
        $this->assertInstanceOf(Collection::class, $games);
        $this->assertCount(3, $games);
        
        foreach ($games as $game) {
            $this->assertEquals($this->season->id, $game->season_id);
            $this->assertEquals('scheduled', $game->status);
            $this->assertEquals(0, $game->home_team_score);
            $this->assertEquals(0, $game->away_team_score);
            $this->assertEquals($date->toDateTimeString(), $game->scheduled_at->toDateTimeString());
            
            $this->assertTrue($this->teams->contains('id', $game->home_team_id));
            $this->assertTrue($this->teams->contains('id', $game->away_team_id));
            $this->assertNotEquals($game->home_team_id, $game->away_team_id);
        }
        
        $this->assertCount(3, Game::all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_when_not_enough_teams_for_creating_games()
    {
        Team::whereNotIn('id', [$this->teams[0]->id, $this->teams[1]->id])->delete();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not enough teams to create 2 games. Need at least 4 teams.');
        
        $date = Carbon::tomorrow();
        $this->realTimeSimulationService->createScheduledGames($this->season, $date, 2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_stop_simulation()
    {
        $games = $this->createScheduledGames(2);
        
        $this->mockGameSimulationService
            ->shouldReceive('initializeGame')
            ->times(2)
            ->andReturnUsing(function (Game $game) {
                $game->status = 'in_progress';
                $game->save();
                return null;
            });
            
        $this->mockGameSimulationService
            ->shouldReceive('endGame')
            ->times(2)
            ->andReturnUsing(function (Game $game) {
                $game->status = 'completed';
                $game->save();
                return null;
            });
        
        $this->realTimeSimulationService->startSimulation($games);
        
        $state = $this->realTimeSimulationService->getSimulationState();
        $this->assertTrue($state['is_active']);
        
        $this->realTimeSimulationService->stopSimulation();
        
        $newState = $this->realTimeSimulationService->getSimulationState();
        $this->assertFalse($newState['is_active']);
        $this->assertEmpty($newState['active_games']);
        
        foreach ($games as $game) {
            $this->assertEquals('completed', Game::find($game->id)->status);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_when_starting_simulation_with_no_valid_games()
    {
        $completedGameId = DB::table('games')->insertGetId([
            'season_id' => $this->season->id,
            'home_team_id' => $this->teams[0]->id,
            'away_team_id' => $this->teams[1]->id,
            'status' => 'completed',
            'scheduled_at' => Carbon::now(),
            'home_team_score' => 0,
            'away_team_score' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $completedGame = Game::find($completedGameId);
        $games = new Collection([$completedGame]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No valid games to simulate');
        
        $this->realTimeSimulationService->startSimulation($games);
    }
} 