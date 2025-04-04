<?php

namespace App\Repositories\Interfaces;

use App\Models\Game;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface GameRepositoryInterface
{
    public function find(int $id): ?Game;
    public function findCompletedGames(array $gameIds): Collection;
    public function create(array $data): Game;
    public function getAllGames(array $filters = [], array $relations = []);
    public function findById(int $id, array $relations = []);
    public function getGameEvents(int $gameId, int $limit = 20, int $sinceId = 0);
    public function getGameStatistics(int $id);
    public function getActiveSeason();
    public function getAllTeams();
    public function getExistingGames(int $seasonId);
    public function createGame(array $gameData);
    public function update(Game $game, array $attributes): bool;
    public function getScheduledGames(): Collection;
    public function updateStatus(Game $game, string $status): bool;
    public function updateScore(Game $game, int $homeScore, int $awayScore): bool;
    public function createScheduledGame(Season $season, int $homeTeamId, int $awayTeamId, Carbon $scheduledAt): Game;
    public function completeGame(Game $game): bool;
} 