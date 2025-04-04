<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Game::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'scheduled_at' => $this->faker->dateTimeBetween('+1 day', '+3 months'),
            'started_at' => null,
            'ended_at' => null,
            'status' => 'scheduled',
            'current_quarter' => 0,
            'quarter_time_seconds' => 0,
            'home_team_score' => 0,
            'away_team_score' => 0,
        ];
    }

    /**
     * Configure the game as in progress.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function inProgress(): Factory
    {
        return $this->state(function (array $attributes) {
            $quarter = $this->faker->numberBetween(1, 4);
            $quarterTime = $this->faker->numberBetween(0, 720);
            $startedAt = Carbon::now()->subHours($this->faker->numberBetween(0, 2));
            
            return [
                'status' => 'in_progress',
                'started_at' => $startedAt,
                'current_quarter' => $quarter,
                'quarter_time_seconds' => $quarterTime,
                'home_team_score' => $this->faker->numberBetween(10 * $quarter, 30 * $quarter),
                'away_team_score' => $this->faker->numberBetween(10 * $quarter, 30 * $quarter),
            ];
        });
    }

    /**
     * Configure the game as completed.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function completed(): Factory
    {
        return $this->state(function (array $attributes) {
            $startedAt = Carbon::now()->subHours(3);
            $endedAt = $startedAt->copy()->addHours(2);
            
            $homeScore = $this->faker->numberBetween(80, 130);
            $awayScore = $this->faker->numberBetween(80, 130);
            
            if ($homeScore === $awayScore) {
                $homeScore += $this->faker->numberBetween(1, 5);
            }
            
            return [
                'status' => 'completed',
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'current_quarter' => 4,
                'quarter_time_seconds' => 0,
                'home_team_score' => $homeScore,
                'away_team_score' => $awayScore,
            ];
        });
    }
} 