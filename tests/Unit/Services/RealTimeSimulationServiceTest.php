<?php

namespace Tests\Unit\Services;

use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Repositories\Interfaces\GameRepositoryInterface;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use App\Repositories\Interfaces\TeamSeasonRepositoryInterface;
use App\Services\CacheService;
use App\Services\GameSimulationService;
use App\Services\RealTimeSimulationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class RealTimeSimulationServiceTest extends TestCase
{
    private $gameSimulationService;
    private $gameRepository;
    private $teamRepository;
    private $teamSeasonRepository;
    private $cacheService;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->gameSimulationService = Mockery::mock(GameSimulationService::class);
        $this->gameRepository = Mockery::mock(GameRepositoryInterface::class);
        $this->teamRepository = Mockery::mock(TeamRepositoryInterface::class);
        $this->teamSeasonRepository = Mockery::mock(TeamSeasonRepositoryInterface::class);
        $this->cacheService = Mockery::mock(CacheService::class);
        
        $this->service = new RealTimeSimulationService(
            $this->gameSimulationService,
            $this->gameRepository,
            $this->teamRepository,
            $this->teamSeasonRepository,
            $this->cacheService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a better mock for Game model that handles status checks properly
     */
    private function createBetterGameMock($attributes = [])
    {
        $game = Mockery::mock(Game::class);
        
        foreach ($attributes as $key => $value) {
            $game->allows()->offsetGet($key)->andReturn($value);
            $game->allows()->offsetExists($key)->andReturn(true);
            $game->allows()->__get($key)->andReturn($value);
        }
        
        $game->allows('getAttribute')->with('status')->andReturn($attributes['status'] ?? null);
        $game->allows('getAttribute')->andReturn(null);
        
        $game->allows('__set')->andReturnSelf();
        $game->allows('setAttribute')->andReturnSelf();
        
        $game->allows('getKey')->andReturn($attributes['id'] ?? null);
        
        $game->allows('save')->andReturn(true);
        
        return $game;
    }

    /**
     * Create a simple season model mock
     */
    private function createSeasonMock($attributes = [])
    {
        $season = Mockery::mock(Season::class);
        
        foreach ($attributes as $key => $value) {
            $season->{$key} = $value;
        }
        
        $season->shouldReceive('getAttribute')->andReturnUsing(function($key) use ($season) {
            return $season->{$key} ?? null;
        });
        
        $season->shouldReceive('setAttribute')->andReturnUsing(function($key, $value) use ($season) {
            $season->{$key} = $value;
            return $season;
        });
        
        return $season;
    }

    /**
     * Create a simple team model mock
     */
    private function createTeamMock($attributes = [])
    {
        $team = Mockery::mock(Team::class);
        
        foreach ($attributes as $key => $value) {
            $team->{$key} = $value;
        }
        
        $team->shouldReceive('getAttribute')->andReturnUsing(function($key) use ($team) {
            return $team->{$key} ?? null;
        });
        
        $team->shouldReceive('setAttribute')->andReturnUsing(function($key, $value) use ($team) {
            $team->{$key} = $value;
            return $team;
        });
        
        return $team;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_start_a_simulation()
    {
        $game = $this->createBetterGameMock([
            'id' => 1,
            'status' => 'scheduled',
            'home_team_score' => 0,
            'away_team_score' => 0,
            'home_team_id' => 1, 
            'away_team_id' => 2,
            'season_id' => 1
        ]);
        
        $game->allows()->offsetGet('status')->andReturn('scheduled');
        $game->allows()->__get('status')->andReturn('scheduled');
        
        $games = new Collection([$game]);
        
        $this->gameSimulationService->shouldReceive('initializeGame')
            ->once()
            ->with(Mockery::on(function($arg) {
                return $arg instanceof Game;
            }));
        
        $this->gameRepository->shouldReceive('updateStatus')
            ->once()
            ->with(Mockery::on(function($arg) {
                return $arg instanceof Game;
            }), 'in_progress');
        
        $this->cacheService->shouldReceive('put')
            ->once()
            ->withAnyArgs();
        
        $this->service->startSimulation($games);
        
        $this->assertTrue(true, 'The simulation started successfully');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_when_no_valid_games()
    {
        $game = $this->createBetterGameMock([
            'id' => 1,
            'status' => 'completed'
        ]);
        
        $games = new Collection([$game]);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No valid games to simulate');
        
        $this->service->startSimulation($games);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_process_update()
    {
        $game = $this->createBetterGameMock([
            'id' => 1,
            'status' => 'in_progress',
            'home_team_score' => 10,
            'away_team_score' => 8
        ]);
        
        $state = [
            'is_active' => true,
            'active_games' => [1],
            'completed_games' => [],
            'game_progress' => [
                1 => [
                    'current_minute' => 5,
                    'total_minutes' => 48,
                    'home_score' => 10,
                    'away_score' => 8
                ]
            ]
        ];
        
        $this->cacheService->shouldReceive('get')
            ->once()
            ->with('simulation_state', Mockery::any())
            ->andReturn($state);
        
        $this->gameRepository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($game);
        
        $this->gameSimulationService->shouldReceive('simulateMinute')
            ->once()
            ->with(Mockery::type(Game::class))
            ->andReturn([
                'minute' => 6,
                'events' => [
                    ['type' => 'shot', 'team' => 'home', 'points' => 2, 'success' => true]
                ]
            ]);
        
        $this->cacheService->shouldReceive('put')
            ->once()
            ->with('simulation_state', Mockery::type('array'));
        
        $result = $this->service->processUpdate();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('active_games', $result);
        $this->assertArrayHasKey('completed_games', $result);
        $this->assertArrayHasKey('updates', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_complete_games_during_update()
    {
        $game = $this->createBetterGameMock([
            'id' => 1,
            'status' => 'in_progress',
            'home_team_score' => 100,
            'away_team_score' => 98,
            'home_team_id' => 1,
            'away_team_id' => 2,
            'season_id' => 1
        ]);
        
        $state = [
            'is_active' => true,
            'active_games' => [1],
            'completed_games' => [],
            'game_progress' => [
                1 => [
                    'current_minute' => 47, // Last minute - completion threshold is 48-1 = 47
                    'total_minutes' => 48,
                    'home_score' => 100,
                    'away_score' => 98
                ]
            ]
        ];
        
        $this->cacheService->shouldReceive('get')
            ->once()
            ->with('simulation_state', Mockery::any())
            ->andReturn($state);
        
        $this->gameRepository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($game);
        
        $this->gameSimulationService->shouldReceive('simulateMinute')
            ->once()
            ->with(Mockery::type(Game::class))
            ->andReturn([
                'minute' => 48,
                'events' => [
                    ['type' => 'end_of_game', 'description' => 'Game complete']
                ]
            ]);
        
        $this->gameSimulationService->shouldReceive('endGame')
            ->once()
            ->with(Mockery::type(Game::class));
        
        $this->gameRepository->shouldReceive('completeGame')
            ->once()
            ->with(Mockery::type(Game::class));
        
        $this->teamSeasonRepository->shouldReceive('updateStatsForCompletedGame')
            ->once()
            ->with(Mockery::type(Game::class));
        
        $this->cacheService->shouldReceive('put')
            ->once()
            ->with('simulation_state', Mockery::type('array'));
        
        $result = $this->service->processUpdate();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('active_games', $result);
        $this->assertArrayHasKey('completed_games', $result);
        $this->assertArrayHasKey('updates', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_stop_simulation()
    {
        $game = $this->createBetterGameMock([
            'id' => 1,
            'status' => 'in_progress',
            'home_team_score' => 80,
            'away_team_score' => 75,
            'home_team_id' => 1,
            'away_team_id' => 2,
            'season_id' => 1
        ]);
        
        $state = [
            'is_active' => true,
            'active_games' => [1],
            'completed_games' => [],
            'game_progress' => [
                1 => [
                    'current_minute' => 36,
                    'total_minutes' => 48,
                    'home_score' => 80,
                    'away_score' => 75
                ]
            ]
        ];
        
        $this->cacheService->shouldReceive('get')
            ->once()
            ->with('simulation_state', Mockery::any())
            ->andReturn($state);
        
        $this->gameRepository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($game);
        
        $this->gameSimulationService->shouldReceive('endGame')
            ->once()
            ->with(Mockery::type(Game::class));
        
        $this->gameRepository->shouldReceive('completeGame')
            ->once()
            ->with(Mockery::type(Game::class));
        
        $this->teamSeasonRepository->shouldReceive('updateStatsForCompletedGame')
            ->once()
            ->with(Mockery::type(Game::class));
        
        $this->cacheService->shouldReceive('put')
            ->once()
            ->with('simulation_state', Mockery::type('array'));
        
        $this->service->stopSimulation();
        
        $this->assertTrue(true, 'The simulation stopped successfully');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_scheduled_games()
    {
        $season = Mockery::mock(Season::class);
        $season->allows('__get')->with('id')->andReturn(1);
        $season->allows('getAttribute')->with('id')->andReturn(1);
        
        $teams = new Collection();
        for ($i = 1; $i <= 10; $i++) {
            $team = Mockery::mock(Team::class);
            $team->allows('__get')->with('id')->andReturn($i);
            $team->allows('getAttribute')->with('id')->andReturn($i);
            $team->allows('getAttribute')->andReturn(null);
            $teams->push($team);
        }
        
        $scheduledAt = Carbon::tomorrow();
        
        $this->teamRepository->shouldReceive('getAllActive')
            ->andReturn($teams);
        
        for ($i = 0; $i < 3; $i++) {
            $game = $this->createBetterGameMock([
                'id' => 100 + $i,
                'status' => 'scheduled',
                'home_team_id' => ($i * 2) + 1,
                'away_team_id' => ($i * 2) + 2,
                'season_id' => 1
            ]);
            
            $this->gameRepository->shouldReceive('createScheduledGame')
                ->with(
                    Mockery::type(Season::class), 
                    Mockery::type('int'), 
                    Mockery::type('int'), 
                    Mockery::type(Carbon::class)
                )
                ->andReturn($game);
        }
        
        $games = $this->service->createScheduledGames($season, $scheduledAt, 3);
        
        $this->assertCount(3, $games);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_when_not_enough_teams()
    {
        $season = Mockery::mock(Season::class);
        $season->allows('__get')->with('id')->andReturn(1);
        
        $teams = new Collection();
        for ($i = 1; $i <= 3; $i++) {
            $team = Mockery::mock(Team::class);
            $team->allows('__get')->with('id')->andReturn($i);
            $team->allows('getAttribute')->with('id')->andReturn($i);
            $team->allows('getAttribute')->andReturn(null);
            $teams->push($team);
        }
        
        $scheduledAt = Carbon::tomorrow();
        
        $this->teamRepository->shouldReceive('getAllActive')
            ->once()
            ->andReturn($teams);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Not enough teams to create 3 games. Need at least 6 teams.');
        
        $this->service->createScheduledGames($season, $scheduledAt, 3);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_games_before_simulation()
    {
        $games = new Collection();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No valid games to simulate');
        
        $this->service->startSimulation($games);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_correctly_identifies_simulation_state()
    {
        $this->cacheService->shouldReceive('get')
            ->once()
            ->with('simulation_state', Mockery::type('array'))
            ->andReturn([
                'is_active' => true,
                'active_games' => [1, 2, 3],
                'completed_games' => [4, 5],
                'game_progress' => []
            ]);
        
        $state = $this->service->getSimulationState();
        
        $this->assertTrue($state['is_active']);
        $this->assertEquals([1, 2, 3], $state['active_games']);
        $this->assertEquals([4, 5], $state['completed_games']);
    }
} 