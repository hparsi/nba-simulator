<?php

namespace App\Repositories\Interfaces;

use App\Models\Team;
use Illuminate\Support\Collection;

interface TeamRepositoryInterface
{
    public function findById($id): ?Team;
    public function getAllActive(): Collection;
    public function getRandomTeams(int $count): Collection;
} 