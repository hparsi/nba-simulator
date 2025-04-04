<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'date' => $this->scheduled_at ? $this->scheduled_at->format('Y-m-d') : null,
            'home_team' => [
                'id' => $this->homeTeam->id,
                'name' => $this->homeTeam->name,
                'score' => $this->home_team_score
            ],
            'away_team' => [
                'id' => $this->awayTeam->id,
                'name' => $this->awayTeam->name,
                'score' => $this->away_team_score
            ],
            'current_quarter' => $this->current_quarter,
            'quarter_time_seconds' => $this->quarter_time_seconds,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
        ];
    }
}
