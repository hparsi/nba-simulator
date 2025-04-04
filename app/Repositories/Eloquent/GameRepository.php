<?php

namespace App\Repositories\Eloquent;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Season;
use App\Models\Team;
use App\Repositories\Interfaces\GameRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GameRepository implements GameRepositoryInterface
{
    public function findById(int $id, array $relations = [])
    {
        return Game::with($relations)->find($id);
    }

    public function update(Game $game, array $attributes): bool
    {
        return $game->update($attributes);
    }

    public function getScheduledGames(): Collection
    {
        return Game::where('status', 'scheduled')->get();
    }

    public function updateStatus(Game $game, string $status): bool
    {
        $game->status = $status;
        return $game->save();
    }

    public function updateScore(Game $game, int $homeScore, int $awayScore): bool
    {
        $game->home_team_score = $homeScore;
        $game->away_team_score = $awayScore;
        return $game->save();
    }

    public function createScheduledGame(Season $season, int $homeTeamId, int $awayTeamId, Carbon $scheduledAt): Game
    {
        return Game::create([
            'season_id' => $season->id,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'status' => 'scheduled',
            'home_team_score' => 0,
            'away_team_score' => 0,
            'scheduled_at' => $scheduledAt
        ]);
    }

    public function completeGame(Game $game): bool
    {
        $game->status = 'completed';
        return $game->save();
    }

    public function getAllGames(array $filters = [], array $relations = [])
    {
        $query = Game::with($relations);
        
        if (isset($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        return $query->get();
    }
    
    public function getGameEvents(int $gameId, int $limit = 20, int $sinceId = 0)
    {
        return GameEvent::where('game_id', $gameId)
            ->when($sinceId > 0, function ($query) use ($sinceId) {
                return $query->where('id', '>', $sinceId);
            })
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->with(['player', 'team'])
            ->get();
    }
    
    public function getGameStatistics(int $id)
    {
        return Game::with([
            'homeTeam', 
            'awayTeam',
            'playerStatistics.player'
        ])->findOrFail($id);
    }
    
    public function find(int $id): ?Game
    {
        return Game::find($id);
    }
    
    public function findCompletedGames(array $gameIds): Collection
    {
        return Game::whereIn('id', $gameIds)
            ->where('status', 'completed')
            ->get();
    }
    
    public function create(array $data): Game
    {
        return Game::create($data);
    }
    
    public function getActiveSeason()
    {
        return Season::where('is_active', true)->first();
    }
    
    public function getAllTeams()
    {
        return Team::all();
    }
    
    public function getExistingGames(int $seasonId)
    {
        return Game::where('season_id', $seasonId)->get();
    }
    
    public function createGame(array $gameData)
    {
        return Game::create($gameData);
    }
} 