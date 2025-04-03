<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStatistic extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'game_id',
        'player_id',
        'team_id',
        'seconds_played',
        'points',
        'assists',
        'field_goals_made',
        'field_goals_attempted',
        'three_pointers_made',
        'three_pointers_attempted',
        'free_throws_made',
        'free_throws_attempted',
        'field_goal_percentage',
        'three_point_percentage',
        'free_throw_percentage',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'field_goal_percentage' => 'float',
        'three_point_percentage' => 'float',
        'free_throw_percentage' => 'float',
    ];

    /**
     * Get the game that this statistic belongs to
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the player that this statistic belongs to
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Get the team that this statistic belongs to
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the minutes played as a formatted string
     */
    public function getMinutesPlayedAttribute(): string
    {
        $minutes = floor($this->seconds_played / 60);
        $seconds = $this->seconds_played % 60;
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Update the points based on shots made
     */
    public function updatePoints(): void
    {
        $this->points = ($this->field_goals_made - $this->three_pointers_made) * 2 + 
                       $this->three_pointers_made * 3 + 
                       $this->free_throws_made;
        $this->save();
    }

    /**
     * Update shooting percentages
     */
    public function updatePercentages(): void
    {
        // Field goal percentage
        if ($this->field_goals_attempted > 0) {
            $this->field_goal_percentage = round(($this->field_goals_made / $this->field_goals_attempted) * 100, 1);
        }

        // Three point percentage
        if ($this->three_pointers_attempted > 0) {
            $this->three_point_percentage = round(($this->three_pointers_made / $this->three_pointers_attempted) * 100, 1);
        }

        // Free throw percentage
        if ($this->free_throws_attempted > 0) {
            $this->free_throw_percentage = round(($this->free_throws_made / $this->free_throws_attempted) * 100, 1);
        }

        $this->save();
    }
}
