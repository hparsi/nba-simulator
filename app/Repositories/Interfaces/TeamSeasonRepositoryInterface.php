<?php

namespace App\Repositories\Interfaces;

use App\Models\Game;
use App\Models\TeamSeason;

interface TeamSeasonRepositoryInterface
{
    public function updateGameStats(int $teamId, int $seasonId, bool $isWinner, int $pointsFor, int $pointsAgainst): bool;
    public function findByTeamAndSeason(int $teamId, int $seasonId): ?TeamSeason;
    public function updateStatsForCompletedGame(Game $game): void;
} 