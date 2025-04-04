<?php

namespace App\Http\Controllers;

use App\Models\Game;

use App\Http\Requests\GameSimulation\StartSimulationRequest;
use App\Http\Requests\GameSimulation\SimulateGameRequest;
use App\Http\Resources\GameResource;
use App\Http\Resources\SimulationStateResource;
use App\Http\Resources\SimulationUpdateResource;
use App\Services\GameSimulationService;
use App\Services\RealTimeSimulationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GameSimulationController extends Controller
{
    protected GameSimulationService $gameSimulationService;
    protected RealTimeSimulationService $realTimeService;

    public function __construct(
        GameSimulationService $gameSimulationService,
        RealTimeSimulationService $realTimeService
    ) {
        $this->gameSimulationService = $gameSimulationService;
        $this->realTimeService = $realTimeService;
    }

    /**
     * Start a simulation for multiple games
     * 
     * @param StartSimulationRequest $request
     * @return JsonResponse
     */
    public function startSimulation(StartSimulationRequest $request): JsonResponse
    {
        try {
            $gameIds = $request->input('game_ids');
            
            $games = $this->realTimeService->getScheduledGames($gameIds);
            
            $this->realTimeService->startSimulation($games);
            
            $state = $this->realTimeService->getSimulationState();

            return response()->json([
                'success' => true,
                'message' => 'Simulation started for ' . $games->count() . ' games',
                'game_ids' => $games->pluck('id')->toArray(),
                'simulation_state' => new SimulationStateResource($state)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start simulation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process the next update for all active games
     * 
     * @return JsonResponse
     */
    public function processUpdate(Request $request): JsonResponse
    {
        try {
            $state = $this->realTimeService->getSimulationState();
            
            $isActive = !empty($state['active_games'] ?? []);
            if (!$isActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active simulation in progress. Please start a simulation first.'
                ], 400);
            }
            
            $results = $this->realTimeService->processUpdate();

            return response()->json([
                'success' => true,
                'data' => new SimulationUpdateResource($results)
            ]);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'No active simulation in progress') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active simulation in progress. Please start a simulation first.'
                ], 400);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the current state of the simulation
     * 
     * @return JsonResponse
     */
    public function getSimulationState(): JsonResponse
    {
        try {
            $state = $this->realTimeService->getSimulationState();
            
            return response()->json([
                'success' => true,
                'data' => new SimulationStateResource($state)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get simulation state: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stop the current simulation
     * 
     * @return JsonResponse
     */
    public function stopSimulation(): JsonResponse
    {
        try {
            $this->realTimeService->stopSimulation();

            return response()->json([
                'success' => true,
                'message' => 'Simulation stopped'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop simulation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate an entire game at once (non-real-time)
     * 
     * @param int $gameId
     * @return JsonResponse
     */
    public function simulateGame(int $gameId): JsonResponse
    {
        try {
            $game = $this->gameSimulationService->getScheduledGameById($gameId);
            
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game is not found or not in scheduled status'
                ], 400);
            }
            
            $this->gameSimulationService->simulateGame($game);
            
            return response()->json([
                'success' => true,
                'message' => 'Game simulation completed',
                'game' => new GameResource($game->refresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate game: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to save simulation state
     */
    protected function saveSimulationState(array $state): void
    {
        Cache::put('simulation_state', $state, now()->addHours(1));

        DB::table('simulation_states')->updateOrInsert(
            ['id' => 1],
            ['state' => json_encode($state), 'updated_at' => now()]
        );
    }

    /**
     * Helper method to get persisted simulation state
     */
    protected function getPersistedSimulationState(): ?array
    {
        return Cache::get('simulation_state');
    }
} 