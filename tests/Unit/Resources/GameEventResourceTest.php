<?php

namespace Tests\Unit\Resources;

use Tests\TestCase;
use App\Http\Resources\GameEventResource;
use App\Models\Game;
use App\Models\Team;
use App\Models\Player;
use App\Models\GameEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GameEventResourceTest extends TestCase
{
    use RefreshDatabase;

    protected $game;
    protected $team;
    protected $player;
    protected $gameEvent;

    public function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create([
            'name' => 'Test Team'
        ]);

        $awayTeam = Team::factory()->create();
        $this->game = Game::factory()->create([
            'home_team_id' => $this->team->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'in_progress'
        ]);

        $this->player = Player::factory()->create([
            'team_id' => $this->team->id,
            'first_name' => 'Test',
            'last_name' => 'Player'
        ]);

        $this->gameEvent = GameEvent::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'player_id' => $this->player->id,
            'event_type' => 'field_goal',
            'score_value' => 2,
            'quarter' => 1,
            'quarter_time' => 600,
            'description' => 'Test Player made a field goal',
            'home_score' => 2,
            'away_score' => 0
        ]);

        $this->gameEvent->load(['player', 'team']);
    }

    /**
     * Test that the resource contains all required fields.
     */
    public function test_resource_contains_required_fields(): void
    {
        $resource = new GameEventResource($this->gameEvent);
        $resourceArray = $resource->toArray(request());

        $this->assertArrayHasKey('id', $resourceArray);
        $this->assertArrayHasKey('event_type', $resourceArray);
        $this->assertArrayHasKey('score_value', $resourceArray);
        $this->assertArrayHasKey('quarter', $resourceArray);
        $this->assertArrayHasKey('quarter_time', $resourceArray);
        $this->assertArrayHasKey('description', $resourceArray);
        $this->assertArrayHasKey('home_score', $resourceArray);
        $this->assertArrayHasKey('away_score', $resourceArray);
        $this->assertArrayHasKey('created_at', $resourceArray);
        $this->assertArrayHasKey('player', $resourceArray);
        $this->assertArrayHasKey('team', $resourceArray);
    }

    /**
     * Test that the event basic data is correct.
     */
    public function test_event_data_is_correct(): void
    {
        $resource = new GameEventResource($this->gameEvent);
        $resourceArray = $resource->toArray(request());

        $this->assertEquals($this->gameEvent->id, $resourceArray['id']);
        $this->assertEquals($this->gameEvent->event_type, $resourceArray['event_type']);
        $this->assertEquals($this->gameEvent->score_value, $resourceArray['score_value']);
        $this->assertEquals($this->gameEvent->quarter, $resourceArray['quarter']);
        $this->assertEquals($this->gameEvent->quarter_time, $resourceArray['quarter_time']);
        $this->assertEquals($this->gameEvent->description, $resourceArray['description']);
        $this->assertEquals($this->gameEvent->home_score, $resourceArray['home_score']);
        $this->assertEquals($this->gameEvent->away_score, $resourceArray['away_score']);
    }

    /**
     * Test that the nested player data is correct.
     */
    public function test_player_data_is_correct(): void
    {
        $resource = new GameEventResource($this->gameEvent);
        $resourceArray = $resource->toArray(request());

        $this->assertIsArray($resourceArray['player']);
        $this->assertArrayHasKey('id', $resourceArray['player']);
        $this->assertArrayHasKey('name', $resourceArray['player']);
        $this->assertArrayHasKey('team_id', $resourceArray['player']);
        
        $this->assertEquals($this->player->id, $resourceArray['player']['id']);
        $this->assertEquals($this->player->first_name . ' ' . $this->player->last_name, $resourceArray['player']['name']);
        $this->assertEquals($this->player->team_id, $resourceArray['player']['team_id']);
    }

    /**
     * Test that the nested team data is correct.
     */
    public function test_team_data_is_correct(): void
    {
        $resource = new GameEventResource($this->gameEvent);
        $resourceArray = $resource->toArray(request());

        $this->assertIsArray($resourceArray['team']);
        $this->assertArrayHasKey('id', $resourceArray['team']);
        $this->assertArrayHasKey('name', $resourceArray['team']);
        
        $this->assertEquals($this->team->id, $resourceArray['team']['id']);
        $this->assertEquals($this->team->name, $resourceArray['team']['name']);
    }

    /**
     * Test that secondary player data is not included if not loaded.
     */
    public function test_secondary_player_not_included_if_not_loaded(): void
    {
        $resource = new GameEventResource($this->gameEvent);
        $resourceArray = $resource->toArray(request());

        $this->assertArrayNotHasKey('secondary_player', $resourceArray);
    }
}
