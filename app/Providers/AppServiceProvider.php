<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Repositories\Interfaces\GameRepositoryInterface;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use App\Repositories\Interfaces\TeamSeasonRepositoryInterface;
use App\Services\RealTimeSimulationService;
use App\Services\GameSimulationService;
use App\Services\CacheService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the RepositoryServiceProvider
        $this->app->register(RepositoryServiceProvider::class);
        
        $this->app->singleton(RealTimeSimulationService::class, function ($app) {
            return new RealTimeSimulationService(
                $app->make(GameSimulationService::class),
                $app->make(GameRepositoryInterface::class),
                $app->make(TeamRepositoryInterface::class),
                $app->make(TeamSeasonRepositoryInterface::class),
                $app->make(CacheService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
