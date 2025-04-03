<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueStanding extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'season_id',
        'team_id',
        'wins',
        'losses',
        'win_percentage',
        'home_wins',
        'home_losses',
        'away_wins',
        'away_losses',
        'points_scored',
        'games_behind',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'win_percentage' => 'float',
        'games_behind' => 'float',
    ];

    /**
     * Get the season this standing belongs to
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Get the team this standing belongs to
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the formatted record (e.g., "50-32")
     */
    public function getRecordAttribute(): string
    {
        return "{$this->wins}-{$this->losses}";
    }

    /**
     * Get the formatted home record
     */
    public function getHomeRecordAttribute(): string
    {
        return "{$this->home_wins}-{$this->home_losses}";
    }

    /**
     * Get the formatted away record
     */
    public function getAwayRecordAttribute(): string
    {
        return "{$this->away_wins}-{$this->away_losses}";
    }

    /**
     * Update the win percentage
     */
    public function updateWinPercentage(): void
    {
        $totalGames = $this->wins + $this->losses;
        
        if ($totalGames > 0) {
            $this->win_percentage = round($this->wins / $totalGames, 3);
            $this->save();
        }
    }
}
