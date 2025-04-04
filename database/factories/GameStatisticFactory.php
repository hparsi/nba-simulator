<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameStatistic;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameStatistic>
 */
class GameStatisticFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = GameStatistic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'team_id' => Team::factory(),
            'is_home_team' => $this->faker->boolean(),
            'q1_score' => $this->faker->numberBetween(15, 35),
            'q2_score' => $this->faker->numberBetween(15, 35),
            'q3_score' => $this->faker->numberBetween(15, 35),
            'q4_score' => $this->faker->numberBetween(15, 35),
            'ot_score' => 0,
            'field_goals_made' => $fieldGoalsMade = $this->faker->numberBetween(25, 45),
            'field_goals_attempted' => $fieldGoalsMade + $this->faker->numberBetween(20, 40),
            'three_pointers_made' => $threePointersMade = $this->faker->numberBetween(8, 20),
            'three_pointers_attempted' => $threePointersMade + $this->faker->numberBetween(10, 25),
            'free_throws_made' => $freeThrowsMade = $this->faker->numberBetween(10, 25),
            'free_throws_attempted' => $freeThrowsMade + $this->faker->numberBetween(5, 15),
            'assists' => $this->faker->numberBetween(15, 35),
            'attack_count' => $this->faker->numberBetween(80, 100),
            'field_goal_percentage' => null,
            'three_point_percentage' => null,
            'free_throw_percentage' => null,
        ];
    }

    /**
     * Configure the statistics for home team.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function homeTeam(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_home_team' => true,
            ];
        });
    }

    /**
     * Configure the statistics for away team.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function awayTeam(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_home_team' => false,
            ];
        });
    }

    /**
     * Configure the statistics for a team with overtime.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withOvertime(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'ot_score' => $this->faker->numberBetween(5, 15),
            ];
        });
    }
} 