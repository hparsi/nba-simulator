<?php

namespace App\Providers;

use App\Repositories\Eloquent\GameRepository;
use App\Repositories\Eloquent\SeasonRepository;
use App\Repositories\Eloquent\TeamRepository;
use App\Repositories\Eloquent\TeamSeasonRepository;
use App\Repositories\Interfaces\GameRepositoryInterface;
use App\Repositories\Interfaces\SeasonRepositoryInterface;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use App\Repositories\Interfaces\TeamSeasonRepositoryInterface;
use App\Services\CacheService;
use App\Services\RealTimeSimulationService;
use App\Services\RealTimeSimulationServiceCompat;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(GameRepositoryInterface::class, GameRepository::class);
        $this->app->bind(TeamRepositoryInterface::class, TeamRepository::class);
        $this->app->bind(SeasonRepositoryInterface::class, SeasonRepository::class);
        $this->app->bind(TeamSeasonRepositoryInterface::class, TeamSeasonRepository::class);
        
        $this->app->singleton(CacheService::class, function ($app) {
            return new CacheService();
        });
        
        $this->app->singleton(RealTimeSimulationService::class, function ($app) {
            return new RealTimeSimulationService(
                $app->make(\App\Services\GameSimulationService::class),
                $app->make(GameRepositoryInterface::class),
                $app->make(TeamRepositoryInterface::class),
                $app->make(TeamSeasonRepositoryInterface::class),
                $app->make(CacheService::class)
            );
        });
        
        $this->app->singleton(RealTimeSimulationServiceCompat::class, function ($app) {
            return new RealTimeSimulationServiceCompat(
                $app->make(\App\Services\GameSimulationService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
} 