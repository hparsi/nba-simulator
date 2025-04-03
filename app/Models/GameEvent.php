<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameEvent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'game_id',
        'team_id',
        'player_id',
        'secondary_player_id',
        'event_type',
        'score_value',
        'quarter',
        'quarter_time',
        'description',
        'home_score',
        'away_score',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'score_value' => 'integer',
        'quarter' => 'integer',
        'quarter_time' => 'integer',
    ];

    /**
     * Get the game this event belongs to
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the team this event belongs to
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the primary player involved in this event
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Get the secondary player involved in this event (e.g., assist provider)
     */
    public function secondaryPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'secondary_player_id');
    }

    /**
     * Get the formatted game time of this event
     */
    public function getFormattedGameTimeAttribute(): string
    {
        $minutes = floor($this->quarter_time / 60);
        $seconds = $this->quarter_time % 60;
        
        $quarterName = $this->quarter > 4 ? 'OT' . ($this->quarter - 4) : 'Q' . $this->quarter;
        
        return sprintf('%s %d:%02d', $quarterName, $minutes, $seconds);
    }

    /**
     * Get the formatted score at the time of this event
     */
    public function getFormattedScoreAttribute(): string
    {
        return "{$this->home_score}-{$this->away_score}";
    }

    /**
     * Scope to get events of a specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope to get events from a specific quarter
     */
    public function scopeInQuarter($query, $quarter)
    {
        return $query->where('quarter', $quarter);
    }

    /**
     * Scope to get scoring events
     */
    public function scopeScoring($query)
    {
        return $query->whereIn('event_type', ['field_goal', 'three_pointer', 'free_throw']);
    }
}
