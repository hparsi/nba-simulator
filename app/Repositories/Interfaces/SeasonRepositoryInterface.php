<?php

namespace App\Repositories\Interfaces;

use App\Models\Season;

interface SeasonRepositoryInterface
{
    public function findById($id): ?Season;
    public function getCurrentSeason(): ?Season;
} 