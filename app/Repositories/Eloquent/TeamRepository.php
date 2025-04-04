<?php

namespace App\Repositories\Eloquent;

use App\Models\Team;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use Illuminate\Support\Collection;

class TeamRepository implements TeamRepositoryInterface
{
    public function findById($id): ?Team
    {
        return Team::find($id);
    }

    public function getAllActive(): Collection
    {
        return Team::all();
    }

    public function getRandomTeams(int $count): Collection
    {
        return Team::inRandomOrder()->limit($count)->get();
    }
} 