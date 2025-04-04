<?php

namespace App\Repositories\Eloquent;

use App\Models\Season;
use App\Repositories\Interfaces\SeasonRepositoryInterface;

class SeasonRepository implements SeasonRepositoryInterface
{
    public function findById($id): ?Season
    {
        return Season::find($id);
    }

    public function getCurrentSeason(): ?Season
    {
        return Season::where('is_active', true)->first();
    }
} 