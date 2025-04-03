<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameStatistic extends Model
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
        'is_home_team',
        'q1_score',
        'q2_score',
        'q3_score',
        'q4_score',
        'ot_score',
        'field_goals_made',
        'field_goals_attempted',
        'three_pointers_made',
        'three_pointers_attempted',
        'free_throws_made',
        'free_throws_attempted',
        'assists',
        'attack_count',
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
        'is_home_team' => 'boolean',
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
     * Get the team that this statistic belongs to
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the total score
     */
    public function getTotalScoreAttribute(): int
    {
        return $this->q1_score + $this->q2_score + $this->q3_score + $this->q4_score + $this->ot_score;
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
