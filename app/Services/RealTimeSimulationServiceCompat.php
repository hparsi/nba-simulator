<?php

namespace App\Services;

use App\Repositories\Eloquent\GameRepository;
use App\Repositories\Eloquent\TeamRepository;
use App\Repositories\Eloquent\TeamSeasonRepository;
use Illuminate\Support\Collection;
use App\Models\Game;
use App\Models\Season;
use Carbon\Carbon;

/**
 * Compatibility wrapper for RealTimeSimulationService to work with existing feature tests
 */
class RealTimeSimulationServiceCompat
{
    private RealTimeSimulationService $service;

    public function __construct(GameSimulationService $gameSimulationService)
    {
        $gameRepository = new GameRepository();
        $teamRepository = new TeamRepository();
        $teamSeasonRepository = new TeamSeasonRepository();
        $cacheService = new CacheService();
        
        $this->service = new RealTimeSimulationService(
            $gameSimulationService,
            $gameRepository,
            $teamRepository,
            $teamSeasonRepository,
            $cacheService
        );
    }

    public function startSimulation(Collection $games): void
    {
        $this->service->startSimulation($games);
    }

    public function processUpdate(): array
    {
        return $this->service->processUpdate();
    }

    public function stopSimulation(): void
    {
        $this->service->stopSimulation();
    }

    public function getSimulationState(): array
    {
        return $this->service->getSimulationState();
    }

    public function createScheduledGames(Season $season, Carbon $scheduledAt, int $count = 5): Collection
    {
        return $this->service->createScheduledGames($season, $scheduledAt, $count);
    }
} 