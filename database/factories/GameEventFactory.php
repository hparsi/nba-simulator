<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameEvent>
 */
class GameEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = GameEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            'game_start', 'game_end', 'quarter_start', 'quarter_end',
            'field_goal', 'three_pointer', 'free_throw',
            'rebound', 'assist', 'steal', 'block', 'turnover',
            'foul', 'substitution', 'timeout'
        ];
        
        $eventType = $this->faker->randomElement($eventTypes);
        $scoreValue = 0;
        
        if ($eventType === 'field_goal') {
            $scoreValue = 2;
        } elseif ($eventType === 'three_pointer') {
            $scoreValue = 3;
        } elseif ($eventType === 'free_throw') {
            $scoreValue = 1;
        }
        
        $quarter = $this->faker->numberBetween(1, 4);
        $quarterTime = $this->faker->numberBetween(0, 720);
        $homeScore = $this->faker->numberBetween(0, 120);
        $awayScore = $this->faker->numberBetween(0, 120);
        
        $includeTeamAndPlayer = in_array($eventType, [
            'field_goal', 'three_pointer', 'free_throw',
            'rebound', 'assist', 'steal', 'block', 'turnover',
            'foul', 'substitution'
        ]);
        
        $data = [
            'game_id' => Game::factory(),
            'event_type' => $eventType,
            'score_value' => $scoreValue,
            'quarter' => $quarter,
            'quarter_time' => $quarterTime,
            'description' => $this->getDescriptionForEvent($eventType),
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ];
        
        if ($includeTeamAndPlayer) {
            $team = Team::factory()->create();
            $player = Player::factory()->create(['team_id' => $team->id]);
            
            $data['team_id'] = $team->id;
            $data['player_id'] = $player->id;
            
            if (in_array($eventType, ['assist', 'field_goal', 'three_pointer'])) {
                $secondaryPlayer = Player::factory()->create(['team_id' => $team->id]);
                $data['secondary_player_id'] = $secondaryPlayer->id;
            }
        }
        
        return $data;
    }
    
    /**
     * Generate a description for the given event type
     */
    private function getDescriptionForEvent(string $eventType): string
    {
        switch ($eventType) {
            case 'game_start':
                return 'Game started between Home Team and Away Team';
            case 'game_end':
                return 'Game ended. Home Team wins 105-98';
            case 'quarter_start':
                return 'Quarter 2 started';
            case 'quarter_end':
                return 'Quarter 3 ended';
            case 'field_goal':
                return 'John Smith made a field goal';
            case 'three_pointer':
                return 'John Smith made a three-pointer (assisted by James Johnson)';
            case 'free_throw':
                return 'John Smith made free throw 2 of 2';
            case 'rebound':
                return 'John Smith grabbed the rebound';
            case 'assist':
                return 'John Smith assisted on the play';
            case 'steal':
                return 'John Smith stole the ball';
            case 'block':
                return 'John Smith blocked the shot';
            case 'turnover':
                return 'John Smith committed a turnover';
            case 'foul':
                return 'John Smith committed a foul on James Johnson';
            case 'substitution':
                return 'John Smith substituted in for James Johnson';
            case 'timeout':
                return 'Team called a timeout';
            default:
                return 'Event occurred during the game';
        }
    }

    /**
     * Configure the event as a game start.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function gameStart(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'game_start',
                'score_value' => 0,
                'quarter' => 1,
                'quarter_time' => 720,
                'description' => 'Game started between Home Team and Away Team',
                'home_score' => 0,
                'away_score' => 0,
                'team_id' => null,
                'player_id' => null,
                'secondary_player_id' => null,
            ];
        });
    }

    /**
     * Configure the event as a game end.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function gameEnd(): Factory
    {
        return $this->state(function (array $attributes) {
            $homeScore = $this->faker->numberBetween(80, 130);
            $awayScore = $this->faker->numberBetween(80, 130);
            
            return [
                'event_type' => 'game_end',
                'score_value' => 0,
                'quarter' => 4,
                'quarter_time' => 0,
                'description' => "Game ended. " . ($homeScore > $awayScore ? "Home" : "Away") . " Team wins " . max($homeScore, $awayScore) . "-" . min($homeScore, $awayScore),
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'team_id' => null,
                'player_id' => null,
                'secondary_player_id' => null,
            ];
        });
    }
} 