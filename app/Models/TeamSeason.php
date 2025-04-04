<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamSeason extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'season_id',
        'wins',
        'losses',
        'games_played',
        'points_for',
        'points_against',
    ];

    /**
     * Get the team that this record belongs to
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the season that this record belongs to
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Get the winning percentage
     */
    public function getWinningPercentageAttribute(): float
    {
        if ($this->games_played === 0) {
            return 0.0;
        }

        return round($this->wins / $this->games_played, 3);
    }

    /**
     * Get the point differential
     */
    public function getPointDifferentialAttribute(): int
    {
        return $this->points_for - $this->points_against;
    }
} 