<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Game;

class GameStatisticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        /** @var Game $this */
        
        $playerStats = $this->playerStatistics->load('player');
        
        $homeTeamStats = $playerStats->filter(function ($stat) {
            return $stat->player->team_id === $this->home_team_id;
        });
        
        $awayTeamStats = $playerStats->filter(function ($stat) {
            return $stat->player->team_id === $this->away_team_id;
        });
        
        $homeTeamPlayers = $homeTeamStats->map(function ($stat) {
            return [
                'id' => $stat->player->id,
                'name' => $stat->player->first_name . ' ' . $stat->player->last_name,
                'points' => $stat->points,
                'assists' => $stat->assists,
                'field_goals_attempted' => $stat->field_goals_attempted,
                'field_goals_made' => $stat->field_goals_made,
                'three_pointers_attempted' => $stat->three_pointers_attempted,
                'three_pointers_made' => $stat->three_pointers_made
            ];
        })->sortByDesc('points')->values()->all();
        
        $awayTeamPlayers = $awayTeamStats->map(function ($stat) {
            return [
                'id' => $stat->player->id,
                'name' => $stat->player->first_name . ' ' . $stat->player->last_name,
                'points' => $stat->points,
                'assists' => $stat->assists,
                'field_goals_attempted' => $stat->field_goals_attempted,
                'field_goals_made' => $stat->field_goals_made,
                'three_pointers_attempted' => $stat->three_pointers_attempted,
                'three_pointers_made' => $stat->three_pointers_made
            ];
        })->sortByDesc('points')->values()->all();
        
        $homeTeamTotals = $this->calculateTeamTotals($homeTeamStats);
        $awayTeamTotals = $this->calculateTeamTotals($awayTeamStats);
        
        return [
            'home_team' => [
                'players' => $homeTeamPlayers,
                'totals' => $homeTeamTotals
            ],
            'away_team' => [
                'players' => $awayTeamPlayers,
                'totals' => $awayTeamTotals
            ]
        ];
    }
    
    /**
     * Calculate team totals from player statistics
     *
     * @param \Illuminate\Support\Collection $teamStats
     * @return array
     */
    private function calculateTeamTotals($teamStats): array
    {
        $totals = [
            'points' => 0,
            'assists' => 0,
            'field_goals_attempted' => 0,
            'field_goals_made' => 0,
            'three_pointers_attempted' => 0,
            'three_pointers_made' => 0
        ];
        
        foreach ($teamStats as $stat) {
            $totals['points'] += $stat->points;
            $totals['assists'] += $stat->assists;
            $totals['field_goals_attempted'] += $stat->field_goals_attempted;
            $totals['field_goals_made'] += $stat->field_goals_made;
            $totals['three_pointers_attempted'] += $stat->three_pointers_attempted;
            $totals['three_pointers_made'] += $stat->three_pointers_made;
        }
        
        return $totals;
    }
} 