<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'score_value' => $this->score_value,
            'quarter' => $this->quarter,
            'quarter_time' => $this->quarter_time,
            'description' => $this->description,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'created_at' => $this->created_at,
            'player' => $this->player ? [
                'id' => $this->player->id,
                'name' => $this->player->first_name . ' ' . $this->player->last_name,
                'team_id' => $this->player->team_id
            ] : null,
            'team' => [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ]
        ];
        
        if ($this->relationLoaded('secondaryPlayer') && $this->secondaryPlayer) {
            $data['secondary_player'] = [
                'id' => $this->secondaryPlayer->id,
                'name' => $this->secondaryPlayer->first_name . ' ' . $this->secondaryPlayer->last_name,
                'team_id' => $this->secondaryPlayer->team_id
            ];
        }
        
        return $data;
    }
}
