<?php

namespace App\Http\Controllers;

use App\Http\Requests\Game\IndexGamesRequest;
use App\Http\Requests\Game\GetGameEventsRequest;
use App\Http\Requests\Game\ScheduleNextWeekRequest;
use App\Http\Resources\GameResource;
use App\Http\Resources\GameEventResource;
use App\Http\Resources\GameStatisticsResource;
use App\Services\GameService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    protected $gameService;

    public function __construct(GameService $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * Display a listing of games.
     * 
     * @param IndexGamesRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(IndexGamesRequest $request): AnonymousResourceCollection
    {
        $games = $this->gameService->getGames($request->all());
        return GameResource::collection($games);
    }

    /**
     * Display the specified game.
     * 
     * @param int $id
     * @return GameResource|JsonResponse
     */
    public function show(int $id)
    {
        $game = $this->gameService->getGameById($id);
        
        if (!$game) {
            return response()->json(['message' => 'Game not found'], 404);
        }
        
        return new GameResource($game);
    }

    /**
     * Get the latest events for a game.
     * 
     * @param int $id
     * @param GetGameEventsRequest $request
     * @return AnonymousResourceCollection
     */
    public function getEvents(int $id, GetGameEventsRequest $request): AnonymousResourceCollection
    {
        $limit = $request->input('limit', 20);
        $sinceId = $request->input('since_id', 0);
        
        $events = $this->gameService->getGameEvents($id, $limit, $sinceId);
        return GameEventResource::collection($events);
    }

    /**
     * Get player statistics for a game.
     * 
     * @param int $id
     * @return JsonResource
     */
    public function getStatistics(int $id): JsonResource
    {
        $game = $this->gameService->getGameStatistics($id);
        return new GameStatisticsResource($game);
    }

    /**
     * Schedule new games for the next week, avoiding recent matchups
     * 
     * @param ScheduleNextWeekRequest $request
     * @return JsonResponse
     */
    public function scheduleNextWeek(ScheduleNextWeekRequest $request): JsonResponse
    {
        try {
            $result = $this->gameService->scheduleNextWeekGames($request->input('played_matchups', []));
            
            return response()->json([
                'success' => true,
                'message' => count($result['games']) . ' games scheduled for next week',
                'games' => GameResource::collection(collect($result['games']))
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to schedule next week games: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
} 