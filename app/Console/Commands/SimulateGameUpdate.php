<?php

namespace App\Console\Commands;

use App\Http\Controllers\GameSimulationController;
use App\Models\Game;
use App\Services\RealTimeSimulationService;
use Illuminate\Console\Command;

class SimulateGameUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:simulate-game-update 
                            {--start : Start a new simulation} 
                            {--game_ids=* : IDs of games to simulate}
                            {--update : Process a single update}
                            {--status : Show the current simulation status}
                            {--stop : Stop the current simulation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage real-time game simulations';

    /**
     * Execute the console command.
     */
    public function handle(
        GameSimulationController $simulationController,
        RealTimeSimulationService $simulationService
    ): int
    {
        if ($this->option('start')) {
            return $this->startSimulation($simulationController);
        }

        if ($this->option('update')) {
            return $this->processUpdate($simulationController);
        }

        if ($this->option('status')) {
            return $this->showStatus($simulationService);
        }

        if ($this->option('stop')) {
            return $this->stopSimulation($simulationController);
        }

        $this->info('Please specify an action (--start, --update, --status, or --stop)');
        return 1;
    }

    /**
     * Start a new simulation
     */
    private function startSimulation(GameSimulationController $controller): int
    {
        $gameIds = $this->option('game_ids');
        
        if (empty($gameIds)) {
            $scheduledGames = Game::where('status', 'scheduled')->get();
            
            if ($scheduledGames->isEmpty()) {
                $this->error('No scheduled games found');
                return 1;
            }
            
            $gameIds = $scheduledGames->pluck('id')->toArray();
        }
        
        $this->info('Starting simulation for games: ' . implode(', ', $gameIds));
        
        try {
            $request = new \Illuminate\Http\Request();
            $request->merge(['game_ids' => $gameIds]);
            
            $response = $controller->startSimulation($request);
            $content = json_decode($response->getContent(), true);
            
            if (isset($content['success']) && $content['success']) {
                $this->info($content['message']);
                return 0;
            } else {
                $this->error($content['message'] ?? 'Unknown error starting simulation');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Process a single update
     */
    private function processUpdate(GameSimulationController $controller): int
    {
        $this->info('Processing simulation update...');
        
        try {
            $request = new \Illuminate\Http\Request();
            $response = $controller->processUpdate($request);
            $content = json_decode($response->getContent(), true);
            
            if (isset($content['success']) && $content['success']) {
                $this->info('Update processed successfully');
                $this->info('Active games: ' . $content['active_games']);
                $this->info('Completed games: ' . $content['completed_games']);
                
                // Display score updates
                foreach ($content['updates'] as $gameId => $update) {
                    $initialScore = $update['initialScore'] ?? null;
                    $finalScore = $update['finalScore'] ?? null;
                    
                    if ($initialScore && $finalScore) {
                        $this->info("Game {$gameId}: {$initialScore['home']}-{$initialScore['away']} → {$finalScore['home']}-{$finalScore['away']}");
                    }
                }
                
                return 0;
            } else {
                $this->error($content['message'] ?? 'Unknown error processing update');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show the current simulation status
     */
    private function showStatus(RealTimeSimulationService $service): int
    {
        try {
            $state = $service->getSimulationState();
            
            $this->info('Simulation active: ' . ($state['is_active'] ? 'Yes' : 'No'));
            $this->info('Active games: ' . count($state['active_games']));
            $this->info('Completed games: ' . count($state['completed_games']));
            
            if (!empty($state['game_progress'])) {
                $this->info("\nGame Progress:");
                foreach ($state['game_progress'] as $gameId => $progress) {
                    $this->info("Game {$gameId}: {$progress['current_minute']}/{$progress['total_minutes']} minutes played | Score: {$progress['home_score']}-{$progress['away_score']}");
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Stop the current simulation
     */
    private function stopSimulation(GameSimulationController $controller): int
    {
        $this->info('Stopping simulation...');
        
        try {
            $response = $controller->stopSimulation();
            $content = json_decode($response->getContent(), true);
            
            if (isset($content['success']) && $content['success']) {
                $this->info($content['message']);
                return 0;
            } else {
                $this->error($content['message'] ?? 'Unknown error stopping simulation');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
