<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimulationUpdateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $update = $this->resource;
        
        return [
            'active_games' => $update['active_games'] ?? [],
            'completed_games' => $update['completed_games'] ?? [],
            'updates' => $update['updates'] ?? [],
            'timestamp' => now()->toDateTimeString()
        ];
    }
}
