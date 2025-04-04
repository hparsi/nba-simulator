<?php

namespace App\Console;

use App\Http\Controllers\GameSimulationController;
use App\Services\RealTimeSimulationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            $controller = app()->make(GameSimulationController::class);
            $request = new \Illuminate\Http\Request();
            $controller->processUpdate($request);
        })->everyFiveSeconds()->when(function () {
            $service = app()->make(RealTimeSimulationService::class);
            $state = $service->getSimulationState();
            return $state['is_active'] === true;
        });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 