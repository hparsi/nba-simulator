<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerStatistic;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerStatistic>
 */
class PlayerStatisticFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PlayerStatistic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fieldGoalsMade = $this->faker->numberBetween(0, 12);
        $fieldGoalsAttempted = $fieldGoalsMade + $this->faker->numberBetween(0, 10);
        
        $threePointersMade = $this->faker->numberBetween(0, 6);
        $threePointersAttempted = $threePointersMade + $this->faker->numberBetween(0, 6);
        
        $freeThrowsMade = $this->faker->numberBetween(0, 8);
        $freeThrowsAttempted = $freeThrowsMade + $this->faker->numberBetween(0, 4);
        
        $points = ($fieldGoalsMade - $threePointersMade) * 2 + $threePointersMade * 3 + $freeThrowsMade;
        
        return [
            'game_id' => Game::factory(),
            'player_id' => Player::factory(),
            'team_id' => function (array $attributes) {
                return Player::find($attributes['player_id'])->team_id;
            },
            'seconds_played' => $this->faker->numberBetween(300, 2400),
            'points' => $points,
            'assists' => $this->faker->numberBetween(0, 12),
            'field_goals_made' => $fieldGoalsMade,
            'field_goals_attempted' => $fieldGoalsAttempted,
            'three_pointers_made' => $threePointersMade,
            'three_pointers_attempted' => $threePointersAttempted,
            'free_throws_made' => $freeThrowsMade,
            'free_throws_attempted' => $freeThrowsAttempted,
            'field_goal_percentage' => $fieldGoalsAttempted > 0 ? round(($fieldGoalsMade / $fieldGoalsAttempted) * 100, 1) : null,
            'three_point_percentage' => $threePointersAttempted > 0 ? round(($threePointersMade / $threePointersAttempted) * 100, 1) : null,
            'free_throw_percentage' => $freeThrowsAttempted > 0 ? round(($freeThrowsMade / $freeThrowsAttempted) * 100, 1) : null,
        ];
    }

    /**
     * Configure the statistics for a player with high score.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function highScorer(): Factory
    {
        return $this->state(function (array $attributes) {
            $fieldGoalsMade = $this->faker->numberBetween(10, 18);
            $fieldGoalsAttempted = $fieldGoalsMade + $this->faker->numberBetween(4, 10);
            
            $threePointersMade = $this->faker->numberBetween(3, 8);
            $threePointersAttempted = $threePointersMade + $this->faker->numberBetween(2, 6);
            
            $freeThrowsMade = $this->faker->numberBetween(5, 12);
            $freeThrowsAttempted = $freeThrowsMade + $this->faker->numberBetween(0, 3);
            
            $points = ($fieldGoalsMade - $threePointersMade) * 2 + $threePointersMade * 3 + $freeThrowsMade;
            
            return [
                'points' => $points,
                'field_goals_made' => $fieldGoalsMade,
                'field_goals_attempted' => $fieldGoalsAttempted,
                'three_pointers_made' => $threePointersMade,
                'three_pointers_attempted' => $threePointersAttempted,
                'free_throws_made' => $freeThrowsMade,
                'free_throws_attempted' => $freeThrowsAttempted,
                'field_goal_percentage' => round(($fieldGoalsMade / $fieldGoalsAttempted) * 100, 1),
                'three_point_percentage' => round(($threePointersMade / $threePointersAttempted) * 100, 1),
                'free_throw_percentage' => round(($freeThrowsMade / $freeThrowsAttempted) * 100, 1),
            ];
        });
    }
} 