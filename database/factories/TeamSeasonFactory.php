<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\Team;
use App\Models\TeamSeason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeamSeason>
 */
class TeamSeasonFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TeamSeason::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'season_id' => Season::factory(),
            'wins' => $this->faker->numberBetween(0, 82),
            'losses' => $this->faker->numberBetween(0, 82),
            'games_played' => function (array $attributes) {
                return $attributes['wins'] + $attributes['losses'];
            },
            'points_for' => $this->faker->numberBetween(7000, 10000),
            'points_against' => $this->faker->numberBetween(7000, 10000),
        ];
    }

    /**
     * Configure the factory to create a team season record for a winning team.
     */
    public function winningRecord(): self
    {
        return $this->state(function (array $attributes) {
            $wins = $this->faker->numberBetween(42, 73);
            $losses = 82 - $wins;
            
            return [
                'wins' => $wins,
                'losses' => $losses,
                'games_played' => $wins + $losses,
            ];
        });
    }

    /**
     * Configure the factory to create a team season record for a losing team.
     */
    public function losingRecord(): self
    {
        return $this->state(function (array $attributes) {
            $losses = $this->faker->numberBetween(42, 73);
            $wins = 82 - $losses;
            
            return [
                'wins' => $wins,
                'losses' => $losses,
                'games_played' => $wins + $losses,
            ];
        });
    }
} 