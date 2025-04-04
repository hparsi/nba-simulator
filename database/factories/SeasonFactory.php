<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Season>
 */
class SeasonFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Season::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = $this->faker->numberBetween(2020, 2030);
        
        return [
            'name' => $year . '-' . ($year + 1) . ' NBA Season',
            'year_start' => $year,
            'year_end' => $year + 1,
            'start_date' => $year . '-10-24',
            'end_date' => ($year + 1) . '-06-15',
            'is_active' => $this->faker->boolean(20), // 20% chance of being active
        ];
    }

    /**
     * Indicate that the season is active.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function active(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => true,
            ];
        });
    }
} 