<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'season_id',
        'home_team_id',
        'away_team_id',
        'scheduled_at',
        'started_at',
        'ended_at',
        'home_team_score',
        'away_team_score',
        'status',
        'current_quarter',
        'quarter_time_seconds',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the season that the game belongs to
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Get the home team
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * Get the away team
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * Get the home team's game statistics
     */
    public function homeTeamStats(): HasOne
    {
        return $this->hasOne(GameStatistic::class)->where('is_home_team', true);
    }

    /**
     * Get the away team's game statistics
     */
    public function awayTeamStats(): HasOne
    {
        return $this->hasOne(GameStatistic::class)->where('is_home_team', false);
    }

    /**
     * Get all player statistics for this game
     */
    public function playerStatistics(): HasMany
    {
        return $this->hasMany(PlayerStatistic::class);
    }

    /**
     * Get all game events for this game
     */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    /**
     * Scope to get scheduled games
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope to get in-progress games
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope to get completed games
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Check if the game is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if the game is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Format the current game time
     */
    public function formattedGameTime(): string
    {
        if ($this->current_quarter === 0) {
            return 'Not Started';
        }
        
        $minutes = floor($this->quarter_time_seconds / 60);
        $seconds = $this->quarter_time_seconds % 60;
        
        $quarterName = $this->current_quarter > 4 ? 'OT' . ($this->current_quarter - 4) : 'Q' . $this->current_quarter;
        
        return sprintf('%s %d:%02d', $quarterName, $minutes, $seconds);
    }
}
