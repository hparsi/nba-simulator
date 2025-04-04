<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class GameCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($game) {
                return new GameResource($game);
            }),
            'meta' => [
                'count' => $this->collection->count(),
                'timestamp' => now()->toDateTimeString()
            ]
        ];
    }
}
