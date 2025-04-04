<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\Game;
use App\Models\Team;
use App\Models\Player;
use App\Models\GameEvent;
use App\Models\PlayerStatistic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class GameControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $game;
    protected $homeTeam;
    protected $awayTeam;
    protected $player;
    protected $gameEvent;

    public function setUp(): void
    {
        parent::setUp();

        $this->homeTeam = Team::factory()->create([
            'name' => 'Home Team'
        ]);
        
        $this->awayTeam = Team::factory()->create([
            'name' => 'Away Team'
        ]);

        $this->game = Game::factory()->create([
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(1)->startOfDay(),
        ]);

        $this->player = Player::factory()->create([
            'team_id' => $this->homeTeam->id,
            'first_name' => 'Test',
            'last_name' => 'Player'
        ]);

        PlayerStatistic::factory()->create([
            'game_id' => $this->game->id,
            'player_id' => $this->player->id,
            'points' => 10,
            'assists' => 5
        ]);
        
        $this->gameEvent = GameEvent::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->homeTeam->id,
            'player_id' => $this->player->id,
            'event_type' => 'field_goal',
            'score_value' => 2,
            'quarter' => 1,
            'quarter_time' => 600,
            'description' => 'Test Player made a field goal',
            'home_score' => 2,
            'away_score' => 0
        ]);
    }

    /**
     * Test index endpoint retrieves scheduled games.
     */
    public function test_index_endpoint_returns_scheduled_games(): void
    {
        $response = $this->getJson('/api/games');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'date',
                        'home_team' => [
                            'id',
                            'name'
                        ],
                        'away_team' => [
                            'id',
                            'name'
                        ],
                    ]
                ]
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->game->id)
            ->assertJsonPath('data.0.status', 'scheduled')
            ->assertJsonPath('data.0.home_team.id', $this->homeTeam->id)
            ->assertJsonPath('data.0.away_team.id', $this->awayTeam->id);
    }

    /**
     * Test show endpoint returns game details.
     */
    public function test_show_endpoint_returns_game_details(): void
    {
        $response = $this->getJson('/api/games/' . $this->game->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'date',
                    'home_team' => [
                        'id',
                        'name'
                    ],
                    'away_team' => [
                        'id',
                        'name'
                    ],
                    'current_quarter',
                    'quarter_time_seconds',
                    'started_at',
                    'ended_at'
                ]
            ])
            ->assertJsonPath('data.id', $this->game->id)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.home_team.id', $this->homeTeam->id)
            ->assertJsonPath('data.away_team.id', $this->awayTeam->id);
    }

    /**
     * Test events endpoint returns game events.
     */
    public function test_events_endpoint_returns_game_events(): void
    {
        $response = $this->getJson('/api/games/' . $this->game->id . '/events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'event_type',
                        'score_value',
                        'quarter',
                        'quarter_time',
                        'description',
                        'home_score',
                        'away_score',
                        'created_at',
                        'player' => [
                            'id',
                            'name',
                            'team_id'
                        ],
                        'team' => [
                            'id',
                            'name'
                        ]
                    ]
                ]
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->gameEvent->id)
            ->assertJsonPath('data.0.event_type', 'field_goal')
            ->assertJsonPath('data.0.player.id', $this->player->id)
            ->assertJsonPath('data.0.team.id', $this->homeTeam->id);
    }

    /**
     * Test statistics endpoint returns player statistics.
     */
    public function test_statistics_endpoint_returns_player_statistics(): void
    {
        $response = $this->getJson('/api/games/' . $this->game->id . '/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'home_team' => [
                        'players' => [
                            '*' => [
                                'id',
                                'name',
                                'points',
                                'assists',
                                'field_goals_attempted',
                                'field_goals_made',
                                'three_pointers_attempted',
                                'three_pointers_made'
                            ]
                        ],
                        'totals' => [
                            'points',
                            'assists'
                        ]
                    ],
                    'away_team' => [
                        'players' => [],
                        'totals' => [
                            'points',
                            'assists'
                        ]
                    ]
                ]
            ])
            ->assertJsonPath('data.home_team.players.0.id', $this->player->id)
            ->assertJsonPath('data.home_team.players.0.points', 10)
            ->assertJsonPath('data.home_team.players.0.assists', 5);
    }

    /**
     * Test show endpoint with invalid game ID.
     */
    public function test_show_endpoint_with_invalid_game_id(): void
    {
        $response = $this->getJson('/api/games/999999');
        $response->assertStatus(404);
    }

    /**
     * Test events endpoint with invalid game ID.
     */
    public function test_events_endpoint_with_invalid_game_id(): void
    {
        $response = $this->getJson('/api/games/999999/events');
        $response->assertStatus(200)
                 ->assertJsonCount(0, 'data');
    }

    /**
     * Test events endpoint with pagination.
     */
    public function test_events_endpoint_with_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            GameEvent::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->homeTeam->id,
                'player_id' => $this->player->id,
            ]);
        }

        $response = $this->getJson('/api/games/' . $this->game->id . '/events?limit=3');
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        $sinceId = $this->gameEvent->id;
        $response = $this->getJson('/api/games/' . $this->game->id . '/events?since_id=' . $sinceId);
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
        
        $response->assertJsonPath('data', function ($data) use ($sinceId) {
            return collect($data)->every(fn ($item) => $item['id'] > $sinceId);
        });
    }

    /**
     * Test statistics endpoint with invalid game ID.
     */
    public function test_statistics_endpoint_with_invalid_game_id(): void
    {
        $response = $this->getJson('/api/games/999999/statistics');
        $response->assertStatus(404);
    }
} 