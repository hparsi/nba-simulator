<?php

namespace App\Repositories\Eloquent;

use App\Models\Game;
use App\Models\TeamSeason;
use App\Repositories\Interfaces\TeamSeasonRepositoryInterface;

class TeamSeasonRepository implements TeamSeasonRepositoryInterface
{
    public function findByTeamAndSeason(int $teamId, int $seasonId): ?TeamSeason
    {
        return TeamSeason::where('team_id', $teamId)
            ->where('season_id', $seasonId)
            ->first();
    }

    public function updateStatsForCompletedGame(Game $game): void
    {
        $homeTeamSeason = $this->findByTeamAndSeason($game->home_team_id, $game->season_id);
        $awayTeamSeason = $this->findByTeamAndSeason($game->away_team_id, $game->season_id);

        if (!$homeTeamSeason || !$awayTeamSeason) {
            return;
        }

        $homeTeamSeason->games_played += 1;
        $homeTeamSeason->points_for += $game->home_team_score;
        $homeTeamSeason->points_against += $game->away_team_score;

        if ($game->home_team_score > $game->away_team_score) {
            $homeTeamSeason->wins += 1;
        } else {
            $homeTeamSeason->losses += 1;
        }

        $awayTeamSeason->games_played += 1;
        $awayTeamSeason->points_for += $game->away_team_score;
        $awayTeamSeason->points_against += $game->home_team_score;

        if ($game->away_team_score > $game->home_team_score) {
            $awayTeamSeason->wins += 1;
        } else {
            $awayTeamSeason->losses += 1;
        }

        $homeTeamSeason->save();
        $awayTeamSeason->save();
    }

    public function updateGameStats(int $teamId, int $seasonId, bool $isWinner, int $pointsFor, int $pointsAgainst): bool
    {
        $teamSeason = $this->findByTeamAndSeason($teamId, $seasonId);
        
        if (!$teamSeason) {
            $teamSeason = new TeamSeason([
                'team_id' => $teamId,
                'season_id' => $seasonId,
                'wins' => 0,
                'losses' => 0,
                'games_played' => 0,
                'points_for' => 0,
                'points_against' => 0
            ]);
        }
        
        $teamSeason->games_played += 1;
        $teamSeason->points_for += $pointsFor;
        $teamSeason->points_against += $pointsAgainst;
        
        if ($isWinner) {
            $teamSeason->wins += 1;
        } else {
            $teamSeason->losses += 1;
        }
        
        return $teamSeason->save();
    }
} 