<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimulationStateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->resource;
        
        return [
            'active' => !empty($state['active_games'] ?? []),
            'active_games' => $state['active_games'] ?? [],
            'completed_games' => $state['completed_games'] ?? [],
            'game_progress' => $state['game_progress'] ?? [],
            'timestamp' => now()->toDateTimeString()
        ];
    }
}
