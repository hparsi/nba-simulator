<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $teamNames = [
            'Celtics', 'Lakers', 'Bulls', 'Warriors', 'Knicks',
            'Heat', 'Rockets', 'Mavericks', 'Nuggets', 'Clippers',
            'Spurs', 'Thunder', 'Raptors', 'Nets', 'Bucks',
            'Suns', 'Hawks', 'Pistons', 'Jazz', 'Pelicans',
            'Grizzlies', 'Trail Blazers', 'Wizards', '76ers', 'Kings',
            'Magic', 'Pacers', 'Hornets', 'Timberwolves', 'Cavaliers'
        ];
        
        $cityNames = [
            'Boston', 'Los Angeles', 'Chicago', 'San Francisco', 'New York',
            'Miami', 'Houston', 'Dallas', 'Denver', 'Los Angeles',
            'San Antonio', 'Oklahoma City', 'Toronto', 'Brooklyn', 'Milwaukee',
            'Phoenix', 'Atlanta', 'Detroit', 'Utah', 'New Orleans',
            'Memphis', 'Portland', 'Washington', 'Philadelphia', 'Sacramento',
            'Orlando', 'Indiana', 'Charlotte', 'Minnesota', 'Cleveland'
        ];
        
        $index = $this->faker->numberBetween(0, count($teamNames) - 1);
        
        return [
            'name' => $cityNames[$index] . ' ' . $teamNames[$index],
        ];
    }

    /**
     * Configure the team with a name indicating it's from the Eastern Conference.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function eastern(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => $attributes['name'] . ' (East)',
            ];
        });
    }

    /**
     * Configure the team with a name indicating it's from the Western Conference.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function western(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => $attributes['name'] . ' (West)',
            ];
        });
    }
}