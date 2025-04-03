<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get all players on this team
     */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /**
     * Get all home games for this team
     */
    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    /**
     * Get all away games for this team
     */
    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'away_team_id');
    }

    /**
     * Get all game statistics for this team
     */
    public function gameStatistics(): HasMany
    {
        return $this->hasMany(GameStatistic::class);
    }

    /**
     * Get all league standings for this team
     */
    public function leagueStandings(): HasMany
    {
        return $this->hasMany(LeagueStanding::class);
    }

    /**
     * Get all games for this team (both home and away)
     */
    public function games()
    {
        return $this->homeGames->merge($this->awayGames);
    }
}
