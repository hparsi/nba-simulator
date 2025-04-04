<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Season;
use App\Repositories\Interfaces\GameRepositoryInterface;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use App\Repositories\Interfaces\TeamSeasonRepositoryInterface;
use App\Repositories\Eloquent\GameRepository;
use App\Repositories\Eloquent\TeamRepository;
use App\Repositories\Eloquent\TeamSeasonRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RealTimeSimulationService
{
    private string $cacheKey = 'simulation_state';
    private int $gameTotalMinutes = 48;
    
    private GameRepositoryInterface $gameRepository;
    private TeamRepositoryInterface $teamRepository;
    private TeamSeasonRepositoryInterface $teamSeasonRepository;
    private CacheService $cacheService;

    /**
     * Constructor that works with both legacy and new dependency injection
     */
    public function __construct(
        private GameSimulationService $gameSimulationService,
        ?GameRepositoryInterface $gameRepository = null,
        ?TeamRepositoryInterface $teamRepository = null,
        ?TeamSeasonRepositoryInterface $teamSeasonRepository = null,
        ?CacheService $cacheService = null
    ) {
        $this->gameRepository = $gameRepository ?? new GameRepository();
        $this->teamRepository = $teamRepository ?? new TeamRepository();
        $this->teamSeasonRepository = $teamSeasonRepository ?? new TeamSeasonRepository();
        $this->cacheService = $cacheService ?? new CacheService();
    }

    /**
     * Start a real-time simulation with the provided games
     * 
     * @param Collection $games Collection of games to simulate
     * @throws Exception If no valid games to simulate
     */
    public function startSimulation(Collection $games): void
    {
        $validGames = $games->filter(fn($game) => $game->status === 'scheduled');
        
        if ($validGames->isEmpty()) {
            throw new Exception('No valid games to simulate');
        }
        
        $state = [
            'is_active' => true,
            'active_games' => [],
            'completed_games' => [],
            'game_progress' => [],
        ];
        
        foreach ($validGames as $game) {
            $this->gameSimulationService->initializeGame($game);
            $this->gameRepository->updateStatus($game, 'in_progress');
            
            $state['active_games'][] = $game->id;
            $state['game_progress'][$game->id] = [
                'current_minute' => 0,
                'total_minutes' => $this->gameTotalMinutes,
                'home_score' => $game->home_team_score,
                'away_score' => $game->away_team_score
            ];
        }
        
        $this->putCache($state);
    }

    /**
     * Process the next simulation update for all active games
     */
    public function processUpdate(): array
    {
        $state = $this->getSimulationState();
        
        if (!$state['is_active'] || empty($state['active_games'])) {
            return [
                'active_games' => [],
                'completed_games' => $state['completed_games'] ?? [],
                'updates' => []
            ];
        }
        
        $updates = [];
        $completedGames = [];
        
        foreach ($state['active_games'] as $key => $gameId) {
            $game = $this->gameRepository->findById($gameId);
            
            if (!$game || $game->status !== 'in_progress') {
                continue;
            }
            
            $currentProgress = $state['game_progress'][$gameId];
            $currentMinute = $currentProgress['current_minute'];
            
            $result = $this->gameSimulationService->simulateMinute($game);
            
            $result['minute'] = $currentMinute + 1;
            
            $updates[$gameId] = $result;
            
            $state['game_progress'][$gameId]['current_minute'] = $result['minute'];
            $state['game_progress'][$gameId]['home_score'] = $game->home_team_score;
            $state['game_progress'][$gameId]['away_score'] = $game->away_team_score;
            
            if ($currentMinute >= $this->gameTotalMinutes - 1) {
                $this->completeGame($game);
                $completedGames[] = $gameId;
                
                unset($state['active_games'][$key]);
                $state['completed_games'][] = $gameId;
            }
        }
        
        $state['active_games'] = array_values($state['active_games']);
        
        $this->putCache($state);
        
        return [
            'active_games' => $state['active_games'],
            'completed_games' => $state['completed_games'],
            'updates' => $updates
        ];
    }

    /**
     * Stop the current simulation
     */
    public function stopSimulation(): void
    {
        $state = $this->getSimulationState();
        
        if (!$state['is_active']) {
            return;
        }
        
        foreach ($state['active_games'] as $gameId) {
            $game = $this->gameRepository->findById($gameId);
            
            if ($game && $game->status === 'in_progress') {
                $this->completeGame($game);
                $state['completed_games'][] = $gameId;
            }
        }
        
        $state['is_active'] = false;
        $state['active_games'] = [];
        
        $this->putCache($state);
    }

    /**
     * Get the current simulation state
     */
    public function getSimulationState(): array
    {
        $defaultState = [
            'is_active' => false,
            'active_games' => [],
            'completed_games' => [],
            'game_progress' => [],
        ];
        
        return $this->getCache($defaultState);
    }

    /**
     * Create scheduled games for a season
     */
    public function createScheduledGames(Season $season, Carbon $scheduledAt, int $count = 5): Collection
    {
        if ($this->teamRepository) {
            $teams = $this->teamRepository->getAllActive();
        } else {
            $teams = \App\Models\Team::query()->get();
        }
        
        if ($teams->count() < $count * 2) {
            throw new Exception("Not enough teams to create $count games. Need at least " . ($count * 2) . " teams.");
        }
        
        $randomTeams = $teams->shuffle()->take($count * 2);
        
        $games = collect();
        
        for ($i = 0; $i < $count; $i++) {
            $homeTeamId = $randomTeams[$i * 2]->id;
            $awayTeamId = $randomTeams[$i * 2 + 1]->id;
            
            if ($this->gameRepository) {
                $game = $this->gameRepository->createScheduledGame(
                    $season,
                    $homeTeamId,
                    $awayTeamId,
                    $scheduledAt
                );
            } else {
                $game = Game::create([
                    'season_id' => $season->id,
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                    'status' => 'scheduled',
                    'home_team_score' => 0,
                    'away_team_score' => 0,
                    'scheduled_at' => $scheduledAt
                ]);
            }
            
            $games->push($game);
        }
        
        return $games;
    }

    /**
     * Mark a game as complete and update team statistics
     */
    private function completeGame(Game $game): void
    {
        $this->gameSimulationService->endGame($game);
        
        $this->gameRepository->completeGame($game);
        
        $this->teamSeasonRepository->updateStatsForCompletedGame($game);
    }
    
    /**
     * Helper to abstract cache access - works with either CacheService or Laravel Cache
     */
    private function putCache(array $data): void
    {
        try {
            $this->cacheService->put($this->cacheKey, $data);
        } catch (\Throwable $e) {
            Cache::put($this->cacheKey, $data, now()->addHours(1));
        }
    }
    
    /**
     * Helper to get cache data - works with either CacheService or Laravel Cache
     */
    private function getCache(array $default): array
    {
        try {
            return $this->cacheService->get($this->cacheKey, $default);
        } catch (\Throwable $e) {
            return Cache::get($this->cacheKey, $default);
        }
    }

    /**
     * Get scheduled games by IDs
     * 
     * @param array $gameIds Array of game IDs to filter
     * @return \Illuminate\Support\Collection Collection of scheduled games
     */
    public function getScheduledGames(array $gameIds): \Illuminate\Support\Collection
    {
        return $this->gameRepository->getAllGames([
            'ids' => $gameIds,
            'status' => 'scheduled'
        ]);
    }
} 