<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Game;
use App\Models\Team;
use App\Models\GameEvent;
use App\Models\Player;

class GameControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $homeTeam;
    protected $awayTeam;
    protected $game;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->homeTeam = Team::factory()->create(['name' => 'Home Team']);
        $this->awayTeam = Team::factory()->create(['name' => 'Away Team']);

        $this->game = Game::factory()->create([
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'home_team_score' => 0,
            'away_team_score' => 0,
        ]);
    }

    /**
     * Test the index endpoint with no filters.
     */
    public function test_index_returns_all_games(): void
    {
        $response = $this->getJson('/api/games');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->game->id)
            ->assertJsonPath('data.0.status', 'scheduled')
            ->assertJsonPath('data.0.home_team.id', $this->homeTeam->id)
            ->assertJsonPath('data.0.away_team.id', $this->awayTeam->id);
    }

    /**
     * Test the index endpoint with status filter.
     */
    public function test_index_filters_by_status(): void
    {
        $completedGame = Game::factory()->create([
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'status' => 'completed',
            'scheduled_at' => now(),
        ]);

        $response = $this->getJson('/api/games?status=scheduled');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->game->id)
            ->assertJsonPath('data.0.status', 'scheduled');
    }

    /**
     * Test the index endpoint with IDs filter.
     */
    public function test_index_filters_by_ids(): void
    {
        $game2 = Game::factory()->create([
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $response = $this->getJson('/api/games?ids=' . $this->game->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->game->id);
    }

    /**
     * Test the index endpoint with events included.
     */
    public function test_index_includes_events_when_requested(): void
    {
        $player = Player::factory()->create(['team_id' => $this->homeTeam->id]);
        $event = GameEvent::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->homeTeam->id,
            'player_id' => $player->id,
            'event_type' => 'field_goal',
            'quarter' => 1,
        ]);

        $response = $this->getJson('/api/games?with_events=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * Test the show endpoint.
     */
    public function test_show_returns_single_game(): void
    {
        $response = $this->getJson('/api/games/' . $this->game->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'home_team' => [
                        'id',
                        'name'
                    ],
                    'away_team' => [
                        'id',
                        'name'
                    ]
                ]
            ]);
    }

    /**
     * Test the show endpoint with a non-existent game.
     */
    public function test_show_returns_404_for_nonexistent_game(): void
    {
        $response = $this->getJson('/api/games/999999');

        $response->assertStatus(404);
    }

    /**
     * Test the getEvents endpoint.
     */
    public function test_get_events_returns_game_events(): void
    {
        $player = Player::factory()->create(['team_id' => $this->homeTeam->id]);
        
        $event1 = GameEvent::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->homeTeam->id,
            'player_id' => $player->id,
            'event_type' => 'field_goal',
            'quarter' => 1,
            'score_value' => 2,
        ]);
        
        $event2 = GameEvent::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->homeTeam->id,
            'player_id' => $player->id,
            'event_type' => 'three_pointer',
            'quarter' => 1,
            'score_value' => 3,
        ]);

        $response = $this->getJson('/api/games/' . $this->game->id . '/events');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.event_type', $event2->event_type)  // Latest event first (DESC order)
            ->assertJsonPath('data.1.event_type', $event1->event_type);
    }

    /**
     * Test the getEvents endpoint with since_id parameter.
     */
    public function test_get_events_with_since_id_parameter(): void
    {
        $player = Player::factory()->create(['team_id' => $this->homeTeam->id]);
        
        $event1 = GameEvent::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->homeTeam->id,
            'player_id' => $player->id,
            'event_type' => 'field_goal',
            'quarter' => 1,
        ]);
        
        $event2 = GameEvent::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->homeTeam->id,
            'player_id' => $player->id,
            'event_type' => 'three_pointer',
            'quarter' => 1,
        ]);

        $response = $this->getJson('/api/games/' . $this->game->id . '/events?since_id=' . $event1->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $event2->id);
    }

    /**
     * Test the getEvents endpoint with limit parameter.
     */
    public function test_get_events_with_limit_parameter(): void
    {
        $player = Player::factory()->create(['team_id' => $this->homeTeam->id]);
        
        $events = [];
        for ($i = 0; $i < 5; $i++) {
            $events[] = GameEvent::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->homeTeam->id,
                'player_id' => $player->id,
                'event_type' => 'field_goal',
                'quarter' => 1,
            ]);
        }

        $response = $this->getJson('/api/games/' . $this->game->id . '/events?limit=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
