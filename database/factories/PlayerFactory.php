<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Player::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'position' => $this->faker->randomElement(['PG', 'SG', 'SF', 'PF', 'C']),
            'jersey_number' => $this->faker->unique()->numberBetween(0, 99),
            'is_active' => true,
        ];
    }

    /**
     * Configure the player as a guard (PG or SG).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function guard(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'position' => $this->faker->randomElement(['PG', 'SG']),
            ];
        });
    }

    /**
     * Configure the player as a forward (SF or PF).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function forward(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'position' => $this->faker->randomElement(['SF', 'PF']),
            ];
        });
    }

    /**
     * Configure the player as a center (C).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function center(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'position' => 'C',
            ];
        });
    }
}